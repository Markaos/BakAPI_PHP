<?php
namespace Markaos\BakAPI {
  require_once "util.php";
  require_once "ProxyClient.php";
  require_once "LegacyClient.php";
  require_once "MySQLDatabase.php";

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
  interface IClient {

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

    // Get data (same as connect)
    //
    // @return    Array with data from connect()
    public function getData();

    // Load specified sections
    //
    // @sections  Coma separated list of sections (string)
    // @return    Array with specified sections  (for details about format see
    //            Wiki/Extending BakAPI or look at LegacyClient  code),  false
    //            on failure
    public function load($sections);
  }

  // Interface representing a storage (DB, file...)
  interface IDatabase {

    // Create new table in database
    //
    // @tablename   Name of the table to be created
    // @structure   Associative array describing the table structure  (in form
    //              "fieldName" => "type:size"  where  type is either  "string"
    //              or "int", size is how many characters can be stored  here;
    //              size parameter is required only for string, int size is 11).
    //              Primary key has to be created automatically as column "_ID"
    // @return      True on success, false on failure
    public function createTable($tablename, $structure);

    // Get contents of table after filtering out unneeded records
    //
    // @table       Table name
    // @columns     Array of wanted columns' names ("_ID" for primary key)
    // @conditions  Array of associative arrays of form
    //              [
    //                "column"    => "columnName"
    //                "condition" => "equals|greater|lesser"
    //                "value"     => "valueToUseAsRightSideOfFormula"
    //              ]
    // @orderBy     Array with two strings:  the first is  column name and the
    //              second is  sort order  ("ASC"  or  "DESC").  Pass false to
    //              use default sorting
    // @return      Array containing query results (["columnName" => "value"])
    public function query($table, $columns, $conditions, $orderBy);

    // Insert data into database
    //
    // @table     Table name
    // @columns   Columns to insert data to
    // @values    Values of these columns (array of arrays)
    // @return    True on success, false otherwise
    public function insert($table, $columns, $values);

    // Modify data in database
    //
    // @table     Table name
    // @ids       IDs of records to be modified
    // @columns   Columns to be modified (array)
    // @values    Values of modified columns (array of arrays)
    // @return    True on success, false otherwise
    public function modify($table, $ids, $columns, $values);
  }

  // Primary class of this library, should be the only one used from outside
  class BakAPI {

    // Try to connect to server $server using either legacy or BakAPI client
    //
    // @server   String containing server URL
    // @return   BakAPI client able to connect to this server or null
    public static function checkServer($server) {
      $settings = \Markaos\BakAPI\Util::getSettings();
      foreach ($settings["clients"] as $client) {
        if(!class_exists($client)) continue;
        $client = new $client();
        if($client->checkAndStore($server)) {
          return $client;
        }
      }
      return NULL;
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

      // Make sure the UID is unique
      $data["uid"] =
        str_replace('\\', '_', get_class($client)) . "-" . $data["uid"];

      $uid = $data["uid"];
      $data = serialize($data);
      $lastCheck = time();

      $db = \Markaos\BakAPI\Util::getDatabase();

      // Check if this user already exists
      $res = $db->query("users", ["_ID"],
        [["column" => "UID", "condition" => "equals", "value" => $uid]], false);
      if($res === false || count($res) == 0) {
        // And create a new record if the user doesn't exist
        $columns = [
          "UID",
          "client",
          "data",
          "lastUpdateFast",
          "lastUpdateSlow"
        ];

        $values = [
          [
            $uid,
            get_class($client),
            $data,
            $lastCheck,
            $lastCheck
          ]
        ];

        $db->insert("users", $columns, $values);
      }

      return [
        "status" => true,
        "result" => $uid
      ];
    }

    // Get BakAPIClient associated with this user (ready to read from server)
    //
    // @user    UID to look for
    // @return  BakAPIClient connected to server using  user's  credentials or
    //          false on failure
    public static function getClient($user) {
      $db = \Markaos\BakAPI\Util::getDatabase();
      $columns = ["client", "data"];
      $conditions = [
        [
          "column" => "UID",
          "condition" => "equals",
          "value" => $user
        ]
      ];
      $result = $db->query("users", $columns, $conditions, false);
      if(count($result) > 1) {
        // TODO: report database corruption here
      } else if (count($result) < 1) {
        return false;
      }
      $client = new $result[0]["client"]();
      $client->reconstruct(unserialize($result[0]["data"]));
      return $client;
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
