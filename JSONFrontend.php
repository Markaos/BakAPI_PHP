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
          case "getUpdates": $this->handleUpdate(); break;
          case "getDatabase": $this->handleDatabase(); break;
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
      $this->printResponse("success", 0, [
        "name"    => $data["name"],
        "class"   => $data["class"],
        "version" => $data["version"]
      ]);
    }

    private function handleUpdate() {
      if(!isset($_GET["token"]) || !isset($_GET["trans"])) {
        $this->error("bad_request");
        return;
      }

      $hash = \Markaos\BakAPI\BakAPI::getFullDatabaseHash (
        \Markaos\BakAPI\BakAPI::getFullDatabase($_GET["token"])
      );

      $db = \Markaos\BakAPI\Util::getDatabase();
      $check = $db->raw("SELECT * FROM changes WHERE _ID=?", [$_GET["trans"]]);
      if(count($check) < 1) {
        echo '{"status":"error","code":210,"message":"Unknown transaction, download full database"}';
        return;
      }

      $res = $db->raw("SELECT MAX(_ID) AS last FROM changes WHERE field_UID=?", [$_GET["token"]]);
      $transactions = \Markaos\BakAPI\BakAPI::getChanges($_GET["token"], $_GET["trans"]);
      echo '{"status":"success","code":0,"t":[';
      $f = true;
      foreach($transactions as $t) {
        if(!$f) echo ',';
        $f = false;
        $t = unserialize($t["serialized"]);
        echo '{"type":"' . $t["type"] . '","sect":"' . $t["table"] . '",';
        echo '"data":{';
        $f2 = true;
        foreach($t["data"] as $key => $value) {
          if(!$f2) echo ',';
          $f2 = false;
          echo '"' . $key . '":"' . $value . '"';
        }
        echo '}}';
      }
      echo '],"last":"' . $res[0]["last"] . '","checksum":"' . $hash . '"}';
    }

    private function handleDatabase() {
      if(!isset($_GET["token"])) {
        $this->error("bad_request");
        return;
      }

      $db = \Markaos\BakAPI\BakAPI::getFullDatabase($_GET["token"]);

      echo '{"status":"success","code":0,"d":{';
      $f = true;
      foreach($db as $section => $data) {
        if(!is_array($data)) continue;
        if(!f) echo ',';
        $f = false;
        echo '"' . $section . '":[';
        foreach($data as $entry) {
          echo '{';
          $f2 = true;
          foreach($entry as $key => $value) {
            if(!$f2) echo ',';
            $f2 = false;
            echo '"' . $key . '":"' . $value . '"';
          }
          echo '}';
        }
        echo ']';
      }
      echo '},"last":"' . $db["transaction"] . '","checksum":"' . $db["hash"] . '"}';
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
