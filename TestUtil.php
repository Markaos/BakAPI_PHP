<?php
class TestUtil {
  public static function print_timetable($timetable, $captions, $cycle) {
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

  public static function merge_timetable($stable, $overlay, $cycle, $nextCycle) {
    $merged = $stable;
    foreach($merged as $key => $lesson) {
      if(isset($lesson["cycle"]) && $lesson["cycle"] != "" &&
          $lesson["cycle"] != $cycle["cycle"]) {
        unset($merged[$key]);
      }
    }

    foreach($overlay as $ovrl) {
      if($ovrl["date"] < $cycle["mondayDate"] || $ovrl["date"] >= $nextCycle["mondayDate"])
        continue;

      $ovrl["overlay"] = true;
      $id = \Markaos\BakAPI\Util::getLessonIndexes($merged, $ovrl["day"], $ovrl["caption"]);
      if(count($id) == 0) {
        if($ovrl["type"] == "X") {
          // We are replacing non-existent lesson with empty lesson - something
          // surely went wrong in the client
          \Markaos\BakAPI\Log::e("TestUtil",
            "Replacing non-existent lesson with empty lesson (caption: " .
            $ovrl["caption"] . "; day: " . $ovrl["day"] . ")");
          continue;
        }
        $merged[] = $ovrl;
        continue;
      }
      $merged[$id[0]] = $ovrl;
    }

    return $merged;
  }
}
?>
