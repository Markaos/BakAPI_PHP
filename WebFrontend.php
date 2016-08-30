<?php
namespace Markaos\BakAPI {
  require_once "bakapi.php";

  class WebFrontend implements \Markaos\BakAPI\IFrontend {

    public function handleRequest() {
      echo "<pre>\n";
      echo "OMG! I'm running!\n";
      echo "Wait a moment...\n";
      echo "I don't know what to do.\n";
      echo "</pre>";
    }
  }
}
?>
