<?php
namespace Markaos\BakAPI {
  require_once "util.php";
  require_once "DiffUtil.php";
  require_once "LogUtil.php";
  require_once "ProxyClient.php";
  require_once "LegacyClient.php";
  require_once "MySQLDatabase.php";
  require_once "WebFrontend.php";
  require_once "JSONFrontend.php";

  define("BAKAPI_STATUS_OK", "01");
  define("BAKAPI_STATUS_ERROR", "02");

  define("BAKAPI_SECTION_GRADES", "grades");
  define("BAKAPI_SECTION_SUBJECTS", "subjects");
  define("BAKAPI_SECTION_MESSAGES", "messages");
  define("BAKAPI_SECTION_EVENTS", "events");
  define("BAKAPI_SECTION_HOMEWORK", "homework");
  define("BAKAPI_SECTION_TIMETABLE_STABLE", "timetable_stable");
  define("BAKAPI_SECTION_TIMETABLE_OVERLAY", "timetable_overlay");
  define("BAKAPI_SECTION_TIMETABLE_CYCLES", "timetable_cycles");
  define("BAKAPI_SECTION_TIMETABLE_CAPTIONS", "timetable_captions");
  define("BAKAPI_SECTION_TIMETABLE_THEMES", "timetable_themes");

  define("BAKAPI_TABLE_CHANGES", "changes");
  define("BAKAPI_TABLE_USERS", "users");

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
    //            user belongs to  (1.A),  "version" for server version,  "uid"
    //            for user ID (has to be unique for this client) and "updating"
    //            for support of delta-updates (boolean, see Wiki) or false on
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

    // Load changes from server (only called if data["updating"] is true)
    //
    // @return    Array containing changes or false on failure
    public function update();
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
    //                "condition" => "equals|g|l|ge|le"
    //                "value"     => "valueToUseAsRightSideOfFormula"
    //              ]
    // @orderBy     Array with two strings:  the first is  column name and the
    //              second is  sort order  ("ASC"  or  "DESC").  Pass false to
    //              use default sorting
    // @return      Array containing query results (["columnName" => "value"])
    public function query($table, $columns, $conditions, $orderBy, $limit = 0);

    // Insert data into database
    //
    // @table     Table name
    // @columns   Columns to insert data to
    // @values    Values of these columns (array of arrays)
    // @return    True on success, false otherwise
    public function insert($table, $columns, $values);

    // Modify data in database
    //
    // @table       Table name
    // @conditions  Rows matching these conditions will be modified
    // @columns     Columns to be modified (array)
    // @values      Values of modified columns (array)
    // @return      True on success, false otherwise
    public function modify($table, $conditions, $columns, $values);

    // Remove data from database
    //
    // @table       Table name
    // @conditions  Rows matching these conditions will be removed
    // @return      True on success, false otherwise
    public function remove($table, $conditions);
  }

  // Interface for frontends (methods for communicating with user)
  // Expected implementations are web frontend and JSON fronted (primary)
  interface IFrontend {

    // Handle user request. User's destiny is in your hands
    public function handleRequest();
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
      $res = $db->query(BAKAPI_TABLE_USERS, ["_ID"],
        [["column" => "UID", "condition" => "equals", "value" => $uid]], false);
      if($res === false || count($res) == 0) {
        // And create a new record if the user doesn't exist
        Log::i("Registration", "Creating new user with UID $uid");
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

        $db->insert(BAKAPI_TABLE_USERS, $columns, $values);
        self::syncData($uid);
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
      $result = $db->query(BAKAPI_TABLE_USERS, $columns, $conditions, false);
      if(count($result) > 1) {
        \Markaos\BakAPI\Log::e("BakAPI",
          "Database contains more than one user with UID \"$user\"");
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
    // @user      User ID
    // @sections  Array containing section names or "ALL" to load all sections
    // @return    True if everything went OK, false otherwise
    public static function syncData($user, $sections = "ALL") {
      // How will  we do that?  It's simple,  we'll  just  load  client (using
      // getClient()),  make array  from database and  do some magic  to merge
      // these two while saving all changes  we made to allow users to  simply
      // patch their local database. Good luck

      // Enough talking, let's make some code
      $client = BakAPI::getClient($user);
      // Good beginning
      if($client === false) {
        // This user isn't registered yet
        Log::w("Synchronization",
          "Trying to synchronize data for non-existent user ($user)");
        return false;
      }

      $newData = array();
      if($sections === "ALL") {
        $newData = $client->load(implode(',', [
            BAKAPI_SECTION_GRADES,
            BAKAPI_SECTION_SUBJECTS,
            BAKAPI_SECTION_MESSAGES,
            BAKAPI_SECTION_EVENTS,
            BAKAPI_SECTION_HOMEWORK,
            BAKAPI_SECTION_TIMETABLE_STABLE,
            BAKAPI_SECTION_TIMETABLE_OVERLAY,
            BAKAPI_SECTION_TIMETABLE_CYCLES,
            BAKAPI_SECTION_TIMETABLE_CAPTIONS,
            BAKAPI_SECTION_TIMETABLE_THEMES
          ])
        );
      } else {
        $newData = $client->load(implode(',', $sections));
      }
      // New data ready - this didn't even hurt

      $db = BakAPI::getFullDatabase($user);
      $oldData = array();
      if(is_array($sections)) {
        foreach($sections as $section) {
          $oldData[$section] = $db[$section];
        }
      } else {
        $oldData = $db;
      }

      // Merging offloaded to DiffUtil
      $diffs = \Markaos\BakAPI\DiffUtil::getDifferencesBakAPI($oldData, $newData);
      $db = \Markaos\BakAPI\Util::getDatabase();
      $columns = [
        "UID",
        "serialized"
      ];

      $values = [
        [
          $user,
          ""
        ]
      ];

      while(list(, $diff) = each($diffs)) {
        $values[0][1] = serialize($diff);
        $db->insert(BAKAPI_TABLE_CHANGES, $columns, $values);
        $diff["data"]["UID"] = $user;

        if($diff["type"] == "a") {
          \Markaos\BakAPI\Util::insertArrayIntoDatabase($db,
            $diff["table"], $diff["data"]);
          \Markaos\BakAPI\Log::i("Synchronization", "Applying change to " .
            $diff["table"] . " (addition)");
        } else if ($diff["type"] == "r") {
          \Markaos\BakAPI\Util::removeArrayFromDatabase($db,
            $diff["table"], $diff["data"]);
          \Markaos\BakAPI\Log::i("Synchronization", "Applying change to " .
            $diff["table"] . " (removal)");
        }
      }

      return true;
    }

    // Get list of changes since last known change
    //
    // @user              User ID
    // @lastKnownChange   ID of the last transaction client knows about
    // @return            Array with modifications  -  refer to documentation
    //                    for exact format
    public static function getChanges($user, $lastKnownChange) {
      $db = \Markaos\BakAPI\Util::getDatabase();
      $columns = ["serialized"];
      $conditions = [
        ["column" => "UID", "condition" => "equals", "value" => $user],
        ["column" => "_ID", "condition" => "g", "value" => $lastKnownChange]
      ];
      return $db->query(BAKAPI_TABLE_CHANGES, $columns, $conditions);
    }

    // Get up-to-date database for this user. Called in case the server doesn't
    // remember the last change known to client or when synchronizing data with
    // remote
    //
    // @user    User ID
    // @return  Array with full database - more in documentation
    public static function getFullDatabase($user) {
      $db = \Markaos\BakAPI\Util::getDatabase();
      $data = array();
      $data[BAKAPI_SECTION_GRADES] = array();
      $data[BAKAPI_SECTION_SUBJECTS] = array();
      $data[BAKAPI_SECTION_MESSAGES] = array();
      $data[BAKAPI_SECTION_EVENTS] = array();
      $data[BAKAPI_SECTION_HOMEWORK] = array();
      $data[BAKAPI_SECTION_TIMETABLE_STABLE] = array();
      $data[BAKAPI_SECTION_TIMETABLE_OVERLAY] = array();
      $data[BAKAPI_SECTION_TIMETABLE_CYCLES] = array();
      $data[BAKAPI_SECTION_TIMETABLE_CAPTIONS] = array();
      $data[BAKAPI_SECTION_TIMETABLE_THEMES] = array();

      $columns = ["_ID"];
      $conditions = [
        ["column" => "UID", "condition" => "equals", "value" => $user]
      ];
      $orderBy = "_ID DESC";
      $tmp = $db->query(BAKAPI_TABLE_CHANGES, ["_ID"], $conditions, $orderBy, 1);
      $data["transaction"] = $tmp[0]["_ID"];

      // Let's start with grades
      $columns = [
        "subject",
        "title",
        "description",
        "grade",
        "weight",
        "date"
      ];

      $conditions = [
        [
          "column" => "UID",
          "condition" => "equals",
          "value" => $user
        ]
      ];

      $tmp = $db->query("grades", $columns, $conditions, "_ID ASC");
      foreach($tmp as $grade) {
        $grade["weight"] = (int) $grade["weight"];
        $grade["date"] = (int) $grade["date"];
        $data[BAKAPI_SECTION_GRADES][] = $grade;
      }

      // Now subjects
      $columns = [
        "name",
        "teachers",
        "emails",
        "short"
      ];

      $conditions = [
        [
          "column" => "UID",
          "condition" => "equals",
          "value" => $user
        ]
      ];

      $tmp = $db->query("subjects", $columns, $conditions, "_ID ASC");
      foreach($tmp as $subject) {
        $data[BAKAPI_SECTION_SUBJECTS][] = $subject;
      }

      // Messages...
      $columns = [
        "from",
        "contents",
        "sysid",
        "date"
      ];

      $conditions = [
        [
          "column" => "UID",
          "condition" => "equals",
          "value" => $user
        ]
      ];

      $tmp = $db->query("messages", $columns, $conditions, "_ID ASC");
      foreach($tmp as $message) {
        $message["date"] = (int) $message["date"];
        $data[BAKAPI_SECTION_MESSAGES][] = $message;
      }

      // And events
      $columns = [
        "name",
        "description",
        "timerange",
        "rooms",
        "teachers",
        "classes",
        "show",
        "date"
      ];

      $conditions = [
        [
          "column" => "UID",
          "condition" => "equals",
          "value" => $user
        ]
      ];

      $tmp = $db->query(BAKAPI_SECTION_EVENTS, $columns, $conditions, "_ID ASC");
      foreach($tmp as $event) {
        $event["date"] = (int) $event["date"];
        $event["show"] = (int) $event["show"];
        $data[BAKAPI_SECTION_EVENTS][] = $event;
      }

      // Homework...
      $columns = [
        "subject",
        "issued",
        "deadline",
        "state",
        "description"
      ];

      $conditions = [
        [
          "column" => "UID",
          "condition" => "equals",
          "value" => $user
        ]
      ];

      $tmp = $db->query(BAKAPI_SECTION_HOMEWORK,
        $columns, $conditions, "_ID ASC");
      foreach($tmp as $homework) {
        $homework["issued"] = (int) $homework["issued"];
        $homework["deadline"] = (int) $homework["deadline"];
        $data[BAKAPI_SECTION_HOMEWORK][] = $homework;
      }

      // Stable timetable...
      $columns = [
        "caption",
        "day",
        "type",
        "short",
        "steacher",
        "teacher",
        "shortRoom",
        "shortGroup",
        "group",
        "cycle"
      ];

      $conditions = [
        [
          "column" => "UID",
          "condition" => "equals",
          "value" => $user
        ]
      ];

      $tmp = $db->query(BAKAPI_SECTION_TIMETABLE_STABLE,
        $columns, $conditions, "_ID ASC");
      foreach($tmp as $lesson) {
        $data[BAKAPI_SECTION_TIMETABLE_STABLE][] = $lesson;
      }

      // Timetable overlay...
      $columns = [
        "caption",
        "day",
        "type",
        "short",
        "steacher",
        "teacher",
        "shortRoom",
        "shortGroup",
        "group",
        "date"
      ];

      $conditions = [
        [
          "column" => "UID",
          "condition" => "equals",
          "value" => $user
        ]
      ];

      $tmp = $db->query(BAKAPI_SECTION_TIMETABLE_OVERLAY,
        $columns, $conditions, "_ID ASC");
      foreach($tmp as $lesson) {
        $lesson["date"] = (int) $lesson["date"];
        $data[BAKAPI_SECTION_TIMETABLE_OVERLAY][] = $lesson;
      }

      // Timetable cycles...
      $columns = [
        "mondayDate",
        "cycle"
      ];

      $conditions = [
        [
          "column" => "UID",
          "condition" => "equals",
          "value" => $user
        ]
      ];

      $tmp = $db->query(BAKAPI_SECTION_TIMETABLE_CYCLES,
        $columns, $conditions, "_ID ASC");
      foreach($tmp as $cycle) {
        $cycle["mondayDate"] = (int) $cycle["mondayDate"];
        $data[BAKAPI_SECTION_TIMETABLE_CYCLES][] = $cycle;
      }

      // And timetable captions
      $columns = [
        "caption",
        "begin",
        "end"
      ];

      $conditions = [
        [
          "column" => "UID",
          "condition" => "equals",
          "value" => $user
        ]
      ];

      $tmp = $db->query(BAKAPI_SECTION_TIMETABLE_CAPTIONS,
        $columns, $conditions, "_ID ASC");
      foreach($tmp as $caption) {
        $data[BAKAPI_SECTION_TIMETABLE_CAPTIONS][] = $caption;
      }

      // We almost forgot to load themes!
      $columns = [
        "date",
        "caption",
        "theme"
      ];

      $conditions = [
        [
          "column" => "UID",
          "condition" => "equals",
          "value" => $user
        ]
      ];

      $tmp = $db->query(BAKAPI_SECTION_TIMETABLE_THEMES,
        $columns, $conditions, "_ID ASC");
      foreach($tmp as $theme) {
        $theme["date"] = (int) $theme["date"];
        $data[BAKAPI_SECTION_TIMETABLE_THEMES][] = $theme;
      }

      $data["hash"] = self::getFullDatabaseHash($data);

      return $data;
    }

    // Get checksum of the database. Used for veryfying patching process
    //
    // @user    User ID
    // @return  Database checksum
    public static function getFullDatabaseHash($db) {
      $grades = DiffUtil::getChecksum($db, BAKAPI_SECTION_GRADES);
      $subjects = DiffUtil::getChecksum($db, BAKAPI_SECTION_SUBJECTS);
      $messages = DiffUtil::getChecksum($db, BAKAPI_SECTION_MESSAGES);
      $events = DiffUtil::getChecksum($db, BAKAPI_SECTION_EVENTS);
      $homework = DiffUtil::getChecksum($db, BAKAPI_SECTION_HOMEWORK);
      $timeStable = DiffUtil::getChecksum($db, BAKAPI_SECTION_TIMETABLE_STABLE);
      $timeOver = DiffUtil::getChecksum($db, BAKAPI_SECTION_TIMETABLE_OVERLAY);
      $timeCycles = DiffUtil::getChecksum($db, BAKAPI_SECTION_TIMETABLE_CYCLES);
      $timeCap = DiffUtil::getChecksum($db, BAKAPI_SECTION_TIMETABLE_CAPTIONS);
      return \hash("sha256",  $grades . $subjects . $messages . $events .
                              $homework . $timeStable . $timeOver .
                              $timeCycles . $timeCap);
    }
  }
}
?>
