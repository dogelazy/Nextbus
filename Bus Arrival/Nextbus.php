<?php
session_start();
//declare variable for filtering company name
$space=" ";
$KMB_eng="KMB";
$CTB_eng="CTB";
$KMB_tc="九巴";
$CTB_tc="城巴";
// check language is get and save in session
if (isset($_GET['lang']) && ($_GET['lang'] == 'tc' || $_GET['lang'] == 'en')) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'en';

// define texts and field names in tc/en
if ($lang == 'tc') {
    $pageTitle       = "下一班巴士";
    $busRouteLabel   = "巴士路線:";
    $directionLabel  = "方向:";
    $directionInbound  = "回程";
    $directionOutbound = "去程";
    $searchLabel     = "查詢";
    $fromText        = "由";
    $toText          = "到";
    $minute          = "分鐘";
    $Noschedule      = "暫時沒有預訂班次";
    $KMB             = "九巴";
    $CTB             = "城巴";
    $changelog       = "變更日誌";
} else {
    $pageTitle       = "Next bus";
    $busRouteLabel   = "Bus Route:";
    $directionLabel  = "Direction:";
    $directionInbound  = "Inbound";
    $directionOutbound = "Outbound";
    $searchLabel     = "search";
    $fromText        = "From";
    $toText          = "to";
    $minute          = "minute";
    $Noschedule      = "Temporarily no scheduled bus";
    $KMB             = "KMB";
    $CTB             = "CTB";
    $changelog       = "changelog";
}

// set API with tc/en
$orig_field = ($lang == 'tc') ? 'orig_tc' : 'orig_en';
$dest_field = ($lang == 'tc') ? 'dest_tc' : 'dest_en';
$name_field = ($lang == 'tc') ? 'name_tc' : 'name_en';
$rmk_field  = ($lang == 'tc') ? 'rmk_tc'  : 'rmk_en';

//set time zone
date_default_timezone_set('Asia/Hong_Kong');

// //preload route
$routeurl      = 'https://data.etabus.gov.hk/v1/transport/kmb/route/';
$route_content = file_get_contents($routeurl);
$routeobj      = json_decode($route_content);
$routedata     = $routeobj->data;
$ctburl      ='https://rt.data.gov.hk/v2/transport/citybus/route/ctb';
$ctbroute_content = file_get_contents($ctburl);
$ctbrouteobj      = json_decode($ctbroute_content);
$ctbroutedata     = $ctbrouteobj->data;

// refresh 60s
header("refresh:60");

// change language and save all existing get parameters
$currentParams = $_GET;
$currentParams['lang'] = 'en';
$queryStringEn = http_build_query($currentParams);

$currentParams['lang'] = 'tc';
$queryStringTC = http_build_query($currentParams);
?>

<html lang="<?php echo $lang; ?>">
<head>
  <title><?php echo $pageTitle; ?></title>
  <link rel="stylesheet" href="busstyle.css">
  <link rel="icon" type="image/x-icon" href="icon.png">
</head>
<body>
  <!-- language button save search parameters -->
  <div class="head">
    <br>
    <a href="?<?php echo $queryStringEn; ?>">English</a> | 
    <a href="?<?php echo $queryStringTC; ?>">繁體中文</a> |
    <a href="changelog.html"><?php echo $changelog?></a>
    <img src="icon.png" align="right" id="icon" width=10% alt="icon">
    <br>
    <br>
    <br>


  <h1><?php echo $pageTitle; ?></h1>
  </div>
  <!-- form -->
  <form action="" method="get">
    <?php 
      // save chosen language
      echo "<input type='hidden' name='lang' value='" . $lang . "'>";
    ?>
    <?php echo $busRouteLabel; ?> 
    <input list="route" name="route" value="<?php echo isset($_GET['route']) ? htmlspecialchars($_GET['route']) : ''; ?>">
    <datalist id="route">
      <?php
      foreach ($routedata as $x) {
          if($x->bound=="I" && $x->service_type=="1"){
          echo "<option value='" . $x->route." ".$KMB."'>";
          }
      }
      ?>
    </datalist>
    <?php echo $directionLabel; ?>
    <select name="direction">
      <option value="inbound" <?php if(isset($_GET['direction']) && $_GET['direction']=="inbound") echo "selected"; ?>>
        <?php echo $directionInbound; ?>
      </option>
      <option value="outbound" <?php if(!isset($_GET['direction']) || $_GET['direction']=="outbound") echo "selected"; ?>>
        <?php echo $directionOutbound; ?>
      </option>
    </select>
    <br>
    <input type="submit" class="btn" value="<?php echo $searchLabel; ?>">
  </form>

<?php
if (isset($_GET['route']) && !empty($_GET['route'])) {

  switch ($_GET['route']){
    case "KY Chan":
      echo "<img src='https://i.ibb.co/hxtQF7xm/IMG-2006.jpg' alt = 'KY Chan is angry'>";
      return;
    case "Bus Chan":
      echo "<img src='https://i.ibb.co/hxtQF7xm/IMG-2006.jpg' alt = 'KY Chan is angry'>";
      return;
    case "CS Chan":
      echo "<img src='https://i.ibb.co/hxtQF7xm/IMG-2006.jpg' alt = 'KY Chan is angry'>";
      return;
  }
  //deleting company name
  if (str_contains($_GET['route'], $space . $KMB_tc)) {
    $route = trim(str_replace($KMB_tc, "", $_GET['route']));
  } elseif (str_contains($_GET['route'], $space . $CTB_tc)) {
    $route = trim(str_replace($CTB_tc, "", $_GET['route']));
  } elseif (str_contains($_GET['route'], $space . $KMB_eng)) {
    $route = trim(str_replace($KMB_eng, "", $_GET['route']));
  } elseif (str_contains($_GET['route'], $space . $CTB_eng)) {
    $route = trim(str_replace($CTB_eng, "", $_GET['route']));
  } else {
    $route = $_GET['route'];
    }
    
    $direction = isset($_GET['direction']) ? $_GET['direction'] : 'outbound';

    // eta
    $etaurl = "https://data.etabus.gov.hk/v1/transport/kmb/route-eta/" . $route . "/1";

    // direction 
    $Sdirection = ($direction == "outbound") ? "O" : "I";

    $type = 1;
    $url = 'https://data.etabus.gov.hk/v1/transport/kmb/route/';
    $url1 = 'https://data.etabus.gov.hk/v1/transport/kmb/route-stop/';
    $findurl = $url . $route . "/" . $direction . "/" . $type;
    $findurlstop = $url1 . $route . "/" . $direction . "/" . $type;
    $content = file_get_contents($findurl);
    $obj = json_decode($content);

    if ($direction=="inbound" && (!isset($obj->data->route))) {
        $direction = "outbound";
        $Sdirection = "O";
        $findurl = $url . $route . "/" . $direction . "/" . $type;
        $findurlstop = $url1 . $route . "/" . $direction . "/" . $type;
        $content = file_get_contents($findurl);
        $obj = json_decode($content);
    }
        if(empty($obj->data->route)){
          echo "<span class='nobus'>Invalid route input! Please check is route letter upper case or is the data input a valid route.</span>";
          die();
        }
    // display route
    echo "<h1>" . $obj->data->route . "</h1>";
    echo "<h2>" . $fromText . " " . $obj->data->$orig_field . " " . $toText . " " . $obj->data->$dest_field . "</h2><br>";

    // display stop information.
    $stopcontent = file_get_contents($findurlstop);
    $obj1 = json_decode($stopcontent);
    $data = $obj1->data;
    $objcontent = file_get_contents($etaurl);
    $objeta = json_decode($objcontent);
    $etadata = $objeta->data;
    foreach ($data as $y) {
        // get stop
        $urlstop = "https://data.etabus.gov.hk/v1/transport/kmb/stop/" . $y->stop;
        $contentstop = file_get_contents($urlstop);
        $stopobj = json_decode($contentstop);
        $stopName = $stopobj->data->$name_field;
        echo "<h3><div class='station'>" . $stopName . "</div></h3>";
  
        // find ETA results and display it
        foreach ($etadata as $x) {
                $no=false;
            if ($x->seq == $y->seq) {
                $etatime = $x->eta;
                if($etatime==null){
                  echo "<span class='nobus'>".$Noschedule."</span><br>";
                  $no=true;
                }
                $now = date(DATE_ATOM);
                $etatime1 = new DateTime($etatime);
                $now1 = new DateTime($now);
                $eta = $etatime1->diff($now1);
                if($no !== true){
                echo "<span class='eta'>".$eta->format("%i $minute") . "</span> " . "<span class='rmk'>".$x->$rmk_field . "</span><br>";
                }
            }
        }
      }
  
}


?>
</div>
</body>
</html>
