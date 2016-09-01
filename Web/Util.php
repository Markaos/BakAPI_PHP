<?php
namespace Markaos\BakAPI\Web {
  class WebUtil {
    public static function mergeTimetable($stable, $overlay, $cycle, $nextCycle) {
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
            \Markaos\BakAPI\Log::critical("TestUtil",
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
}
?>
