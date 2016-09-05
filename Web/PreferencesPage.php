<?php
namespace Markaos\BakAPI\Web {

  class PreferencesPage extends BasePage {

    public function onRequest() {
      if(isset($_POST["name"])) {
        \Markaos\BakAPI\Log::i("PreferencesPage", "Setting " . $_POST["name"] . "=" . $_POST["value"]);
        $this->getPreferences()->setValue($_POST["name"], $_POST["value"]);
      }

      $userClient = \Markaos\BakAPI\BakAPI::getClient($this->getUID());
      $userData = $userClient->getData();
      $this->setTitle($userData["name"]);
      $this->addContentNode(ContentBuilder::makeBlock()
        ->addClass("container")
        ->addClass("row")
        ->setAttribute("style", "margin-bottom: 0px;")
        ->addContentNode($this->makeTimetableSection())
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
      $this->addMenuEntrySimple("Úkoly", "&action=homework");
      $this->addMenuEntrySimple("Předměty", "&action=subjects");
      $this->addMenuEntrySimple("Nastavení", "&action=preferences", false, true);
      $this->addMenuEntrySimple("Odhlásit", "&logout=true");
      $this->finish();
    }

    private function makeTimetableSection() {
      $keys = [
        "timetable_show_dates" => [
          "true" => "Zobrazovat datum rozvrhu",
          "false" => "Zobrazovat \"Tento\"/\"Příští\" týden"
        ]
      ];
      $s = ContentBuilder::makeBlock()
        ->addClass("col s12 m6 l4")
        ->addContentNode(
          ContentBuilder::makeText("h2")
            ->addClass("header")
            ->setContents("Rozvrh")
            ->build()
        );

      $p = $this->getPreferences();
      foreach($keys as $key => $values) {
        \reset($values);
        $value = $p->getValue($key, \key($values));
        $sel = ContentBuilder::makeSelect()
          ->setSelectAttribute("name", "value")
          ->setSelectAttribute("onChange", "this.form.submit()");

        foreach($values as $val => $description) {
          $sel->addOption($description, $val, $val == $value);
        }

        $s->addContentNode(
          ContentBuilder::makeForm()
            ->setAttribute("action", "?frontend=cz.markaos.bakapi.web&action=preferences")
            ->setAttribute("method", "POST")
            ->addContentNode(
              ContentBuilder::makeHidden()
                ->setAttribute("type", "hidden")
                ->setAttribute("name", "name")
                ->setAttribute("value", $key)
                ->build()
            )
            ->addContentNode($sel->build())
            ->build()
        );
      }

      return $s->build();
    }
  }
}
?>
