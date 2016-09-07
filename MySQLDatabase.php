<?php
namespace Markaos\BakAPI {
  // Simple MySQL implementation of IDatabase
  class MySQLDatabase implements \Markaos\BakAPI\IDatabase {
    private $db;

    public function __construct($purpose = "general") {
      $settings = \Markaos\BakAPI\Util::getSettings();
      $host = "";
      $db = "";
      $username = "";
      $password = "";
      if($purpose == "general") {
        $host = $settings["mysql_host"];
        $db = $settings["mysql_db"];
        $username = $settings["mysql_username"];
        $password = $settings["mysql_password"];
      } else if ($purpose == "log") {
        $host = isset($settings["mysql_host_log"]) ?
          $settings["mysql_host_log"] : $settings["mysql_host"];
        $db = isset($settings["mysql_db_log"]) ?
          $settings["mysql_db_log"] : $settings["mysql_db"];
        $username = isset($settings["mysql_username_log"]) ?
          $settings["mysql_username_log"] : $settings["mysql_username"];
        $password = isset($settings["mysql_password_log"]) ?
          $settings["mysql_password_log"] : $settings["mysql_password"];
      }

      $this->db = new \PDO(
        "mysql:host=" . $host . ";dbname=" . $db,
        $username,
        $password
      );
    }

    // This is ugly unsystematic hack for optimizing queries
    public function getMySQLDatabase() {
      return $this->db;
    }

    public function createTable($tablename, $structure) {
      $sql = "CREATE TABLE IF NOT EXISTS $tablename (
        _ID INT (11) AUTO_INCREMENT PRIMARY KEY,
        _DATE TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ";

      $tmp = true;
      foreach($structure as $key => $type) {
        if(!$tmp) $sql .= ", ";
        $tmp = false;
        $type = explode(':', $type . ":x");
        $sql .= "field_$key" . " " . ($type[0] == "int" ? "INT (11)" :
          "VARCHAR (" . $type[1] . ")");
      }
      $sql .= ");";

      $res = $this->db->exec($sql);
      return $res !== false;
    }

    public function query($table, $columns, $conditions, $orderBy, $limit = 0) {
      $sql = "SELECT ";
      $tmp = true;

      foreach ($columns as $col) {
        if(!$tmp) $sql .= ", ";
        $tmp = false;
        $col = $col == "_ID" ? $col : "field_" . $col;
        $sql .= $col;
      }

      $sql .= " FROM $table WHERE ";

      $values = array();
      $tmp = true;
      foreach ($conditions as $cond) {
        if(!$tmp) $sql .= " AND ";
        $tmp = false;
        $key = $cond["column"] == "_ID" ? "_ID" : "field_" . $cond["column"];
        $sql .= $key . " ";
        switch($cond["condition"]) {
          case "equals":
            $sql .= "=";
            break;
          case "l":
            $sql .= "<";
            break;
          case "g":
            $sql .= ">";
            break;
          case "ge":
            $sql .= ">=";
            break;
          case "le":
            $sql .= "<=";
            break;
          default:
            \Markaos\BakAPI\Log::critical("MySQL", "Unknown operator used: " .
              $cond["condition"]);
            throw new Exception("Unknown operator");
            break;
        }
        $sql .= " ?";
        $values[] = $cond["value"];
      }

      if($orderBy !== false) {
        $sql .= " ORDER BY $orderBy";
      }

      if($limit !== 0) {
        $sql .= " LIMIT $limit";
      }

      $query = $this->db->prepare($sql);
      if($query->execute($values) === false) {
        Log::critical("MySQL", "Query failed. SQL: \"$sql\", error: " . $query->errorInfo()[2]);
      }
      $result = $query->fetchAll();

      $r = array();
      foreach ($result as $row) {
        foreach($row as $key => $value) {
          if($key != "_ID" && $key != "_DATE") {
            unset($row[$key]);
            $row[substr($key, 6)] = $value;
          }
        }
        unset($row[0]);
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
        $sql .= "field_" . $col;
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

      $this->db->beginTransaction();
      $query = $this->db->prepare($sql);
      $res = $query->execute($vals);
      $this->db->commit();

      if($res === false) {
        \Markaos\BakAPI\Log::critical("MySQL",
          "Query failed! (\"$sql\", error: " . $query->errorInfo()[2] . ")");
      }
      return $res !== false;
    }

    public function modify($table, $conditions, $columns, $values) {
      $sql = "UPDATE $table SET ";

      $tmp = true;
      foreach($columns as $col) {
        if(!$tmp) $sql .= ", ";
        $tmp = false;
        if($col != "_ID" && $col != "_DATE") {
          $col = "field_$col";
        }
        $sql .= "$col = ?";
      }

      $sql .= " WHERE ";

      $vals = $values;
      $tmp = true;
      foreach ($conditions as $cond) {
        if(!$tmp) $sql .= " AND ";
        $tmp = false;
        $key = $cond["column"] == "_ID" ? "_ID" : "field_" . $cond["column"];
        $sql .= $key . " ";
        switch($cond["condition"]) {
          case "equals":
            $sql .= "=";
            break;
          case "l":
            $sql .= "<";
            break;
          case "g":
            $sql .= ">";
            break;
          case "ge":
            $sql .= ">=";
            break;
          case "le":
            $sql .= "<=";
            break;
          default:
            \Markaos\BakAPI\Log::critical("MySQL", "Unknown operator used: " .
              $cond["condition"]);
            throw new Exception("Unknown operator");
            break;
        }
        $sql .= " ?";
        $vals[] = $cond["value"];
      }

      $this->db->beginTransaction();
      $query = $this->db->prepare($sql);
      $res = $query->execute($vals);
      $this->db->commit();

      if($res === false) {
        \Markaos\BakAPI\Log::critical("MySQL", $query->errorInfo()[2]);
      }
      return $res !== false;
    }

    public function remove($table, $conditions) {
      $sql = "DELETE FROM $table WHERE ";

      $values = array();
      $tmp = true;
      foreach ($conditions as $cond) {
        if(!$tmp) $sql .= " AND ";
        $tmp = false;
        $key = $cond["column"] == "_ID" ? "_ID" : "field_" . $cond["column"];
        $sql .= $key . " ";
        switch($cond["condition"]) {
          case "equals":
            $sql .= "=";
            break;
          case "l":
            $sql .= "<";
            break;
          case "g":
            $sql .= ">";
            break;
          case "ge":
            $sql .= ">=";
            break;
          case "le":
            $sql .= "<=";
            break;
          default:
            \Markaos\BakAPI\Log::critical("MySQL", "Unknown operator used: " .
              $cond["condition"]);
            throw new Exception("Unknown operator");
            break;
        }
        $sql .= " ?";
        $values[] = $cond["value"];
      }

      $this->db->beginTransaction();
      $query = $this->db->prepare($sql);
      $res = $query->execute($values);
      $this->db->commit();

      if($res === false) {
        \Markaos\BakAPI\Log::critical("MySQL",
          "Query failed! (\"$sql\", error: " . $query->errorInfo()[2] . ")");
      }
      return $res !== false;
    }
  }
}
?>
