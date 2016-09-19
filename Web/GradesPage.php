<?php
namespace Markaos\BakAPI\Web {

  class GradesPage extends BasePage {

    public function onRequest() {
      $userClient = \Markaos\BakAPI\BakAPI::getClient($this->getUID());
      $userData = $userClient->getData();
      $this->setTitle($userData["name"]);
      $data = $this->getData();
      $grades = $data[BAKAPI_SECTION_GRADES];
      $subsorig = $data[BAKAPI_SECTION_SUBJECTS];
      $subjects = array();

      $g = ContentBuilder::makeCollection()->addClass("with-header");
      foreach($grades as $grade) {
        if(!isset($subjects[$grade["subject"]])) {
          $subjects[$grade["subject"]] = array();
        }
        $subjects[$grade["subject"]][] = $grade;
      }

      foreach($subjects as $key => $subject) {
        $s = "";
        foreach($subsorig as $su) {
          if($su["short"] == $key) $s = $su["name"];
        }
        $g->addItem(
          ContentBuilder::makeText("h4")->setContents($s)->build(),
          true
        );
        foreach($subject as $grade) {
          $g->addItem(
          ContentBuilder::makeBlock("div")
            ->addContentNode(
              ContentBuilder::makeText("span")
                ->setAttribute("style", "display: block;")
                ->setContents($grade["title"])
                ->build()
            )
            ->addContentNode(
              ContentBuilder::makeText("span")
                ->setAttribute("style", "display: block;")
                ->setContents($grade["description"])
                ->build()
            )
            ->addContentNode(
              ContentBuilder::makeText("span")
                ->setContents("<b>" . $grade["grade"] . "</b> (" . $grade["weight"] . ")")
                ->build()
            )
            ->build()
          );
        }
      }

      $this->addContentNode(ContentBuilder::makeBlock()
        ->addClass("container")
        ->addClass("row")
        ->setAttribute("style", "margin-bottom: 0px;")
        ->addContentNode($g->build())
        ->build());
      $this->addPermanentMenuEntry(ContentBuilder::makeBlock("a")
        ->setAttribute("href", "?frontend=cz.markaos.bakapi.web&sync=true")
        ->addContentNode(
          ContentBuilder::makeText("i")
            ->addClass("material-icons")
            ->setContents("loop")
            ->build()
        )
        ->build());
      $this->addMenuEntrySimple("Rozvrh", "&action=timetable");
      $this->addMenuEntrySimple("Známky", "&action=grades", false, true);
      $this->addMenuEntrySimple("Akce", "&action=events");
      $this->addMenuEntrySimple("Úkoly", "&action=homework");
      $this->addMenuEntrySimple("Předměty", "&action=subjects");
      $this->addMenuEntrySimple("Nastavení", "&action=preferences&section=grades");
      $this->addMenuEntrySimple("Odhlásit", "&logout=true");
      $this->finish();
    }
  }
}
?>
