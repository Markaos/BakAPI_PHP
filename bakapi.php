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

  define("BAKAPI_ERROR_SERVER_UNSUPPORTED", "SERVER_UNSUPPORTED");
  define("BAKAPI_ERROR_LOGIN_FAILED", "LOGIN_FAILED");

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

    // Try to register user using given credentials.  The correct  client will
    // be selected using BakAPI::checkServer().
    //
    // @server    Server URL
    // @name      Username for remote server
    // @password  Password for remote server
    // @return    Array containing fields  "status"  (true if registration was
    //            successful, false otherwise) and "result" (string containing
    //            UID or error message)
    public static function register($server, $name, $password) {
      $client = BakAPI::checkServer($server);
      if($client == NULL) {
        return [
          "status" => false,
          "result" => BAKAPI_ERROR_SERVER_UNSUPPORTED
        ];
      }

      $data = $client->connect($name, $password);
      if($data === false) {
        return [
          "status" => false,
          "result" => BAKAPI_ERROR_LOGIN_FAILED
        ];
      }

      // TODO: store data to database

      return [
        "status" => true,
        "result" => $data["uid"]
      ];
    }

    // Get BakAPIClient associated with this user (ready to read from server)
    //
    // @user    UID to look for
    // @return  BakAPIClient connected to server using user's credentials
    public static function getClient($user) {

    }

    // Read new or changed data from server and store them to DB (both as full
    // DB and as transactions)
    //
    // @user    User ID
    // @return  True if everything went OK, false otherwise
    public static function syncData($user) {

    }

    // Get list of changes since last known change
    //
    // @user              User ID
    // @lastKnownChange   ID of the last transaction client knows about
    // @return            Array with modifications  -  refer to documentation
    //                    for exact format
    public static function getChanges($user, $lastKnownChange) {

    }

    // Get up-to-date database for this user. Called in case the server doesn't
    // remember the last change known to client
    //
    // @user    User ID
    // @return  Array with full database - more in documentation
    public static function getFullDatabase($user) {

    }

    // Get hashsum of the database. Used for veryfying patching process
    //
    // @user    User ID
    // @return  Database hashsum
    public static function getFullDatabaseHash($user) {

    }
  }
}
?>
