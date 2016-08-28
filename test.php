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
    <input name="password" type="password" />
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
  <style>
    .ovrl {background-color: gray;}
    .stable {background-color: lightgray;}
  </style>
</head>
<body>
<?php

// From PHP.net by user mitgath@gmail.com
function mb_str_pad ($input, $pad_length, $pad_string, $pad_style,
    $encoding="UTF-8") {
  return str_pad(
    $input,
    strlen($input) - mb_strlen($input,$encoding) + $pad_length,
    $pad_string,
    $pad_style
  );
}

function print_timetable($timetable, $captions, $cycle) {
  echo "  <b>" . date("j.n.Y", $cycle["mondayDate"]) . " - " .
    date("j.n.Y", strtotime("this week friday", $cycle["mondayDate"])) .
    "</b>\n";

  $t = array();
  $days = ["Po", "Út", "St", "Čt", "Pá", "So", "Ne"];
  foreach($timetable as $lesson) {
    $row = 0;
    foreach($days as $id => $day) {
      if($day == $lesson["day"]) {
        $row = $id;
        break;
      }
    }

    $column = 0;
    foreach($captions as $id => $caption) {
      if($caption["caption"] == $lesson["caption"]) {
        $column = $id;
        break;
      }
    }

    if(!isset($t[$row])) {
      $t[$row] = array();
    }

    $t[$row][$column] = $lesson;
  }

  echo "  /-----";
  //    | Po |
  foreach ($captions as $caption) {
    echo "---------------";
  }

  echo "\\\n";
  echo "  |    |";
  foreach ($captions as $caption) {
    echo mb_str_pad($caption["caption"], 15, " ", STR_PAD_BOTH);
  }
  echo "|\n";
  echo "  |    |";
  foreach ($captions as $caption) {
    echo str_pad(
      $caption["begin"] . "-" . $caption["end"],
      15, " ", STR_PAD_BOTH
    );
  }
  echo "|\n";

  echo "  |----|";
  foreach ($captions as $caption) {
    echo "---------------";
  }
  echo "|\n";

  $first = true;
  foreach($t as $dayId => $day) {
    if(!$first) {
      echo "  |    |";
      foreach ($captions as $caption) {
        echo "               "; // 15 spaces
      }
      echo "|\n";
    }
    $first = false;
    echo "  |    |";
    foreach($captions as $id => $caption) {
      if(!isset($day[$id])) {
        echo "               "; // 15 spaces
      } else {
        echo " <b>";
        if(isset($day[$id]["overlay"])) echo "<span class=\"ovrl\">";
        else echo "<span class=\"stable\">";
        echo mb_str_pad($day[$id]["short"], 13, " ", STR_PAD_BOTH);
        echo "</span>";
        echo "</b> ";
      }
    }
    echo "|\n";
    echo "  |" . mb_str_pad($days[$dayId], 4, " ", STR_PAD_BOTH) . "|";
    foreach($captions as $id => $caption) {
      if(!isset($day[$id])) {
        echo "               "; // 15 spaces
      } else {
        echo " <i>";
        if(isset($day[$id]["overlay"])) echo "<span class=\"ovrl\">";
        else echo "<span class=\"stable\">";
        echo mb_str_pad($day[$id]["steacher"], 13, " ", STR_PAD_BOTH);
        echo "</span>";
        echo "</i> ";
      }
    }
    echo "|\n";
    echo "  |    |";
    foreach($captions as $id => $caption) {
      if(!isset($day[$id])) {
        echo "               "; // 15 spaces
      } else {
        echo " ";
        if(isset($day[$id]["overlay"])) echo "<span class=\"ovrl\">";
        else echo "<span class=\"stable\">";
        echo " ";
        echo mb_str_pad($day[$id]["shortRoom"], 5, " ", STR_PAD_RIGHT);
        echo " ";
        echo mb_str_pad($day[$id]["shortGroup"], 5, " ", STR_PAD_LEFT);
        echo " ";
        echo "</span>";
        echo " ";
      }
    }
    echo "|\n";
  }
  echo "  \\-----";
  foreach ($captions as $caption) {
    echo "---------------";
  }
  echo "/\n";
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre>";
\Markaos\BakAPI\Log::i(basename(__FILE__), "Running " . basename(__FILE__) . "...");
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
          echo "  <b><i>" . date("d.m.Y", $event["date"]) . "</i>  " .
            $event["name"] . "</b>: " . $event["desc"] . "\n";
          $tmp[2]++;
        }
      } else {
        echo "  <b><i>" . date("d.m.Y", $event["date"]) . "</i>  " .
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
    echo "Fetching timetable...\n";
    flush();
    $arr = array();
    $arr["stable"] = $client->load(BAKAPI_SECTION_TIMETABLE_STABLE);
    echo "  Permanent timetable loaded...\n";
    flush();
    $arr["captions"] = $client->load(BAKAPI_SECTION_TIMETABLE_CAPTIONS);
    echo "  Captions too...\n";
    flush();
    $arr["actual"] = $client->load(BAKAPI_SECTION_TIMETABLE_OVERLAY);
    echo "  Overlay ready, loading cycles...\n";
    flush();
    $arr["cycles"] = $client->load(BAKAPI_SECTION_TIMETABLE_CYCLES);
    echo "  Finishing it up...\n";
    echo "\n\n";
    flush();

    $cycles = $arr["cycles"][BAKAPI_SECTION_TIMETABLE_CYCLES];
    $captions = $arr["captions"][BAKAPI_SECTION_TIMETABLE_CAPTIONS];
    $stable = $arr["stable"][BAKAPI_SECTION_TIMETABLE_STABLE];
    $overlay = $arr["actual"][BAKAPI_SECTION_TIMETABLE_OVERLAY];
    $merged = $stable;

    $cycle = $cycles[0]["cycle"];
    foreach($merged as $key => $lesson) {
      if(isset($lesson["cycle"]) && $lesson["cycle"] != "" &&
          $lesson["cycle"] != $cycle) {
        unset($merged[$key]);
      }
    }

    foreach($overlay as $ovrl) {
      if($ovrl["date"] < $cycles[0]["mondayDate"] || $ovrl["date"] >= $cycles[1]["mondayDate"])
        continue;

      $ovrl["overlay"] = true;
      $id = \Markaos\BakAPI\Util::getLessonIndexes($merged, $ovrl["day"], $ovrl["caption"]);
      if(count($id) == 0) {
        if($ovrl["type"] == "X") {
          // We are replacing non-existent lesson with empty lesson - something
          // surely went wrong in the client
          continue;
        }
        $merged[] = $ovrl;
        continue;
      }
      $merged[$id[0]] = $ovrl;
    }

    print_timetable($merged, $captions, $cycles[0]);
    echo "\n\n";

    $merged = $stable;
    $cycle = $cycles[1]["cycle"];
    foreach($merged as $key => $lesson) {
      if(isset($lesson["cycle"]) && $lesson["cycle"] != "" &&
          $lesson["cycle"] != $cycle) {
        unset($merged[$key]);
      }
    }

    foreach($overlay as $ovrl) {
      if($ovrl["date"] < $cycles[1]["mondayDate"] || $ovrl["date"] >= $cycles[2]["mondayDate"])
        continue;

      $ovrl["overlay"] = true;
      $id = \Markaos\BakAPI\Util::getLessonIndexes($merged, $ovrl["day"], $ovrl["caption"]);
      if(count($id) == 0) {
        $merged[] = $ovrl;
        continue;
      }
      $merged[$id[0]] = $ovrl;
    }

    print_timetable($merged, $captions, $cycles[1]);

  } else {
    \Markaos\BakAPI\Log::w(basename(__FILE__), "Login failed");
    echo "Login failed\n";
    flush();
  }
} else {
  \Markaos\BakAPI\Log::w(basename(__FILE__), "Server not available");
  echo "Server not available\n";
  flush();
}

echo "\n";
echo "Peak memory usage: " . ceil((memory_get_peak_usage() / 1024)) . " kB\n";
echo "</pre>\n";
?>
</body>
</html>
