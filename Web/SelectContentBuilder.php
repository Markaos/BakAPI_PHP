<?php
namespace Markaos\BakAPI\Web {

  class SelectContentBuilder extends ContentBuilder {
    public function __construct() {
      $this->content["element"]    = "div";
      $this->content["type"]       = "container";
      $this->content["classes"]    = "input-field";
      $this->content["id"]         = "";
      $this->content["attributes"] = array();
      $this->content["contents"]   = array();

      $label = [
        "element"     => "label",
        "type"        => "end",
        "classes"     => "",
        "id"          => "",
        "attributes"  => array(),
        "contents"    => ""
      ];

      $input = [
        "element"     => "select",
        "type"        => "container",
        "classes"     => "",
        "id"          => "",
        "attributes"  => array()
      ];

      $this->content["contents"][1] = $input;
      $this->content["contents"][0] = $label;
    }

    public function setId($id) {
      $this->content["contents"][1]["id"] = $id;
      $this->content["contents"][0]["attributes"]["for"] = $id;
      return $this;
    }

    public function setSelectAttribute($attr, $value) {
      $this->content["contents"][1]["attributes"][$attr] = $value;
      return $this;
    }

    public function addOption($text, $value, $selected) {
      $opt = ContentBuilder::makeText("option");
      $opt->setContents($text);
      $opt->setAttribute("value", $value);
      if($selected) {
        $opt->setAttribute("selected", "true");
      }

      $this->content["contents"][1]["contents"][] = $opt->build();
    }
  }
}
?>
