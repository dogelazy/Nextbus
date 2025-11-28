const baseUrl = "http://127.0.0.1:5000";
const routeMap = {};
let currentLang = "en";
let currentDirection = "O"; // default outbound
let activeRouteLabel = null; // track the currently selected route
let refreshTimer = null;

const dict = {
  en: {
    title: "Next bus",
    routesHeader: "Search Routes",
    stopsHeader: "Stops for Selected Route",
    arrivalsHeader: "Upcoming Arrivals at Selected Stop",
    selectedRoute: "Selected Route",
    otherRoutes: "Other Routes at This Stop",
    noUpcoming: "No upcoming buses.",
    placeholder: "Select a route",
    directionreverse: "Click here to change direction of route"
  },
  zh: {
    title: "Next bus",
    routesHeader: "搜尋路線",
    stopsHeader: "所選路線的車站",
    arrivalsHeader: "所選車站的到站時間",
    selectedRoute: "所選路線",
    otherRoutes: "同一車站的其他路線",
    noUpcoming: "沒有即將到達的巴士。",
    placeholder: "選擇路線",
    directionreverse: "點擊此處更改路線方向"
  }
};

function setLanguage(lang) {
  currentLang = lang;
  updateLanguage();
  loadRoutes().then(() => {
    showRouteList("");
  });
  if (activeRouteLabel) loadStops();
}

function updateLanguage() {
  const t = dict[currentLang];
  document.getElementById("title").textContent = t.title;
  document.getElementById("routesHeader").textContent = t.routesHeader;
  document.getElementById("arrivalsHeader").textContent = t.arrivalsHeader;
  document.getElementById("routeInput").placeholder = t.placeholder;

  document.getElementById("stopsLabel").textContent = t.stopsHeader;
  document.getElementById("directionToggle").textContent = `⟳ ${t.directionreverse}`;
}

function formatETA(etaString) {
  if (!etaString) return currentLang === "en" ? "No ETA" : "沒有時間";
  const etaDate = new Date(etaString);
  const now = new Date();
  const diffMs = etaDate - now;
  const diffMinutes = Math.floor(diffMs / 60000);

  if (diffMinutes < 0) {
    return currentLang === "en" ? "Departed" : "已離站";
  } else if (diffMinutes === 0) {
    return currentLang === "en" ? "Arrived" : "已到達";
  } else if (diffMinutes < 60) {
    return currentLang === "en" ? `${diffMinutes} min` : `${diffMinutes} 分鐘`;
  } else {
    return etaDate.toLocaleTimeString();
  }
}

async function loadRoutes() {
  const res = await fetch(`${baseUrl}/bus/routes`);
  const data = await res.json();
  Object.keys(routeMap).forEach(k => delete routeMap[k]);

  (data.data || []).forEach(route => {
    const key = `${route.route}-${route.bound}-${route.service_type}`;
    routeMap[key] = { ...route };

    // also create opposite direction if missing
    const opposite = route.bound === "O" ? "I" : "O";
    const oppKey = `${route.route}-${opposite}-${route.service_type}`;
    if (!routeMap[oppKey]) {
      routeMap[oppKey] = {
        route: route.route,
        bound: opposite,
        service_type: route.service_type,
        orig_en: route.dest_en,
        dest_en: route.orig_en,
        orig_tc: route.dest_tc,
        dest_tc: route.orig_tc
      };
    }
  });
}

function showRouteList(query) {
  const list = document.getElementById("routeList");
  list.innerHTML = "";

  let matches = Object.keys(routeMap)
    .filter(key => {
      const r = routeMap[key];
      if (r.bound !== "O") return false; // only outbound
      const label = currentLang === "en"
        ? `${r.route} - ${r.orig_en} → ${r.dest_en}`
        : `${r.route} - ${r.orig_tc} → ${r.dest_tc}`;
      return !query || label.toLowerCase().includes(query.toLowerCase());
    })
    .slice(0, 5);

  matches.forEach(key => {
    const r = routeMap[key];
    const label = currentLang === "en"
      ? `${r.route} - ${r.orig_en} → ${r.dest_en}`
      : `${r.route} - ${r.orig_tc} → ${r.dest_tc}`;
    const li = document.createElement("li");
    li.textContent = label;
    li.addEventListener("click", () => {
      document.getElementById("routeInput").value = label;
      activeRouteLabel = key;
      currentDirection = r.bound;
      loadStops();
      startAutoRefresh();
    });
    list.appendChild(li);
  });
}
document.getElementById("routeInput").addEventListener("input", e => {
  showRouteList(e.target.value);
});

async function loadStops(boundOverride = null) {
  const label = activeRouteLabel;
  const stopsSection = document.getElementById("stopsSection");

  if (!label || !routeMap[label]) {
    stopsSection.classList.add("hidden");
    return;
  }

  let { route, bound, service_type, orig_en, dest_en, orig_tc, dest_tc } = routeMap[label];
  if (boundOverride) bound = boundOverride;
  currentDirection = bound;

  const boundWord = bound === "O" ? "outbound" : "inbound";
  const res = await fetch(`${baseUrl}/bus/route/${route}/${boundWord}/${service_type}/stops`);
  const data = await res.json();

  const stopList = document.getElementById("stopList");
  stopList.innerHTML = "";

  if (!data.data || data.data.length === 0) {
    stopsSection.classList.add("hidden");
    return;
  }

  stopsSection.classList.remove("hidden");

  document.getElementById("stopsLabel").textContent = dict[currentLang].stopsHeader;
  const routeLabel = currentLang === "en"
    ? `${route}: ${orig_en} → ${dest_en}`
    : `${route}: ${orig_tc} → ${dest_tc}`;
  document.getElementById("routeInfo").textContent = routeLabel;
  document.getElementById("directionToggle").textContent = `⟳ ${dict[currentLang].directionreverse}`;

  const sortedStops = data.data.slice().sort((a, b) => a.seq - b.seq);

  const enriched = await Promise.all(
    sortedStops.map(async (stop) => {
      try {
        const stopRes = await fetch(`${baseUrl}/bus/stop/${stop.stop}`);
        const stopData = await stopRes.json();
        const name_en = stopData.data?.name_en || "Unnamed Stop";
        const name_tc = stopData.data?.name_tc || "未命名車站";
        return { seq: stop.seq, stop: stop.stop, name_en, name_tc };
      } catch {
        return { seq: stop.seq, stop: stop.stop, name_en: "Unnamed Stop", name_tc: "未命名車站" };
      }
    })
  );

  for (const s of enriched) {
    const stopItem = document.createElement("div");
    stopItem.className = "stop-item";
    stopItem.dataset.stopId = s.stop;

    const nameSpan = document.createElement("span");
    nameSpan.className = "stop-name";
    nameSpan.textContent = `${s.seq}. ${currentLang === "en" ? s.name_en : s.name_tc}`;
    stopItem.appendChild(nameSpan);

    const details = document.createElement("div");
    details.className = "stop-details";
    stopItem.appendChild(details);

    stopItem.addEventListener("click", async () => {
      if (details.classList.contains("active")) {
        details.classList.remove("active");
        details.innerHTML = "";
        return;
      }

      const arrivalsRes = await fetch(`${baseUrl}/bus/stop/${s.stop}/arrivals`);
      const arrivalsData = await arrivalsRes.json();
      details.classList.add("active");
      details.innerHTML = "";

      if (!arrivalsData.data || arrivalsData.data.length === 0) {
        details.innerHTML = `<p>${dict[currentLang].noUpcoming}</p>`;
        return;
      }

      const grouped = {};
      arrivalsData.data.forEach(arrival => {
        const dir = arrival.dir ? arrival.dir.toUpperCase() : "";
        if (dir === currentDirection) {
          if (!grouped[arrival.route]) grouped[arrival.route] = [];
          grouped[arrival.route].push(arrival);
        }
      });

      if (grouped[route]) {
        const list = document.createElement("ul");
        list.className = "arrival-list";
        const header = document.createElement("h4");
        header.textContent = `${dict[currentLang].selectedRoute} (${route})`;
        details.appendChild(header);

        grouped[route]
          .sort((a, b) => new Date(a.eta || 0) - new Date(b.eta || 0))
          .slice(0, 3)
          .forEach(arrival => {
            list.appendChild(buildArrivalRow(arrival, s.stop)); // ✅ pass stopId
          });

        details.appendChild(list);
      }

      // Other routes arrivals (next bus only, same direction)
      const otherRoutes = Object.keys(grouped).filter(r => r !== route);
      if (otherRoutes.length > 0) {
        const otherHeader = document.createElement("h4");
        otherHeader.textContent = dict[currentLang].otherRoutes;
        details.appendChild(otherHeader);

        const otherList = document.createElement("ul");
        otherList.className = "arrival-list";

        otherRoutes.forEach(r => {
          grouped[r].sort((a, b) => new Date(a.eta || 0) - new Date(b.eta || 0));
          otherList.appendChild(buildArrivalRow(grouped[r][0], s.stop)); // ✅ pass stopId
        });

        details.appendChild(otherList);
      }
    });

    stopList.appendChild(stopItem);
  }
}

// Helper: build a 4-column row
function buildArrivalRow(arrival, stopId) {
  const dir = arrival.dir ? arrival.dir.toUpperCase() : "";
  const li = document.createElement("li");

  const routeSpan = document.createElement("span");
  routeSpan.className = "arrival-route";
  routeSpan.textContent = arrival.route;

  const destSpan = document.createElement("span");
  destSpan.className = "arrival-dest";
  destSpan.textContent = currentLang === "en" ? arrival.dest_en : arrival.dest_tc;

  const etaSpan = document.createElement("span");
  etaSpan.className = "arrival-eta";
  etaSpan.id = `eta-${arrival.route}-${stopId}-${dir}`;   // ✅ matches refresh
  etaSpan.textContent = arrival.eta ? formatETA(arrival.eta) : "";

  const remarkSpan = document.createElement("span");
  remarkSpan.className = "remark";
  remarkSpan.id = `remark-${arrival.route}-${stopId}-${dir}`; // ✅ matches refresh
  remarkSpan.textContent = arrival.eta
    ? (currentLang === "en" ? arrival.rmk_en : arrival.rmk_tc)
    : (currentLang === "en" ? "No scheduled bus" : "沒有班次");

  li.append(routeSpan, destSpan, etaSpan, remarkSpan);
  return li;
}

function searchRoute(route, service_type = null, bound = null) {
  const key = Object.keys(routeMap).find(k => {
    const r = routeMap[k];
    return r.route === route &&
      (service_type ? r.service_type === service_type : true) &&
      (bound ? r.bound === bound : true);
  });

  if (key) {
    const r = routeMap[key];
    const label = currentLang === "en"
      ? `${r.route} - ${r.orig_en} → ${r.dest_en}`
      : `${r.route} - ${r.orig_tc} → ${r.dest_tc}`;

    document.getElementById("routeInput").value = label;
    activeRouteLabel = key;
    currentDirection = r.bound;
    loadStops();
    startAutoRefresh();
  }
}

function changedirection() {
  if (!activeRouteLabel || !routeMap[activeRouteLabel]) return;

  const r = routeMap[activeRouteLabel];
  const newDirection = r.bound === "O" ? "I" : "O";

  const newKey = `${r.route}-${newDirection}-${r.service_type}`;
  const newEntry = routeMap[newKey];
  if (newEntry) {
    const newLabel = currentLang === "en"
      ? `${newEntry.route} - ${newEntry.orig_en} → ${newEntry.dest_en}`
      : `${newEntry.route} - ${newEntry.orig_tc} → ${newEntry.dest_tc}`;

    document.getElementById("routeInput").value = newLabel;
    activeRouteLabel = newKey;
    currentDirection = newDirection;
    loadStops();
    startAutoRefresh();
  } else {
    console.warn("No matching route entry found for direction", newDirection);
  }
}

async function refreshArrivals(stopId) {
  try {
    const arrivalsRes = await fetch(`${baseUrl}/bus/stop/${stopId}/arrivals`);
    const arrivalsData = await arrivalsRes.json();
    if (!arrivalsData.data) return;

    const details = document.querySelector(`[data-stop-id="${stopId}"] .stop-details`);
    if (!details || !details.classList.contains("active")) return;

    details.innerHTML = "";

    const grouped = {};
    arrivalsData.data.forEach(arrival => {
      const dir = arrival.dir ? arrival.dir.toUpperCase() : "";
      if (dir === currentDirection) {
        if (!grouped[arrival.route]) grouped[arrival.route] = [];
        grouped[arrival.route].push(arrival);
      }
    });

    const selectedRoute = activeRouteLabel?.split("-")[0];
    if (grouped[selectedRoute]) {
      const list = document.createElement("ul");
      list.className = "arrival-list";
      const header = document.createElement("h4");
      header.textContent = `${dict[currentLang].selectedRoute} (${selectedRoute})`;
      details.appendChild(header);

      grouped[selectedRoute]
        .sort((a, b) => new Date(a.eta || 0) - new Date(b.eta || 0))
        .slice(0, 3)
        .forEach(arrival => {
          list.appendChild(buildArrivalRow(arrival, stopId));
        });

      details.appendChild(list);
    }

    const otherRoutes = Object.keys(grouped).filter(r => r !== selectedRoute);
    if (otherRoutes.length > 0) {
      const otherHeader = document.createElement("h4");
      otherHeader.textContent = dict[currentLang].otherRoutes;
      details.appendChild(otherHeader);

      const otherList = document.createElement("ul");
      otherList.className = "arrival-list";

      otherRoutes.forEach(r => {
        grouped[r]
          .sort((a, b) => new Date(a.eta || 0) - new Date(b.eta || 0));
        otherList.appendChild(buildArrivalRow(grouped[r][0], stopId));
      });

      details.appendChild(otherList);
    }
  } catch (err) {
    console.error("Refresh failed for stop", stopId, err);
  }
}

function startAutoRefresh() {
  if (refreshTimer) clearInterval(refreshTimer);
  refreshTimer = setInterval(() => {
    document.querySelectorAll(".stop-details.active").forEach(details => {
      const stopId = details.parentElement.dataset.stopId;
      if (stopId) refreshArrivals(stopId);
    });
  }, 30000); // refresh every 30s
}

window.onload = () => {
  loadRoutes().then(() => {
    updateLanguage();
    showRouteList("");
  });

  const langSelect = document.getElementById("langSelect");
  if (langSelect) {
    langSelect.addEventListener("change", e => {
      currentLang = e.target.value;
      updateLanguage();
      loadRoutes().then(() => {
        showRouteList("");
      });
      if (activeRouteLabel) loadStops();
      startAutoRefresh();
    });
  }
};