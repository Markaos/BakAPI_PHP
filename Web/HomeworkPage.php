<?php
namespace Markaos\BakAPI\Web {

  class HomeworkPage extends BasePage {

    public function onRequest() {
      $userClient = \Markaos\BakAPI\BakAPI::getClient($this->getUID());
      $userData = $userClient->getData();
      $this->setTitle($userData["name"]);
      $this->addContentNode(ContentBuilder::makeBlock()
        ->addClass("valign-wrapper")
        ->addClass("row")
        ->setAttribute("style", "height: 90%")
        ->addContentNode(
          ContentBuilder::makeBlock()
            ->addClass("valign")
            ->addClass("col s12 m10 offset-m1 l8 offset-l2")
            ->addContentNode(
              ContentBuilder::makeText("h1")
                ->addClass("center-align")
                ->setContents("Tato část ještě není hotová")
                ->build()
            )
            ->build()
        )
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
      $this->addMenuEntrySimple("Známky", "&action=grades");
      $this->addMenuEntrySimple("Akce", "&action=events");
      $this->addMenuEntrySimple("Úkoly", "&action=homework", false, true);
      $this->addMenuEntrySimple("Předměty", "&action=subjects");
      $this->addMenuEntrySimple("Nastavení", "&action=preferences&section=homework");
      $this->addMenuEntrySimple("Odhlásit", "&logout=true");
      $this->finish();
    }
  }
}
?>
