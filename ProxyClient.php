<?php
namespace Markaos\BakAPI {
  require_once "bakapi.php";

  // This client is used for mirroring data from master server
  class ProxyClient implements \Markaos\BakAPI\IClient {
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

    public function getData() {
      return false;
    }

    public function load($sections) {
      return false;
    }

    public function update() {
      return false;
    }
  }
}
?>
