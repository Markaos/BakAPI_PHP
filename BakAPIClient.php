<?php
namespace Markaos\BakAPI {
  require_once "bakapi.php";

  class BakAPIClient implements \Markaos\BakAPI\IBakAPIClient {
    private $server = null;

    public function checkAndStore($server) {
      return false;
    }

    public function connect($name, $password) {
      return false;
    }

    public function reconstruct($data, $verify = false) {
      return false;
    }
  }
}
?>
