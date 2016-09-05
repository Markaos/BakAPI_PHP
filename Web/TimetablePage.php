<?php
namespace Markaos\BakAPI\Web {

  class TimetablePage extends BasePage {
    private $timetables = array(array(), array());

    public function onRequest() {
      $userClient = \Markaos\BakAPI\BakAPI::getClient($this->getUID());
      $userData = $userClient->getData();
      $this->setTitle($userData["name"]);
      $header = array();
      $table = $this->createTimetable();
      $sel = ContentBuilder::makeSelect()
        ->addClass("col")
        ->addClass("s12 m6 offset-m6 l3 offset-l9")
        ->setSelectAttribute("name", "timetable")
        ->setSelectAttribute("onChange", "this.form.submit()");

      foreach($this->timetables as $id => $timetable) {
        $sel->addOption($timetable["t"], "o$id", isset($timetable["active"]));
      }

      $this->addContentNode(ContentBuilder::makeBlock()
        ->addClass("row")
        ->setAttribute("style", "padding-top: 16px; overflow-x: auto;")
        ->addContentNode(
          ContentBuilder::makeBlock()
            ->addClass("container")
            ->addContentNode(
              ContentBuilder::makeForm()
                ->setAttribute("action", "?frontend=cz.markaos.bakapi.web&action=timetable")
                ->setAttribute("method", "POST")
                ->addContentNode($sel->build())
                ->build()
            )
            ->addContentNodeReference($table)
            ->build()
        )
        ->build()
      );
      $this->addPermanentMenuEntry(ContentBuilder::makeBlock("a")
        ->setAttribute("href", "?frontend=cz.markaos.bakapi.web&action=timetable&sync=true")
        ->addContentNode(
          ContentBuilder::makeText("i")
            ->addClass("material-icons")
            ->setContents("loop")
            ->build()
        )
        ->build());
      $this->addMenuEntrySimple("Rozvrh", "&action=timetable", false, true);
      $this->addMenuEntrySimple("Známky", "&action=grades");
      $this->addMenuEntrySimple("Akce", "&action=events");
      $this->addMenuEntrySimple("Úkoly", "&action=homework");
      $this->addMenuEntrySimple("Předměty", "&action=subjects");
      $this->addMenuEntrySimple("Nastavení", "&action=preferences&section=timetable");
      $this->addMenuEntrySimple("Odhlásit", "&logout=1");
      $this->finish();
    }

    private function createTimetable() {
      $data = $this->getData();

      $actualCycle = $data[BAKAPI_SECTION_TIMETABLE_CYCLES][0];
      $nextCycle = $data[BAKAPI_SECTION_TIMETABLE_CYCLES][1];

      if($this->getPreferences()->getValue("timetable_show_dates", "true") == "true") {
        $this->timetables[0]["t"] = \date("j.n.Y", $actualCycle["mondayDate"]) . " - " .
          \date("j.n.Y", \strtotime("yesterday", $nextCycle["mondayDate"]));

          $this->timetables[1]["t"] = \date("j.n.Y", $nextCycle["mondayDate"]) . " - " .
          \date("j.n.Y", \strtotime("yesterday",
            $data[BAKAPI_SECTION_TIMETABLE_CYCLES][2]["mondayDate"]));
      } else {
        $this->timetables[0]["t"] = "Tento týden";
        $this->timetables[1]["t"] = "Příští týden";
      }

      $captions = $data[BAKAPI_SECTION_TIMETABLE_CAPTIONS];

      if(isset($_POST["timetable"])) {
        if($_POST["timetable"] == "o1") {
          $actualCycle = $nextCycle;
          $nextCycle = $data[BAKAPI_SECTION_TIMETABLE_CYCLES][2];
        }
      }

      $timetable = WebUtil::mergeTimetable (
        $data[BAKAPI_SECTION_TIMETABLE_STABLE],
        $data[BAKAPI_SECTION_TIMETABLE_OVERLAY],
        $actualCycle,
        $nextCycle
      );

      $t = array();
      $days = ["Po", "Út", "St", "Čt", "Pá", "So", "Ne"];
      $daysEnglish = ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"];
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

      $lastCaption = array();
      $row = array();
      $hid = 0;
      foreach($t as $id => $day) {
        if($id > $hid) {
          $hid = $id;
          $row = $day;
        }
      }

      for($i = count($captions); $i > 0; $i--) {
        if(isset($row[$i - 1])) {
          $lastCaption = $captions[$i - 1];
          break;
        }
      }

      if(!isset($_POST["timetable"]) &&
          \strtotime("this week " . $daysEnglish[$hid] . " " . $lastCaption["end"])
            <= \time())
        {
        $actualCycle = $data[BAKAPI_SECTION_TIMETABLE_CYCLES][1];
        $nextCycle = $data[BAKAPI_SECTION_TIMETABLE_CYCLES][2];

        $this->timetables[1]["active"] = true;

        $timetable = WebUtil::mergeTimetable (
          $data[BAKAPI_SECTION_TIMETABLE_STABLE],
          $data[BAKAPI_SECTION_TIMETABLE_OVERLAY],
          $actualCycle,
          $nextCycle
        );

        $t = array();
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
      } else {
        if(!isset($_POST["timetable"])) $this->timetables[0]["active"] = true;
        else {
          if($_POST["timetable"] == "o0") $this->timetables[0]["active"] = true;
          else $this->timetables[1]["active"] = true;
        }
      }

      $header = ContentBuilder::makeBlock("tr")
        ->addContentNode(ContentBuilder::makeText("th")->build());

      foreach($captions as $caption) {
        $header->addContentNode(ContentBuilder::makeBlock("th")
          ->addContentNode(
            ContentBuilder::makeBlock()
              ->setAttribute("style", "min-width: 100px")
              ->addContentNode(
                ContentBuilder::makeText()
                  ->setAttribute("style", "text-align: center; width: 100%; display: inherit")
                  ->setContents($caption["caption"] . "<br>\n")
                  ->build()
              )
              ->addContentNode(
                ContentBuilder::makeText()
                  ->setAttribute("style", "text-align: center; width: 100%; display: inherit")
                  ->addClass("grey-text")
                  ->addClass("text-lighten-1")
                  ->setContents($caption["begin"] . " - " . $caption["end"])
                  ->build()
              )
              ->build()
          )
          ->build());
      }

      $tbl = ContentBuilder::makeBlock("table")
        ->addClass("striped")
        ->addClass("centered")
        ->addContentNode($header->build());

      $h = $this->getPreferences()->getValue("timetable_highlight_diffs", "lesson_name");

      foreach($t as $dayId => $day) {
        $row = ContentBuilder::makeBlock("tr");
        $row->setAttribute("style", "line-height: 10px");
        $row->addContentNode(
          ContentBuilder::makeText("td")
            ->setContents($days[$dayId])
            ->setAttribute("style", "font-weight: 700; width: 60px;")
            ->build()
        );

        foreach($captions as $id => $caption) {
          if(!isset($day[$id])) {
            $row->addContentNode(
              ContentBuilder::makeText("td")
                ->build()
            );
          } else {
            $ovTitle = false;
            $ovTeacher = false;
            $ovRoom = false;

            if(isset($day[$id]["overlay"])) {
              $ovTitle = $day[$id]["overlay"]["title"];
              $ovTeacher = $day[$id]["overlay"]["teacher"];
              $ovRoom = $day[$id]["overlay"]["room"];

              if($h == "lesson_name") {
                $ovTeacher = false;
                $ovRoom = false;
              } else if ($h == "none") {
                $ovTitle = false;
                $ovTeacher = false;
                $ovRoom = false;
              }
            }

            $row->addContentNode(
              ContentBuilder::makeBlock("td")
                ->addContentNode(
                  ContentBuilder::makeText("b")
                    ->addClass($ovTitle ? "red-text" : "")
                    ->setAttribute("style", "text-align: center; display: inline-block;")
                    ->setContents($day[$id]["short"])
                    ->build()
                )
                ->addContentNode(
                  ContentBuilder::makeLineBreak()->build()
                )
                ->addContentNode(
                  ContentBuilder::makeLineBreak()->build()
                )
                ->addContentNode(
                  ContentBuilder::makeText("i")
                    ->addClass($ovTeacher ? "red-text" : "")
                    ->setAttribute("style", "text-align: center; display: inline-block;")
                    ->setContents($day[$id]["steacher"])
                    ->build()
                )
                ->addContentNode(
                  ContentBuilder::makeLineBreak()->build()
                )
                ->addContentNode(
                  ContentBuilder::makeLineBreak()->build()
                )
                ->addContentNode(
                  ContentBuilder::makeText()
                    ->addClass($ovRoom ? "red-text" : "")
                    ->setAttribute("style", "float: left; font-size: 12px; padding-left: 8px; padding-right: 4px;")
                    ->setContents($day[$id]["shortRoom"])
                    ->build()
                )
                ->addContentNode(
                  ContentBuilder::makeText()
                    ->setAttribute("style", "float: right; font-size: 12px; padding-right: 8px; padding-right: 4px;")
                    ->setContents($day[$id]["shortGroup"])
                    ->build()
                )
                ->build()
            );
          }
        }

        $tbl->addContentNode($row->build());
      }

      return $tbl->build();
    }
  }
}
?>
