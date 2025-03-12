<?php
session_start();
$foundETA = false;
//declare variable for filtering company name
$KMB_eng = "KMB";
$CTB_eng = "CTB";
$KMB_tc = "九巴";
$CTB_tc = "城巴";

// check language is gotten and save in session
if (isset($_GET['lang']) && ($_GET['lang'] == 'tc' || $_GET['lang'] == 'en')) {
  $_SESSION['lang'] = $_GET['lang'];
}
$lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'en';

// define texts and field names in tc/en
if ($lang == 'tc') {
  $pageTitle = "下一班巴士";
  $busRouteLabel = "巴士路線:";
  $searchLabel = "查詢";
  $fromText = "由";
  $toText = "往";
  $minute = "分鐘";
  $Noschedule = "暫時沒有預定班次";
  $KMB = "九巴";
  $CTB = "城巴";
  $changelog = "變更日誌";
  $arriving = "即將到達";
} else {
  $pageTitle = "Next bus";
  $busRouteLabel = "Bus Route:";
  $searchLabel = "search";
  $fromText = "From";
  $toText = "to";
  $minute = "minute";
  $Noschedule = "Temporarily no scheduled bus";
  $KMB = "KMB";
  $CTB = "CTB";
  $changelog = "changelog";
  $arriving = "Arriving";
}

// set API with tc/en
$orig_field = ($lang == 'tc') ? 'orig_tc' : 'orig_en';
$dest_field = ($lang == 'tc') ? 'dest_tc' : 'dest_en';
$name_field = ($lang == 'tc') ? 'name_tc' : 'name_en';
$rmk_field = ($lang == 'tc') ? 'rmk_tc' : 'rmk_en';

//set time zone
date_default_timezone_set('Asia/Hong_Kong');

// preload KMB route data
$routeurl = 'https://data.etabus.gov.hk/v1/transport/kmb/route/';
$route_content = file_get_contents($routeurl);
$routeobj = json_decode($route_content);
$routedata = $routeobj->data;

// preload CTB route data
$ctburl = 'https://rt.data.gov.hk/v2/transport/citybus/route/ctb';
$ctbroute_content = file_get_contents($ctburl);
$ctbrouteobj = json_decode($ctbroute_content);
$ctbroutedata = $ctbrouteobj->data;

// refresh every 60s
// header("refresh:60");

// build query strings for language switching while getting parameters
$currentParams = $_GET;
$currentParams['lang'] = 'en';
$queryStringEn = http_build_query($currentParams);
$currentParams['lang'] = 'tc';
$queryStringTC = http_build_query($currentParams);
?>

<html lang="<?php echo $lang; ?>">

<head>
  <title><?php echo $pageTitle; ?></title>
  <link rel="stylesheet" href="style.css">
  <link rel="icon" type="image/x-icon" href="icon.png">
</head>

<body>
  <!-- language switching -->
  <?php
  $i = rand(1, 2);
  if ($i == 2) {
    echo "<div class='head1'>";
  } else {
    echo "<div class='head'>";
  }
  ?>

  <br><br><br><br><br>
  <h1>
    <font color="white"><?php echo $pageTitle; ?></font>
  </h1>
  <div class="headbtn"><a href="?<?php echo $queryStringEn; ?>">English</a> |
    <a href="?<?php echo $queryStringTC; ?>">繁體中文</a> |
    <a href="changelog.html"><?php echo $changelog ?></a>
  </div>
  <hr id="line" width="110%" color="white" />
  <!-- form -->
  <div class="finder">
    <form action="" method="get">
      <?php
      // save the selected language
      echo "<input type='hidden' name='lang' value='" . $lang . "'>";
      ?>
      <?php echo $busRouteLabel; ?>
      <!-- submit when click enter -->
      <input list="route" name="route"
        value="<?php echo isset($_GET['route']) ? htmlspecialchars($_GET['route']) : ''; ?>"
        onkeydown="if(event.keyCode === 13){ event.preventDefault(); document.getElementById('searchbtn').click(); return false; }">
      <datalist id="route">
        <?php
        // output a datalist option
        // The displayed text, eg:"1A KMB to SAU MAU PING (CENTRAL)"
        foreach ($routedata as $x) {
          if ($x->service_type == "1") {
            if ($lang == 'tc') {
              $directionShort = (strtoupper($x->bound) == "O") ? "回程" : "去程";
            } else {
              $directionShort = (strtoupper($x->bound) == "O") ? "outbound" : "inbound";
            }
            // display, eg:"1A 九巴 往 destination"
            $displayText = $x->route . " " . $KMB . " " . (($lang == 'tc') ? "往" : "to") . " " . (($lang == 'tc') ? $x->dest_tc : $x->dest_en);
            // option value encode both the route & direction
            echo "<option value='" . $x->route . " | " . $directionShort . "'>" . $displayText . "</option>";
          }
        }
        echo "  ";
        ?>
      </datalist>
      <input type="submit" class="btn" id="searchbtn" value="<?php echo $searchLabel; ?>">
  </div>

  </div>
  <br>
  <br>
  <br><br>

  <?php
  // if set then process the route.
  if (isset($_GET['route']) && !empty(trim($_GET['route']))) {
    $userInput = trim($_GET['route']);

    // Convert small eng letters to capital letters for route search
    // save 巴士陳 input
    if (
      strcasecmp($userInput, "KY Chan") == 0 ||
      strcasecmp($userInput, "Bus Chan") == 0 ||
      strcasecmp($userInput, "CS Chan") == 0 ||
      $userInput === "巴士陳"
    ) {
      if (strcasecmp($userInput, "KY Chan") == 0) {
        $userInput = "KY Chan";
      } elseif (strcasecmp($userInput, "Bus Chan") == 0) {
        $userInput = "Bus Chan";
      } elseif (strcasecmp($userInput, "CS Chan") == 0) {
        $userInput = "CS Chan";
      }
      // for Chinese input "巴士陳", no change is needed
    } else {
      $userInput = strtoupper($userInput);
    }

    // KY Chan
    switch ($userInput) {
      case "KY Chan":
        echo "<img src='https://i.ibb.co/hxtQF7xm/IMG-2006.jpg' alt='KY Chan is angry'>";
        echo "<center>
        <img src='icon.png' width='10%' alt='logo'>
        <div class='pageend'>By ©2024-2025 CSKLSC ICT F.4 student</div>
      </center>";
        return;
      case "Bus Chan":
        echo "<img src='https://i.ibb.co/hxtQF7xm/IMG-2006.jpg' alt='KY Chan is angry'>";
        echo "<center>
        <img src='icon.png' width='10%' alt='logo'>
        <div class='pageend'>By ©2024-2025 CSKLSC ICT F.4 student</div>
      </center>";
        return;
      case "CS Chan":
        echo "<img src='https://i.ibb.co/hxtQF7xm/IMG-2006.jpg' alt='KY Chan is angry'>";
        echo "<center>
        <img src='icon.png' width='10%' alt='logo'>
        <div class='pageend'>By ©2024-2025 CSKLSC ICT F.4 student</div>
      </center>";
        return;
      case "巴士陳":
        echo "<img src='https://i.ibb.co/hxtQF7xm/IMG-2006.jpg' alt='KY Chan is angry'>";
        echo "<center>
        <img src='icon.png' width='10%' alt='logo'>
        <div class='pageend'>By ©2024-2025 CSKLSC ICT F.4 student</div>
      </center>";
        return;
    }

    // form "Route|direction"
    $parts = explode("|", $userInput);
    if (count($parts) == 2) {
      $route = trim($parts[0]);
      $dirShort = trim($parts[1]);
    } else {
      $route = $userInput;
      $dirShort = ($lang == 'tc') ? "回程" : "o";
    }

    // convert the short direction in full name
    if ($lang == 'tc') {
      if ($dirShort === "去程") {
        $direction = "inbound";
      } else {
        $direction = "outbound";
      }
    } else {
      if (strtolower($dirShort) === "i") {
        $direction = "inbound";
      } else {
        $direction = "outbound";
      }
    }

    // build API URL for bus ETA (arrival times)
    $etaurl = "https://data.etabus.gov.hk/v1/transport/kmb/route-eta/" . $route . "/1";

    // build API URL for route details and stops
    $type = 1;
    $url = 'https://data.etabus.gov.hk/v1/transport/kmb/route/';
    $url1 = 'https://data.etabus.gov.hk/v1/transport/kmb/route-stop/';
    $findurl = $url . $route . "/" . $direction . "/" . $type;
    $findurlstop = $url1 . $route . "/" . $direction . "/" . $type;

    $content = file_get_contents($findurl);
    $obj = json_decode($content);

    // if no route data is returned then switch to the opposite direction
    if (empty($obj->data->route)) {
      if ($direction == "inbound") {
        $direction = "outbound";
      } else {
        $direction = "inbound";
      }
      $findurl = $url . $route . "/" . $direction . "/" . $type;
      $findurlstop = $url1 . $route . "/" . $direction . "/" . $type;
      $content = file_get_contents($findurl);
      $obj = json_decode($content);
    }
    if (empty($obj->data->route)) {
      if ($lang == 'tc') {
        echo "<span class='nobus'>無效的路線！請確保你輸入了有效的路線。</span>";
      } else {
        echo "<span class='nobus'>Invalid route input! Please ensure the route is valid.</span>";
      }
      die();
    }

    // Display route information
    echo "<h1>" . $obj->data->route . "</h1>";
    echo "<h2>" . $fromText . " " . $obj->data->$orig_field . " " . $toText . " " . $obj->data->$dest_field . "</h2><br>";

    // load route-stop details
    $stopcontent = file_get_contents($findurlstop);
    $obj1 = json_decode($stopcontent);
    $data = $obj1->data;
    //load ETA details
    $objcontent = file_get_contents($etaurl);
    $objeta = json_decode($objcontent);
    $etadata = $objeta->data;

    echo "<div class='route' id='$route'>" . "</div>";
    // display bus stop name and ETA
    foreach ($data as $y) {
      // get bus stop details from the stop API.
      // $urlstop = "https://data.etabus.gov.hk/v1/transport/kmb/stop/" . $y->stop;
      // $contentstop = file_get_contents($urlstop);
      // $stopobj = json_decode($contentstop);
      // $stopName = $stopobj->data->$name_field;
      // echo "<h3><div class='station'>" . $stopName . "</div></h3>";
  
      // create div with class station and id stop id
      echo "<h3><div class='station' id='stop[" . $y->stop . "]'>" . "</div></h3>";

      // div class eta
      echo "<div class='eta' id='eta[" . $y->seq . "]'>" . "</div>";


      // find for matching ETA records based on bus stop sequence
      // foreach ($etadata as $x) {
      //   if ($x->seq == $y->seq) {
      //     $etatime = $x->eta;
      //     if ($etatime == null) {
      //       echo "<span class='nobus'>" . $Noschedule . "</span><br>";
      //     } else {
      //       $now = date(DATE_ATOM);
      //       $etatime1 = new DateTime($etatime);
      //       $now1 = new DateTime($now);
      //       $eta_diff = $etatime1->diff($now1);
  
      //       echo "<span class='eta'>" . $eta_diff->format("%i $minute") . "</span> " . "<span class='rmk'>" . $x->$rmk_field . "</span><br>";
      //     }
      //     $foundETA = true;
      //   }
      // }
    }
  } else {
    echo "<center>
    <img src='icon.png' width='10%' alt='logo'>
    <div class='pageend'>By ©2024-2025 CSKLSC ICT F.4 student</div>
  </center>";
    die();
  }
  ?>

  <center>
    <img src="icon.png" width="10%" alt="logo">
    <div class="pageend">By ©2024-2025 CSKLSC ICT F.4 student</div>
  </center>

  <!-- save scroll position after refresh -->
  <script>
    window.addEventListener("beforeunload", function () {
      sessionStorage.setItem("scrollPosition", window.scrollY);
    });
    window.addEventListener("load", function () {
      var scrollPos = sessionStorage.getItem("scrollPosition");
      if (scrollPos !== null) {
        window.scrollTo(0, parseInt(scrollPos));
      }
    });


    // function getStopName(id)
    function getStopName(id) {
      // download and parse json "https://data.etabus.gov.hk/v1/transport/kmb/stop/id"
      var url = "https://data.etabus.gov.hk/v1/transport/kmb/stop/" + id;
      var xhr = new XMLHttpRequest();
      xhr.open("GET", url, true);
      xhr.send();
      xhr.onreadystatechange = function () {
        if (xhr.readyState == 4 && xhr.status == 200) {
          var stopObj = JSON.parse(xhr.responseText);
          document.getElementById("stop[" + id + "]").innerHTML = stopObj.data.<?php echo $name_field ?>;
        }
      };
    }

    function getETA(id) {
      // find class "route" in document
      var route = document.getElementsByClassName("route");
      // get the route number
      var routeNum = route[0].id;

      // download and parse json "https://data.etabus.gov.hk/v1/transport/kmb/route-eta/routeNum/1"
      var url = "https://data.etabus.gov.hk/v1/transport/kmb/route-eta/" + routeNum + "/1";
      // alert(url);
      var xhr = new XMLHttpRequest();
      xhr.open("GET", url, true);
      xhr.send();
      xhr.onreadystatechange = function () {
        if (xhr.readyState == 4 && xhr.status == 200) {
          var etaObj = JSON.parse(xhr.responseText);
          var etaData = etaObj.data;

          // alert number of data in etaData
          // alert(etaData.length);
          // for each etcData, get seq and eta_seq
          for (var i = 0; i < etaData.length; i++) {
            var seq = etaData[i].seq;
            var eta = etaData[i].eta;
            var rmk = etaData[i].rmk_tc;
            // get the div with id eta[seq] and append the content
            // alert(seq + " " + eta + " " + rmk);
            if (eta !== null) {
              var now = new Date();
              var etaTime = new Date(eta);
              var etaDiff = Math.round((etaTime - now) / 60000);
              var etadiff = etaDiff;
              if (etadiff < 1) {
                eta ="<span class='arriving'><?php echo $arriving; ?></span>";
              } else {
                eta = etadiff + " " + "<?php echo $minute; ?>";
              }
              // append
              document.getElementById("eta[" + seq + "]").innerHTML = document.getElementById("eta[" + seq + "]").innerHTML + eta + " " + "<span class='rmk'>" + rmk + "</span><br>";
            } else {
                document.getElementById("eta[" + seq + "]").innerHTML = document.getElementById("eta[" + seq + "]").innerHTML + "<span class = 'nobus'><?php echo $Noschedule ?></span><br>";
            }


          }

        }

      }
    };
    // on finish loading, update div id stop[i] with the bus stop name
    window.onload = function () {
        var stops = document.getElementsByClassName("station");
        for (var i = 0; i < stops.length; i++) {
          var stopIndex = stops[i].id.split("[")[1].split("]")[0];
          var stopName = document.getElementById("stop[" + stopIndex + "]");
          // get StopName(stopIndex) and update the div content
          getStopName(stopIndex);

          // get ETA(stopIndex) and update the div content
        }

        getETA(stopIndex);
      };

  </script>

</body>

</html>
