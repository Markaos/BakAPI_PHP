<?php
namespace Markaos\BakAPI {
  class DiffUtil {

    // Get array of differences between these two fields. Keys don't matter in
    // comparsion
    //
    // @oldA    Array containing old values
    // @newA    Array with new values
    // @return  Array wit differences
    public static function getDifferencesBakAPI($oldA, $newA) {
      // Note: modifications not supported yet
      $removed = array();
      $added = array();

      if(isset($oldA[BAKAPI_SECTION_GRADES])) {
        $section = BAKAPI_SECTION_GRADES;
        $a1 = $oldA[$section];
        $a2 = $newA[$section];
        DiffUtil::findDifferences($a1, $a2, $removed, $added,
          BAKAPI_SECTION_GRADES);
      }

      if(isset($oldA[BAKAPI_SECTION_SUBJECTS])) {
        $section = BAKAPI_SECTION_SUBJECTS;
        $a1 = $oldA[$section];
        $a2 = $newA[$section];
        DiffUtil::findDifferences($a1, $a2, $removed, $added,
          BAKAPI_SECTION_SUBJECTS);
      }

      if(isset($oldA[BAKAPI_SECTION_MESSAGES])) {
        $section = BAKAPI_SECTION_MESSAGES;
        $a1 = $oldA[$section];
        $a2 = $newA[$section];
        DiffUtil::findDifferences($a1, $a2, $removed, $added,
          BAKAPI_SECTION_MESSAGES);
      }

      if(isset($oldA[BAKAPI_SECTION_EVENTS])) {
        $section = BAKAPI_SECTION_EVENTS;
        $a1 = $oldA[$section];
        $a2 = $newA[$section];
        DiffUtil::findDifferences($a1, $a2, $removed, $added,
          BAKAPI_SECTION_EVENTS);
      }

      if(isset($oldA[BAKAPI_SECTION_HOMEWORK])) {
        $section = BAKAPI_SECTION_HOMEWORK;
        $a1 = $oldA[$section];
        $a2 = $newA[$section];
        DiffUtil::findDifferences($a1, $a2, $removed, $added,
          BAKAPI_SECTION_HOMEWORK);
      }

      if(isset($oldA[BAKAPI_SECTION_TIMETABLE_STABLE])) {
        $section = BAKAPI_SECTION_TIMETABLE_STABLE;
        $a1 = $oldA[$section];
        $a2 = $newA[$section];
        DiffUtil::findDifferences($a1, $a2, $removed, $added,
          BAKAPI_SECTION_TIMETABLE_STABLE);
      }

      if(isset($oldA[BAKAPI_SECTION_TIMETABLE_OVERLAY])) {
        $section = BAKAPI_SECTION_TIMETABLE_OVERLAY;
        $a1 = $oldA[$section];
        $a2 = $newA[$section];
        DiffUtil::findDifferences($a1, $a2, $removed, $added,
          BAKAPI_SECTION_TIMETABLE_OVERLAY);
      }

      if(isset($oldA[BAKAPI_SECTION_TIMETABLE_CYCLES])) {
        $section = BAKAPI_SECTION_TIMETABLE_CYCLES;
        $a1 = $oldA[$section];
        $a2 = $newA[$section];
        DiffUtil::findDifferences($a1, $a2, $removed, $added,
          BAKAPI_SECTION_TIMETABLE_CYCLES);
      }

      if(isset($oldA[BAKAPI_SECTION_TIMETABLE_CAPTIONS])) {
        $section = BAKAPI_SECTION_TIMETABLE_CAPTIONS;
        $a1 = $oldA[$section];
        $a2 = $newA[$section];
        DiffUtil::findDifferences($a1, $a2, $removed, $added,
          BAKAPI_SECTION_TIMETABLE_CAPTIONS);
      }

      if(isset($oldA[BAKAPI_SECTION_TIMETABLE_THEMES])) {
        $section = BAKAPI_SECTION_TIMETABLE_THEMES;
        $a1 = $oldA[$section];
        $a2 = $newA[$section];
        DiffUtil::findDifferences($a1, $a2, $removed, $added,
          BAKAPI_SECTION_TIMETABLE_THEMES);
      }

      $diffs = array();

      while(list(, $value) = each($removed)) {
        $section = $value["diff_extra"];
        unset($value["diff_extra"]);
        $diffs[] = [
          "type" => "r",
          "table" => $section,
          "data" => $value
        ];
      }

      while(list(, $value) = each($added)) {
        $section = $value["diff_extra"];
        unset($value["diff_extra"]);
        $diffs[] = [
          "type" => "a",
          "table" => $section,
          "data" => $value
        ];
      }

      return $diffs;
    }

    // Find differences in arrays without taking keys into account
    //
    // @a1        First array, nicknamed "original"
    // @a2        Second array, "new"
    // @removed   Values unique to original array
    // @added     Values unique to new array
    public static function findDifferences($a1, $a2, &$removed, &$added, $e = "") {
      while(list(, $value) = each($a1)) {
        if(!in_array($value, $a2, true)) {
          if($e != "") $value["diff_extra"] = $e;
          $removed[] = $value;
        }
      }

      while(list(, $value) = each($a2)) {
        if(!in_array($value, $a1, true)) {
          if($e != "") $value["diff_extra"] = $e;
          $added[] = $value;
        }
      }
    }

    // Compute SHA-256 checksum of BakAPI section
    public static function getChecksum($db, $section) {
      $str = "";
      switch($section) {
        case BAKAPI_SECTION_GRADES:
          foreach($db[$section] as $item) {
            $str .= $item["subject"] . $item["title"] . $item["description"] .
                    $item["grade"] . $item["weight"] . $item["date"];
          }
          break;
        case BAKAPI_SECTION_SUBJECTS:
          foreach($db[$section] as $item) {
            $str .= $item["name"] . $item["teachers"] . $item["emails"] .
                    $item["short"];
          }
          break;
        case BAKAPI_SECTION_MESSAGES:
          foreach($db[$section] as $item) {
            $str .= $item["from"] . $item["contents"] . $item["sysid"] .
                    $item["date"];
          }
          break;
        case BAKAPI_SECTION_EVENTS:
          foreach($db[$section] as $item) {
            $str .= $item["name"] . $item["description"] . $item["timerange"] .
                    $item["rooms"] . $item["teachers"] . $item["classes"] .
                    $item["show"] . $item["date"];
          }
          break;
        case BAKAPI_SECTION_HOMEWORK:
          foreach($db[$section] as $item) {
            $str .= $item["subject"] . $item["issued"] . $item["deadline"] .
                    $item["state"] . $item["description"];
          }
          break;
        case BAKAPI_SECTION_TIMETABLE_CAPTIONS:
          foreach($db[$section] as $item) {
            $str .= $item["caption"] . $item["begin"] . $item["end"];
          }
          break;
        case BAKAPI_SECTION_TIMETABLE_STABLE:
          foreach($db[$section] as $item) {
            $str .= $item["caption"] . $item["day"] . $item["type"] .
                    $item["short"] . $item["steacher"] . $item["teacher"] .
                    $item["shortRoom"] . $item["shortGroup"] . $item["group"] .
                    $item["cycle"];
          }
          break;
        case BAKAPI_SECTION_TIMETABLE_STABLE:
          foreach($db[$section] as $item) {
            $str .= $item["caption"] . $item["day"] . $item["type"] .
                    $item["short"] . $item["steacher"] . $item["teacher"] .
                    $item["shortRoom"] . $item["shortGroup"] . $item["group"] .
                    $item["theme"] . $item["date"];
          }
          break;
        case BAKAPI_SECTION_TIMETABLE_CAPTIONS:
          foreach($db[$section] as $item) {
            $str .= ((string) $item["mondayDate"]) . $item["cycle"];
          }
          break;
      }
      return \hash("sha256", $str);
    }
  }
}
?>
