<?php
session_start();
//declare variable for filtering company name
$KMB_eng = "KMB";
$CTB_eng = "CTB";
$KMB_tc  = "九巴";
$CTB_tc  = "城巴";

// check language is gotten and save in session
if (isset($_GET['lang']) && ($_GET['lang'] == 'tc' || $_GET['lang'] == 'en')) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'en';

// define texts and field names in tc/en
if ($lang == 'tc') {
    $pageTitle       = "下一班巴士";
    $busRouteLabel   = "巴士路線:";
    $searchLabel     = "查詢";
    $fromText        = "由";
    $toText          = "往"; // Changed from "到" to "往"
    $minute          = "分鐘";
    $Noschedule      = "暫時沒有預定班次";
    $KMB             = "九巴";
    $CTB             = "城巴";
    $changelog       = "變更日誌";
    $arriving        = "到達";
} else {
    $pageTitle       = "Next bus";
    $busRouteLabel   = "Bus Route:";
    $searchLabel     = "search";
    $fromText        = "From";
    $toText          = "to";
    $minute          = "minute";
    $Noschedule      = "Temporarily no scheduled bus";
    $KMB             = "KMB";
    $CTB             = "CTB";
    $changelog       = "changelog";
    $arriving        = "Arriving";
}

// set API with tc/en
$orig_field = ($lang == 'tc') ? 'orig_tc' : 'orig_en';
$dest_field = ($lang == 'tc') ? 'dest_tc' : 'dest_en';
$name_field = ($lang == 'tc') ? 'name_tc' : 'name_en';
$rmk_field  = ($lang == 'tc') ? 'rmk_tc'  : 'rmk_en';

//set time zone
date_default_timezone_set('Asia/Hong_Kong');

// preload KMB route data
$routeurl      = 'https://data.etabus.gov.hk/v1/transport/kmb/route/';
$route_content = file_get_contents($routeurl);
$routeobj      = json_decode($route_content);
$routedata     = $routeobj->data;

// preload CTB route data
$ctburl           = 'https://rt.data.gov.hk/v2/transport/citybus/route/ctb';
$ctbroute_content = file_get_contents($ctburl);
$ctbrouteobj      = json_decode($ctbroute_content);
$ctbroutedata     = $ctbrouteobj->data;

// refresh 60s
header("refresh:60");

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
  <div class="head">
    <br><br><br><br><br>
    <h1><font color="white"><?php echo $pageTitle; ?></font></h1>
    <div class="headbtn"><a href="?<?php echo $queryStringEn; ?>">English</a> | 
    <a href="?<?php echo $queryStringTC; ?>">繁體中文</a> |
    <a href="changelog.html"><?php echo $changelog?></a>
    </div>
  </div>
  <br>
  <div class="formarea">
  <!-- form -->
  <form action="" method="get"> 
    <?php 
      // save the selected language
      echo "<input type='hidden' name='lang' value='" . $lang . "'>";
    ?>
    <?php echo $busRouteLabel; ?> 
    <input list="route" name="route" value="<?php echo isset($_GET['route']) ? htmlspecialchars($_GET['route']) : ''; ?>">
    <datalist id="route">
      <?php
      // output a datalist option
      // The displayed text, eg:"1A KMB to SAU MAU PING (CENTRAL)"
      foreach ($routedata as $x) {
          if ($x->service_type == "1") {
              if ($lang == 'tc') {
                  $directionShort = (strtoupper($x->bound) == "O") ? "回程" : "去程";
              } else {
                  $directionShort = (strtoupper($x->bound) == "O") ? "outbound" : "inboud";
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
<input type="submit" class="btn" value="<?php echo $searchLabel; ?>"></div>

<?php
// if set then process the route.
if (isset($_GET['route']) && !empty(trim($_GET['route']))) {
    $userInput = trim($_GET['route']);
    
    // KY Chan
    switch ($userInput) {
        case "KY Chan":
          echo "<img src='https://i.ibb.co/hxtQF7xm/IMG-2006.jpg' alt='KY Chan is angry'>";
          return;
        case "Bus Chan":
          echo "<img src='https://i.ibb.co/hxtQF7xm/IMG-2006.jpg' alt='KY Chan is angry'>";
          return;
        case "CS Chan":
          echo "<img src='https://i.ibb.co/hxtQF7xm/IMG-2006.jpg' alt='KY Chan is angry'>";
          return;
    }

    // form "Route|direction"
    $parts = explode("|", $userInput);
    if (count($parts) == 2) {
        $route    = trim($parts[0]);
        $dirShort = trim($parts[1]);
    } else {
        $route    = $userInput;
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
    $type         = 1;
    $url          = 'https://data.etabus.gov.hk/v1/transport/kmb/route/';
    $url1         = 'https://data.etabus.gov.hk/v1/transport/kmb/route-stop/';
    $findurl      = $url . $route . "/" . $direction . "/" . $type;
    $findurlstop  = $url1 . $route . "/" . $direction . "/" . $type;
    
    $content = file_get_contents($findurl);
    $obj     = json_decode($content);
    
    // if no route data is returned then switch to the opposite direction
    if (empty($obj->data->route)) {
        if ($direction == "inbound") {
            $direction = "outbound";
        } else {
            $direction = "inbound";
        }
        $findurl     = $url . $route . "/" . $direction . "/" . $type;
        $findurlstop = $url1 . $route . "/" . $direction . "/" . $type;
        $content     = file_get_contents($findurl);
        $obj         = json_decode($content);
    }
    if (empty($obj->data->route)) {
        echo "<span class='nobus'>Invalid route input! Please ensure the route is valid and check the case of letters if applicable.</span>";
        die();
    }
    
    // Display route information
    echo "<h1>" . $obj->data->route . "</h1>";
    echo "<h2>" . $fromText . " " . $obj->data->$orig_field . " " . $toText . " " . $obj->data->$dest_field . "</h2><br>";
    
    // load route-stop details.
    $stopcontent = file_get_contents($findurlstop);
    $obj1        = json_decode($stopcontent);
    $data        = $obj1->data;
    //load ETA details
    $objcontent  = file_get_contents($etaurl);
    $objeta      = json_decode($objcontent);
    $etadata     = $objeta->data;
    
    // display bus stop name and ETA
    foreach ($data as $y) {
        // get bus stop details from the stop API.
        $urlstop      = "https://data.etabus.gov.hk/v1/transport/kmb/stop/" . $y->stop;
        $contentstop  = file_get_contents($urlstop);
        $stopobj      = json_decode($contentstop);
        $stopName     = $stopobj->data->$name_field;
        echo "<h3><div class='station'>" . $stopName . "</div></h3>";
  
        // find for matching ETA records based on bus stop sequence
        $foundETA = false;
        foreach ($etadata as $x) {
            if ($x->seq == $y->seq) {
                $etatime = $x->eta;
                if ($etatime == null) {
                    echo "<span class='nobus'>".$Noschedule."</span><br>";
                } else {
                    $now       = date(DATE_ATOM);
                    $etatime1  = new DateTime($etatime);
                    $now1      = new DateTime($now);
                    $eta_diff  = $etatime1->diff($now1);
                    
                    echo "<span class='eta'>".$eta_diff->format("%i $minute") . "</span> " . "<span class='rmk'>".$x->$rmk_field . "</span><br>";
                }
                $foundETA = true;
            }
        }
        }
    }
        if (!$foundETA) {
            echo "<span class='nobus'>" . $Noschedule . "</span><br>";
        }
  
?>
<center>
  <img src="icon.png" width="10%" alt="logo">
  <div class="pageend">By ©2024-2025 CSKLSC ICT F.4 student</div>
</center>

</body>
</html>
