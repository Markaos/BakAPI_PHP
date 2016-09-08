<?php
namespace Markaos\BakAPI\Web {
  class WebUtil {
    public static function mergeTimetable($stable, $overlay, $cycle, $nextCycle, $themes) {
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

        $ovrl["overlay"] = array("title" => true, "teacher" => false, "room" => false);
        $id = \Markaos\BakAPI\Util::getLessonIndexes($merged, $ovrl["day"], $ovrl["caption"]);
        if(count($id) == 0) {
          if($ovrl["type"] == "X") {
            // We are replacing non-existent lesson with empty lesson - something
            // surely went wrong in the client
            \Markaos\BakAPI\Log::critical("TestUtil",
              "Replacing non-existent lesson with empty lesson (caption: " .
              $ovrl["caption"] . "; day: " . $ovrl["day"] . ")");
            continue;
          }
          $ovrl["overlay"]["teacher"] = true;
          $ovrl["overlay"]["room"] = true;
          $merged[] = $ovrl;
          continue;
        }

        if($merged[$id[0]]["short"] == $ovrl["short"]) {
          $ovrl["overlay"]["title"] = false;
        }

        if($merged[$id[0]]["steacher"] != $ovrl["steacher"]) {
          $ovrl["overlay"]["teacher"] = true;
        }

        if($merged[$id[0]]["shortRoom"] != $ovrl["shortRoom"]) {
          $ovrl["overlay"]["room"] = true;
        }

        $merged[$id[0]] = $ovrl;
      }

      $days = [
        "Po" => "Monday",
        "Út" => "Tuesday",
        "St" => "Wednesday",
        "Čt" => "Thursday",
        "Pá" => "Friday",
        "So" => "Saturday",
        "Ne" => "Sunday"
      ];

      $th = array();
      if($themes !== false) {
        foreach($themes as $theme) {
          if(!isset($th[$theme["date"]])) {
            $th[$theme["date"]] = array();
          }

          $th[$theme["date"]][$theme["caption"]] = $theme["theme"];
        }
      }

      foreach($merged as &$lesson) {
        if(!isset($lesson["date"])) {
          $lesson["date"] = \strtotime("this week " . $days[$lesson["day"]],
            $cycle["mondayDate"]);
        }

        if($themes !== false) {
          if(isset($th[$lesson["date"]][$lesson["caption"]])) {
            $lesson["theme"] = $th[$lesson["date"]][$lesson["caption"]];
          } else {
            $lesson["theme"] = "";
          }
        }
      }

      return $merged;
    }
  }
}
?>
