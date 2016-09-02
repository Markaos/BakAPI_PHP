<?php
namespace Markaos\BakAPI\Web {
  require_once "InputContentBuilder.php";
  require_once "SelectContentBuilder.php";

  class ContentBuilder {
    protected $content = array();

    public static function makeBlock($element = "div") {
      return new static($element, "container");
    }

    public static function makeForm() {
      return new static("form", "container");
    }

    public static function makeInput() {
      return new InputContentBuilder();
    }

    public static function makeSelect() {
      return new SelectContentBuilder();
    }

    public static function makeLineBreak() {
      return new static("br", "end");
    }

    public static function makeButton() {
      return new static("button", "end");
    }

    public static function makeText($element = "span") {
      return new static($element, "end");
    }

    public function __construct($element, $type) {
      $this->content["element"]    = $element;
      $this->content["type"]       = $type;
      $this->content["classes"]    = "";
      $this->content["id"]         = "";
      $this->content["attributes"] = array();
      $this->content["contents"]   = $type == "container" ? array() : "";
    }

    public function setClass($class) {
      $this->content["classes"] = $class;
      return $this;
    }

    public function addClass($class) {
      $this->content["classes"] .= " " . $class;
      return $this;
    }

    public function setId($id) {
      $this->content["id"] = $id;
      return $this;
    }

    public function setAttribute($attr, $value) {
      $this->content["attributes"][$attr] = $value;
      return $this;
    }

    public function addContentNode($node) {
      if($this->content["type"] != "container") {
        throw new \Exception(
          "addContentNode() can be called only on containers"
        );
      }
      $this->content["contents"][] = $node;
      return $this;
    }

    public function addContentNodeReference(&$node) {
      if($this->content["type"] != "container") {
        throw new \Exception(
          "addContentNode() can be called only on containers"
        );
      }
      $this->content["contents"][] = &$node;
      return $this;
    }

    public function setContents($contents) {
      $this->content["contents"] = $contents;
      return $this;
    }

    public function setContentsReference(&$contents) {
      $this->content["contents"] = &$contents;
      return $this;
    }

    public function build() {
      return $this->content;
    }
  }
}
?>
