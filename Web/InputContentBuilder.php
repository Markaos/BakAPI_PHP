<?php
namespace Markaos\BakAPI\Web {

  class InputContentBuilder extends ContentBuilder {
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
        "element"     => "input",
        "type"        => "end",
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

    public function setAttribute($attr, $value) {
      if($attr == "label") {
        $this->content["contents"][0]["contents"] = $value;
      } else {
        $this->content["contents"][1]["attributes"][$attr] = $value;
      }
      return $this;
    }
  }
}
?>
