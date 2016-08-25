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
      $sql = "SELECT ";
      $tmp = true;

      foreach ($columns as $col) {
        if(!$tmp) $sql .= ", ";
        $tmp = false;
        $sql .= $col;
      }

      $sql .= " WHERE ";

      $values = array();
      $tmp = true;
      foreach ($conditions as $cond) {
        if(!$tmp) $sql .= " AND ";
        $tmp = false;
        $sql .= $cond["column"] . " ";
        switch($cond["condition"]) {
          case "equals":
            $sql .= "LIKE";
            break;
          case "lesser":
            $sql .= "<";
            break;
          case "greater":
            $sql .= ">";
            break;
          default:
            throw new Exception("Unknown operator");
            break;
        }
        $sql .= " ?";
        $values[] = $cond["value"];
      }

      if($orderBy !== false) {
        $sql .= " $orderBy";
      }

      $query = $this->db->prepare($sql);
      $result = $query->execute($values);

      $r = array();
      foreach ($result as $row) {
        $r[] = $row;
      }

      return $r;
    }

    public function insert($table, $columns, $values) {
      $rowsCount = count($values);
      $colsCount = count($columns);

      $sql = "INSERT INTO $table (";
      $tmp = true;
      foreach($columns as $col) {
        if(!$tmp) $sql .= ", ";
        $tmp = false;
        $sql .= $col;
      }
      $sql .= ") VALUES ";

      $vals = array();

      for($i = 0; $i < $rowsCount; $i++) {
        $sql .= "(";
        $tmp = true;
        for($j = 0; $j < $colsCount; $j++) {
          if(!$tmp) $sql .= ", ";
          $tmp = false;
          $sql .= "?";
          $vals[] = $values[$i][$j];
        }
        $sql .= ")";
      }

      $query = $this->db->prepare($sql);
      $res = $query->execute($vals);
      $this->db->commit();

      return $res !== false;
    }

    public function modify($table, $ids, $columns, $values) {
      // TODO: stub
    }
  }
}
?>
