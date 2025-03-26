<?php
//last bus will be given a boolean value "true" for 30 minute then reset to false
session_start();
$foundETA = false;
$Islastbus = false;
// Declare variables for filtering company names
$KMB_eng = "KMB";
$CTB_eng = "CTB";
$KMB_tc = "九巴";
$CTB_tc = "城巴";

// Check if language is set and save it in the session
if (isset($_GET['lang']) && in_array($_GET['lang'], ['tc', 'en'])) {
  $_SESSION['lang'] = $_GET['lang'];
}
$lang = $_SESSION['lang'] ?? 'en';

// Define texts and field names for tc/en
$texts = [
  'tc' => [
    'pageTitle' => "下一班巴士",
    'busRouteLabel' => "巴士路線:",
    'searchLabel' => "查詢",
    'fromText' => "由",
    'toText' => "往",
    'minute' => "分鐘",
    'Noschedule' => "暫時沒有預定班次",
    'KMB' => "九巴",
    'CTB' => "城巴",
    'changelog' => "變更日誌",
    'arriving' => "到達"
  ],
  'en' => [
    'pageTitle' => "Next bus",
    'busRouteLabel' => "Bus Route:",
    'searchLabel' => "search",
    'fromText' => "From",
    'toText' => "to",
    'minute' => "minute",
    'Noschedule' => "Temporarily no scheduled bus",
    'KMB' => "KMB",
    'CTB' => "CTB",
    'changelog' => "changelog",
    'arriving' => "Arriving"
  ]
];

$selectedTexts = $texts[$lang];

// Set API field names based on the selected language
$orig_field = $lang == 'tc' ? 'orig_tc' : 'orig_en';
$dest_field = $lang == 'tc' ? 'dest_tc' : 'dest_en';
$name_field = $lang == 'tc' ? 'name_tc' : 'name_en';
$rmk_field = $lang == 'tc' ? 'rmk_tc' : 'rmk_en';

// Set the time zone to HK
date_default_timezone_set('Asia/Hong_Kong');

// Preload KMB route data
$routeurl = 'https://data.etabus.gov.hk/v1/transport/kmb/route/';
$routedata = json_decode(file_get_contents($routeurl))->data;

// Preload CTB route data
$ctburl = 'https://rt.data.gov.hk/v2/transport/citybus/route/ctb';
$ctbroutedata = json_decode(file_get_contents($ctburl))->data;

// Refresh the page every 60s
header("refresh:60");

// Build query strings for language switching while retaining other parameters
$currentParams = $_GET;
$currentParams['lang'] = 'en';
$queryStringEn = http_build_query($currentParams);
$currentParams['lang'] = 'tc';
$queryStringTC = http_build_query($currentParams);
?>

<html lang="<?php echo $lang; ?>">

<head>
  <title><?php echo $selectedTexts['pageTitle']; ?></title>
  <link rel="stylesheet" href="style.css">
  <link rel="icon" type="image/x-icon" href="icon.png">
</head>

<body>
  <!-- Language switching -->
  <?php
  $i = rand(1, 2);
  echo $i == 2 ? "<div class='head1'>" : "<div class='head'>";
  ?>
  
  <br><br><br><br><br>
  <h1>
    <font color="white"><?php echo $selectedTexts['pageTitle']; ?></font>
  </h1>
  <div class="headbtn">
    <a href="?<?php echo $queryStringEn; ?>">English</a> |
    <a href="?<?php echo $queryStringTC; ?>">繁體中文</a> |
    <a href="changelog.html"><?php echo $selectedTexts['changelog']; ?></a>
  </div>
  <hr id="line" width="110%" color="white" />
  
  <!-- Form for bus route search -->
  <div class="finder">
    <form action="" method="get">
      <input type="hidden" name="lang" value="<?php echo $lang; ?>">
      <?php echo $selectedTexts['busRouteLabel']; ?>
      <input list="route" name="route" value="<?php echo htmlspecialchars($_GET['route'] ?? ''); ?>" onkeydown="if(event.keyCode === 13){ event.preventDefault(); document.getElementById('searchbtn').click(); return false; }">
      <datalist id="route">
        <?php
        foreach ($routedata as $x) {
          if ($x->service_type == "1") {
            if (strpos($x->dest_en, "CIRCULAR") !== false || strpos($x->dest_tc, "循環線") !== false) {
              $directionShort = $lang == 'tc' ? "循環線" : "circular";
            } else {
              $directionShort = strtoupper($x->bound) == "O" ? ($lang == 'tc' ? "回程" : "outbound") : ($lang == 'tc' ? "去程" : "inbound");
            }
            $displayText = $x->route . " " . $selectedTexts['KMB'] . " " . ($lang == 'tc' ? "往" : "to") . " " . ($lang == 'tc' ? $x->dest_tc : $x->dest_en);
            echo "<option value='" . $x->route . " | " . $directionShort . "'>" . $displayText . "</option>";
          }
        }
        ?>
      </datalist>
      <input type="submit" class="btn" id="searchbtn" value="<?php echo $selectedTexts['searchLabel']; ?>">
    </form>
  </div>
  
  <br><br><br><br>

  <?php
  if (isset($_GET['route']) && !empty(trim($_GET['route']))) {
    $userInput = trim($_GET['route']);
    
    // Convert small English letters to capital letters for route search
    // Handle Bus Chan inputs
    $specialInputs = ["KY Chan", "Bus Chan", "CS Chan", "巴士陳"];
    if (in_array($userInput, $specialInputs, true)) {
      echo "<img src='https://i.ibb.co/hxtQF7xm/IMG-2006.jpg' alt='KY Chan is angry'>";
      echo "<center><img src='icon.png' width='10%' alt='logo'><div class='pageend'>By ©2024-2025 CSKLSC ICT F.4 student</div></center>";
      return;
    } else {
      $userInput = strtoupper($userInput);
    }

    // Split user input into route and direction`
    $parts = explode("|", $userInput);
    $route = trim($parts[0]);
    $dirShort = trim($parts[1] ?? ($lang == 'tc' ? "回程" : "o"));

    // Convert short direction to full name
    $direction = ($lang == 'tc' && $dirShort === "去程") || (strtolower($dirShort) === "inbound") ? "inbound" : "outbound";

    // Build API URL for bus ETA (arrival times)
    $etaurl = "https://data.etabus.gov.hk/v1/transport/kmb/route-eta/" . $route . "/1";

    // Build API URL for route details and stops
    $type = 1;
    $url = 'https://data.etabus.gov.hk/v1/transport/kmb/route/';
    $url1 = 'https://data.etabus.gov.hk/v1/transport/kmb/route-stop/';
    $findurl = $url . $route . "/" . $direction . "/" . $type;
    $findurlstop = $url1 . $route . "/" . $direction . "/" . $type;

    $content = file_get_contents($findurl);
    $obj = json_decode($content);

    // If no route data is returned, switch to the opposite direction
    if (empty($obj->data->route)) {
      $direction = $direction === "inbound" ? "outbound" : "inbound";
      $findurl = $url . $route . "/" . $direction . "/" . $type;
      $findurlstop = $url1 . $route . "/" . $direction . "/" . $type;
      $content = file_get_contents($findurl);
      $obj = json_decode($content);
    }
    if (empty($obj->data->route)) {
      echo "<span class='nobus'>" . ($lang == 'tc' ? "無效的路線！請確保你輸入了有效的路線。" : "Invalid route input! Please ensure the route is valid.") . "</span>";
      die();
    }

    // Display route information
    echo "<h1>" . $obj->data->route . "</h1>";
    echo "<h2>" . $selectedTexts['fromText'] . " " . $obj->data->$orig_field . " " . $selectedTexts['toText'] . " " . $obj->data->$dest_field . "</h2><br>";

    // Load route-stop details
    $stopcontent = file_get_contents($findurlstop);
    $data = json_decode($stopcontent)->data;

    // Load ETA details
    $etadata = json_decode(file_get_contents($etaurl))->data;

    // Display bus stop name and ETA
    foreach ($data as $y) {
      $stopName = json_decode(file_get_contents("https://data.etabus.gov.hk/v1/transport/kmb/stop/" . $y->stop))->data->$name_field;
      echo "<h3 class='station' onclick='toggleETA(this)' data-stop-id='" . $y->stop . "'><div>" . $stopName . "</div></h3>";
      echo "<div class='eta-details' id='eta-" . $y->stop . "'>";

      // Find first 3 matching ETA records based on bus stop sequence
      $count = 0;
      foreach ($etadata as $x) {
        if ($x->seq == $y->seq) {
          $etatime = $x->eta;
          $rmk=$x->$rmk_field;
          $eta_diff = (new DateTime($etatime))->diff(new DateTime());
            if($eta_diff->format("%i")==0 && $etatime!=null){
              echo "<span class='eta'><span id='arriving'>".$selectedTexts['arriving']."</span></span> <span class='rmk'>" . $x->$rmk_field . "</span><br>";
              $foundETA = true;
            }elseif ($etatime!=null){
              $foundETA = true;
              echo "<span class='eta'>" . $eta_diff->format("%i " . $selectedTexts['minute']) . "</span> <span class='rmk'>" . $x->$rmk_field."</span><br>";
        }
          

          $count++;
      
          if ($count == 3) {
            break;
          }
        }
      }
      if (!$foundETA) {
        echo "<span class='nobus'>" . $selectedTexts['Noschedule'] . "</span><br>";
      }
      echo "</div>";
    }
  } else {
    echo "<center><img src='icon.png' width='10%' alt='logo'><div class='pageend'>By ©2024-2025 CSKLSC ICT F.4 student</div></center>";
    die();
  }
  
  ?>

  <center>
    <img src="icon.png" width="10%" alt="logo">
    <div class="pageend">By ©2024-2025 CSKLSC ICT F.4 student</div>
  </center>
  
  <script>
    //Save scroll position after refresh
    window.addEventListener("beforeunload", function() {
      sessionStorage.setItem("scrollPosition", window.scrollY);
    });
    window.addEventListener("load", function() {
      var scrollPos = sessionStorage.getItem("scrollPosition");
      if (scrollPos !== null) {
        window.scrollTo(0, parseInt(scrollPos));
      }
    });

    // Toggle the visibility of ETA
    function toggleETA(element) {
      var etaDetails = element.nextElementSibling;
      var stopId = element.getAttribute('data-stop-id');
      if (etaDetails.style.display === "none" || etaDetails.style.display === "") {
        etaDetails.style.display = "block";
        localStorage.setItem('eta-' + stopId, 'open');
      } else {
        etaDetails.style.display = "none";
        localStorage.setItem('eta-' + stopId, 'closed');
      }
    }

    // Restore the visibility state of ETA
    document.querySelectorAll('.station').forEach(function(station) {
      var stopId = station.getAttribute('data-stop-id');
      var etaDetails = document.getElementById('eta-' + stopId);
      if (localStorage.getItem('eta-' + stopId) === 'open') {
        etaDetails.style.display = 'block';
      }
    });
  </script>
</body>
</html>
