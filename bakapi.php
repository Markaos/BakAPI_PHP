<?php
namespace Markaos\BakAPI {
  require_once "util.php";
  require_once "BakAPIClient.php";
  require_once "LegacyClient.php";

  define("BAKAPI_STATUS_OK", "01");
  define("BAKAPI_STATUS_ERROR", "02");

  define("BAKAPI_SECTION_GRADES", "grades");
  define("BAKAPI_SECTION_SUBJECTS", "subjects");
  define("BAKAPI_SECTION_MESSAGES", "messages");
  define("BAKAPI_SECTION_EVENTS", "events");

  // This is the interface used for BakAPI clients (classes which convert data
  // from external sources to format readable by BakAPI server)
  interface IBakAPIClient {

    // Check if this client is able to connect to server $server. If yes, store
    // server name internally
    //
    // @server   String containing server URL
    // @return   True if this client can handle this server, false otherwise
    public function checkAndStore($server);

    // Connect to server using specified credentials
    //
    // @name      Username
    // @password  Password
    // @return    Array with data needed to reconnect containing indexes "name"
    //            for real name (e.g. John Smith), "class" for class which the
    //            user belongs to (1.A), "version" for server version and "uid"
    //            for user ID (has to be unique for this client)   or false on
    //            failure
    public function connect($name, $password);

    // Reconstruct connection using data saved in connect()
    //
    // @data    Data returned by the connect function
    // @return  True if everything went good, false otherwise
    public function reconstruct($data, $verify = false);

    // Load specified sections
    //
    // @sections  Coma separated list of sections (string)
    // @return    Array with specified sections  (for details about format see
    //            Wiki/Extending BakAPI or look at LegacyClient  code),  false
    //            on failure
    public function load($sections);
  }

  // Primary class of this library, should be the only one used from outside
  class BakAPI {

    // Try to connect to server $server using either legacy or BakAPI client
    //
    // @server   String containing server URL
    // @return   BakAPI client able to connect to this server or null
    public static function checkServer($server) {
      $client = NULL;
      $tmp = new \Markaos\BakAPI\BakAPIClient();
      if($tmp->checkAndStore($server)) {
        $client = $tmp;
      } else {
        $tmp = new \Markaos\BakAPI\LegacyClient();
        if($tmp->checkAndStore($server)) {
          $client = $tmp;
        }
      }
      return $client;
    }

    public static function login($server, $name, $password) {
      $client = BakAPI::checkServer($server);
      if($client == NULL || !$client->connect($name, $password)) {
        return false;
      }
    }

    public static function syncData($user) {

    }

    public static function getChanges($user, $lastKnownChange) {

    }

    public static function getFullDatabase($user) {

    }

    public static function getFullDatabaseHash($user) {

    }
  }
}
?>
