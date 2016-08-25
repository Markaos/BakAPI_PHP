<?php
namespace Markaos\BakAPI {
  // Simple MySQL implementation of IDatabase
  class MySQLDatabase implements \Markaos\BakAPI\IDatabase {
    private $db;

    public function __construct() {
      $settings = \Markaos\BakAPI\Util::getSettings();
      $this->db = new \PDO(
        "mysql:host=" . $settings["mysql_host"] . ";dbname=" . $settings["mysql_db"],
        $settings["mysql_username"],
        $settings["mysql_password"]
      );
    }

    public function createTable($tablename, $structure) {
      $sql = "CREATE TABLE IF NOT EXISTS $tablename (
        _ID INT (11) AUTO_INCREMENT PRIMARY KEY, ";

      foreach($structure as $key => $type) {
        $type = explode(':', $type . ":x");
        $sql .= $key . " " . $type[0] == "int" ? "INT (11)" :
          "TEXT (" . $type[1] . ")" . ", ";
      }
      $sql .= ");";

      return $db->exec($sql) !== false;
    }

    public function query($table, $columns, $conditions, $orderBy) {
      // TODO: stub
    }

    public function insert($table, $columns, $values) {
      // TODO: stub
    }

    public function modify($table, $ids, $columns, $values) {
      // TODO: stub
    }
  }
}
?>