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
          case "getUserInfo": $this->handleUserInfo(); break;
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
        $this->printResponse("error", $code, ["message" => $msg]);
        return;
      }

      // Login successful
      $this->printResponse("success", 0, ["token" => $res["result"]]);
    }

    private function handleUserInfo() {
      if(!isset($_GET["token"])) {
        $this->error("bad_request");
        return;
      }

      $client = \Markaos\BakAPI\BakAPI::getClient($_GET["token"]);
      $data = $client->getData();
      printResponse("success", 0, [
        "name"    => $data["name"],
        "class"   => $data["class"],
        "version" => $data["version"]
      ]);
    }

    private function error($type) {
      switch($type) {
        case "bad_request":
          Log::i("Mobile API", "Accessed with wrong parameters");
          echo '{"status":"error","code":100,"message":"Malformed request"}';
          break;
      }
    }

    private function printResponse($status, $code, $array) {
      echo '{"status":"' . $status . '","code":' . $code;
      foreach($array as $key => $value) {
        echo ',"' . $key . '":"' . $value . '"';
      }
      echo "}";
    }
  }
}
?>
