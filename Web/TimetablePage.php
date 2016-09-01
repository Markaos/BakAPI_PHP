<?php
namespace Markaos\BakAPI\Web {

  class TimetablePage extends BasePage {

    public function onRequest() {
      $userClient = \Markaos\BakAPI\BakAPI::getClient($this->getUID());
      $userData = $userClient->getData();
      $this->setTitle($userData["name"]);
      $header = array();
      $table = $this->createTimetable($userData);

      $this->addContentNode(ContentBuilder::makeBlock()
        ->addClass("row")
        ->setAttribute("style", "padding-top: 16px; overflow-x: auto;")
        ->addContentNode(
          ContentBuilder::makeBlock()
            ->addClass("container")
            ->addContentNodeReference($table)
            ->build()
        )
        ->build()
      );
      $this->addPermanentMenuEntry(ContentBuilder::makeBlock("a")
        ->setAttribute("href", "#")
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
      $this->addMenuEntrySimple("Odhlásit", "&logout=1");
      $this->finish();
    }

    private function createTimetable() {
      $data = $this->getData();

      $timetable = WebUtil::mergeTimetable (
        $data[BAKAPI_SECTION_TIMETABLE_STABLE],
        $data[BAKAPI_SECTION_TIMETABLE_OVERLAY],
        $data[BAKAPI_SECTION_TIMETABLE_CYCLES][0],
        $data[BAKAPI_SECTION_TIMETABLE_CYCLES][1]
      );

      $captions = $data[BAKAPI_SECTION_TIMETABLE_CAPTIONS];

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
            $row->addContentNode(
              ContentBuilder::makeBlock("td")
                ->addContentNode(
                  ContentBuilder::makeText("b")
                    ->setAttribute("style", "text-align: center; display: inline-block;")
                    ->setContents($day[$id]["short"])
                    ->build()
                )
                ->addContentNode(
                  ContentBuilder::makeLineBreak()->build()
                )
                ->addContentNode(
                  ContentBuilder::makeText("i")
                    ->setAttribute("style", "text-align: center; display: inline-block;")
                    ->setContents($day[$id]["steacher"])
                    ->build()
                )
                ->addContentNode(
                  ContentBuilder::makeLineBreak()->build()
                )
                ->addContentNode(
                  ContentBuilder::makeText()
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
