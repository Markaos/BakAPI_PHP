<?php
namespace Markaos\BakAPI\Web {

  class EventsPage extends BasePage {

    public function onRequest() {
      $userClient = \Markaos\BakAPI\BakAPI::getClient($this->getUID());
      $userData = $userClient->getData();
      $this->setTitle($userData["name"]);
      $data = $this->getData();
      $events = $data[BAKAPI_SECTION_EVENTS];

      $e = ContentBuilder::makeCollection();
      foreach($events as $event) {
        $evt = ContentBuilder::makeBlock()
          ->addClass("col s12 l4");

        $ev = ContentBuilder::makeBlock()
          ->addClass("row")
          ->setAttribute("style", "margin-bottom: 0px")
          ->addContentNode(
            ContentBuilder::makeBlock()
              ->addClass("col s12 l8")
              ->addContentNode(
                ContentBuilder::makeText()
                  ->setAttribute("style", "font-weight: 500;")
                  ->setContents($event["name"])
                  ->build()
              )
              ->addContentNode(ContentBuilder::makeLineBreak()->build())
              ->addContentNode(
                ContentBuilder::makeText()
                  ->setAttribute("style", "line-height: 1rem;")
                  ->setContents(str_replace("/chr(13)", "<br>", $event["description"]))
                  ->build()
              )
              ->build()
          );

        $date = \date("j.n.Y", $event["date"]);
        if($event["timerange"] != "") {
          $date .= " " . $event["timerange"];
        }

        $evt->addContentNode(
          ContentBuilder::makeText()
            ->addClass("grey-text text-darken-2")
            ->setContents($date)
            ->build()
        );

        if($event["classes"] != "") {
          $evt->addContentNode(ContentBuilder::makeLineBreak()->build());
          $evt->addContentNode(
            ContentBuilder::makeText()
              ->addClass("grey-text")
              ->setContents("Třídy: " . $event["classes"])
              ->build()
          );
        }

        if($event["rooms"] != "") {
          $evt->addContentNode(ContentBuilder::makeLineBreak()->build());
          $evt->addContentNode(
            ContentBuilder::makeText()
              ->addClass("grey-text")
              ->setContents("Místnosti: " . $event["rooms"])
              ->build()
          );
        }

        if($event["teachers"] != "") {
          $evt->addContentNode(ContentBuilder::makeLineBreak()->build());
          $evt->addContentNode(
            ContentBuilder::makeText()
              ->addClass("grey-text")
              ->setContents("Učitelé: " . $event["teachers"])
              ->build()
          );
        }

        $ev->addContentNode($evt->build());

        $e->addItem($ev->build());
      }

      $this->addContentNode(ContentBuilder::makeBlock()
        ->addClass("container")
        ->addClass("row")
        ->setAttribute("style", "margin-bottom: 0px;")
        ->addContentNode($e->build())
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
      $this->addMenuEntrySimple("Akce", "&action=events", false, true);
      $this->addMenuEntrySimple("Úkoly", "&action=homework");
      $this->addMenuEntrySimple("Předměty", "&action=subjects");
      $this->addMenuEntrySimple("Nastavení", "&action=preferences&section=events");
      $this->addMenuEntrySimple("Odhlásit", "&logout=true");
      $this->finish();
    }
  }
}
?>
