<?php
namespace Markaos\BakAPI {
  require_once "bakapi.php";

  class LegacyClient implements \Markaos\BakAPI\IBakAPIClient {
    private $server = null;
    private $hash = null;

    public function checkAndStore($server) {
      $store = \Markaos\BakAPI\Util::loadPage($server . "/login.aspx?gethx=null");

      \libxml_use_internal_errors(true);
      $xml = \simplexml_load_string($store);

      if($xml === false) {
        return false;
      }

      if((string) $xml->res == BAKAPI_STATUS_ERROR) {
        $this->server = $server;
        return true;
      }
    }

    public function connect($name, $password) {
      $store = \Markaos\BakAPI\Util::loadPage($this->server . "/login.aspx?gethx=$name");

      \libxml_use_internal_errors(true);
      $xml = \simplexml_load_string($store);
      if($xml === false || !((string) $xml->res == BAKAPI_STATUS_OK)) {
        return false;
      }

      $type = (string) $xml->typ;
      $internalCode = (string) $xml->ikod;
      $salt = (string) $xml->salt;

      $passHash = base64_encode(hash("sha512", $salt . $internalCode . $type . $password, true));
      $passHash = "*login*" . $name . "*pwd*" . $passHash . "*sgn*ANDR";

      $loginHash = base64_encode(hash("sha512", $passHash . date("Ymd"), true));
      $loginHash = str_replace(['\\', '/', '+'], ['_', '_', '-'], $loginHash);
      $this->hash = $loginHash;

      $store = \Markaos\BakAPI\Util::loadPage($this->server .
        "/login.aspx?hx=$loginHash&pm=login");

      \libxml_use_internal_errors(true);
      $xml = \simplexml_load_string($store);
      if($xml === false || !((string) $xml->result == BAKAPI_STATUS_OK)) {
        return false;
      }

      $name = (string) $xml->jmeno;
      $cls = substr($name, strpos($name, ',') + 2);
      $name = substr($name, 0, strpos($name, ','));
      $name = explode(' ', $name);
      $name = $name[1] . " " . $name[0];

      $version = (string) $xml->verze;

      return [
        "name" => $name,
        "class" => $cls,
        "version" => $version,
        "token" => $passHash,
        "server" => $this->server
      ];
    }

    public function reconstruct($data, $verify = false) {
      $loginHash = base64_encode(hash("sha512", $data["token"] . date("Ymd"), true));
      $loginHash = str_replace(['\\', '/', '+'], ['_', '_', '-'], $loginHash);

      $this->server = $data["server"];
      $this->hash = $loginHash;

      if($verify) {
        $store = \Markaos\BakAPI\Util::loadPage($this->server .
          "/login.aspx?hx=$" . data["token"] . "&pm=login");

        \libxml_use_internal_errors(true);
        $xml = \simplexml_load_string($store);
        if($xml === false || !((string) $xml->result == BAKAPI_STATUS_OK)) {
          return false;
        }
      }
    }

    public function load($sections) {
      $sections = explode(',', $sections);
      $rArr = new Array();
      foreach($sections as $section) {
        switch($section) {
          case BAKAPI_SECTION_GRADES:
            rArr[BAKAPI_SECTION_GRADES] = $this->loadGrades();
            break;
          case BAKAPI_SECTION_SUBJECTS:
            rArr[BAKAPI_SECTION_SUBJECTS] = $this->loadSubjects();
            break;
          case BAKAPI_SECTION_MESSAGES:
            rArr[BAKAPI_SECTION_MESSAGES] = $this->loadMessages();
            break;
          case BAKAPI_SECTION_EVENTS:
            rArr[BAKAPI_SECTION_EVENTS] = $this->loadEvents();
            break;
        }
      }
    }

    private function loadGrades() {

    }

    private function loadSubjects() {

    }

    private function loadMessages() {

    }

    private function loadEvents() {
      
    }
  }
}
?>
