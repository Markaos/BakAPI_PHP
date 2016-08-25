<?php
include("bakapi.php");

if(!isset($_GET["formok"])) {
  ?>
  <!DOCTYPE html>
  <html>
  <head>
    <meta charset="UTF-8" />
    <title>BakAPI</title>
  </head>
  <body>
  <form>
    <label for="server">Server:</label>
    <input name="server" value="https://bakalari.gfpvm.cz" />
    <br>
    <label for="name">Jméno:</label>
    <input name="name" />
    <br>
    <label for="password">Heslo:</label>
    <input name="password" />
    <input type="hidden" name="formok" value="y" />
    <br>
    <input type="submit" value="Odeslat" />
  </form>
</body>
</html>
  <?php
  exit(0);
}

if (ob_get_level())
   ob_end_clean();

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <title>BakAPI</title>
</head>
<body>
<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre>";
echo "Checking settings...\n";
$settings = \Markaos\BakAPI\Util::getSettings();
echo $settings["test"] . "\n";
echo "Checking database...\n";
$db = \Markaos\BakAPI\Util::getDatabase();
echo "Using " . get_class($db) . "\n";
echo "Checking server...\n";
flush();
$client = \Markaos\BakAPI\BakAPI::checkServer($_GET["server"]);
if($client != NULL) {
  echo "It works! (using " . get_class($client) . ")\n";
  flush();
  $res = $client->connect($_GET["name"], $_GET["password"]);
  if($res !== false) {
    echo "Login successful!\n";
    echo "<b>Name</b>: " . $res["name"] . "\n";
    echo "<b>Class</b>: " . $res["class"] . "\n";
    echo "<b>Server version</b>: " . $res["version"] . "\n";
    echo "\n";
    echo "Fetching subjects...\n";
    flush();
    $arr = $client->load(BAKAPI_SECTION_SUBJECTS);
    foreach($arr[BAKAPI_SECTION_SUBJECTS] as $subject) {
      echo "  <b>" . $subject["name"] . "</b> (" . $subject["teacher"] . " - " .
        $subject["teacherEmail"] . ")\n";
    }

    echo "Fetching events...\n";
    flush();
    $arr = $client->load(BAKAPI_SECTION_EVENTS);
    $tmp = array(0, 0);
    foreach($arr[BAKAPI_SECTION_EVENTS] as $event) {
      $tmp[0]++;
      if($event["show"] == 1) {
        $tmp[1]++;
      }
    }
    echo "  Found " . $tmp[0] . " events total, " . $tmp[1] .
      " of them for you. Showing first " .
      min($tmp[1] == 0 ? $tmp[0] : $tmp[1], 10) . "\n";
    if($tmp[1] == 0) {
      echo "  Note: showing events for the whole school, as you don't have any\n";
    }
    $tmp[2] = 0;
    foreach($arr[BAKAPI_SECTION_EVENTS] as $event) {
      if($tmp[2] >= 10) break;
      if($tmp[1] > 0) {
        if($event["show"] == 1) {
          $date = date_create_from_format("Ymd", $event["date"]);
          echo "  <b><i>" . date_format($date, "d.m.Y") . "</i>  " .
            $event["name"] . "</b>: " . $event["desc"] . "\n";
          $tmp[2]++;
        }
      } else {
        $date = date_create_from_format("Ymd", $event["date"]);
        echo "  <b><i>" . date_format($date, "d.m.Y") . "</i>  " .
          $event["name"] . "</b>: " . $event["desc"] . "\n";
        $tmp[2]++;
      }
    }

    echo "Fetching grades...\n";
    flush();
    $arr = $client->load(BAKAPI_SECTION_GRADES);
    echo "  <b>Found " . count($arr[BAKAPI_SECTION_GRADES]) . " grades</b>\n";

    echo "Fetching messages...\n";
    flush();
    $arr = $client->load(BAKAPI_SECTION_MESSAGES);
    echo "  <b>Found " . count($arr[BAKAPI_SECTION_MESSAGES]) . " messages</b>\n";
    flush();
  } else {
    echo "Login failed\n";
    flush();
  }
} else {
  echo "Server not available\n";
  flush();
}

echo "</pre>\n";
?>
</body>
</html>
