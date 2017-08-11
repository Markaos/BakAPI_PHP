<?php
namespace Markaos\BakAPI {
  require_once "bakapi.php";

  class JSONFrontend implements \Markaos\BakAPI\IFrontend {

    public function handleRequest() {
      $ctx = Log::addContext("JSONFrontend");

      if(isset($_GET["a"]) && isset($_GET["v"])) {
        // Action and version attributes are required
        switch($_GET["a"]) {
          case "login": $this->handleLogin(); break;
        }
      } else {
        $this->error("bad_request");
      }

      Log::removeContext($ctx);
    }

    private function handleLogin() {
      if (
          !isset($_GET["name"]) ||
          !isset($_GET["pass"]) ||
          !isset($_GET["server"])
      ) {
        $this->error("bad_request");
        return;
      }

      $res = \Markaos\BakAPI\BakAPI::register($_GET["server"], $_GET["name"], $_GET["pass"]);

      if(!$res["status"]) {
        $code;
        $msg;
        if($res["result"] == BAKAPI_ERROR_SERVER_UNSUPPORTED) {
          $code = 200;
          $msg = "Bad URL";
        } else {
          $code = 201;
          $msg = "Bad login";
        }
        echo '{"status":"error","code":' . $code . ',"message":"' . $msg . '"}';
        return;
      }

      // Login successful
      echo '{"status":"success","code":0,"message":"Login succesful","token":"' . $res["result"] . '"}';
    }

    private function error($type) {
      switch($type) {
        case "bad_request":
          Log::i("Mobile API", "Accessed with wrong parameters");
          echo '{"status":"error","code":100,"message":"Malformed request"}';
          break;
      }
    }
  }
}
?>
