<?php
namespace Markaos\BakAPI\Web {

  class CollectionContentBuilder extends ContentBuilder {
    public function __construct() {
      $this->content["element"]    = "ul";
      $this->content["type"]       = "container";
      $this->content["classes"]    = "collection";
      $this->content["id"]         = "";
      $this->content["attributes"] = array();
      $this->content["contents"]   = array();
    }

    public function addItemSimple($text) {
      $this->addContentNode(
        ContentBuilder::makeBlock("li")
          ->addClass("collection-item")
          ->addContentNode(
            ContentBuilder::makeText()
              ->setContents($text)
              ->build()
          )
          ->build()
      );
    }

    public function addItem($item) {
      $this->addContentNode(
        ContentBuilder::makeBlock("li")
          ->addClass("collection-item")
          ->addContentNode($item)
          ->build()
      );
    }
  }
}
?>
