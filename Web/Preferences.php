<?php
namespace Markaos\BakAPI\Web {

  class Preferences {
    private $uid;
    private $db;

    public function __construct($uid) {
      $db = \Markaos\BakAPI\Util::getDatabase();
      if(get_class($db) == "Markaos\BakAPI\MySQLDatabase") {
        $d = $db->getMySQLDatabase();
        $d->exec("CREATE TABLE IF NOT EXISTS WebSettings (
          _ID INT AUTO_INCREMENT,
          _DATE TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          field_uid VARCHAR(128),
          field_key VARCHAR(64),
          field_value VARCHAR(256),
          PRIMARY KEY(field_uid, field_key),
          KEY _ID (_ID))");
      } else {
        $db->createTable("WebSettings", [
          "uid"   => "string:128",
          "key"   => "string:64",
          "value" => "string:256"
        ]);
      }

      $this->db = $db;
      $this->uid = $uid;
    }

    public function setUid($uid) {
      $this->uid = $uid;
    }

    public function getValue($name, $default) {
      $cols = ["value"];
      $conds = [
        ["column" => "key", "condition" => "equals", "value" => $name],
        ["column" => "uid", "condition" => "equals", "value" => $this->uid]
      ];
      $res = $this->db->query("WebSettings", $cols, $conds, false);
      if(!is_array($res) || count($res) < 1) {
        $this->setValue($name, $default);
        return $default;
      }

      return $res[0]["value"];
    }

    public function setValue($name, $value) {
      if(get_class($this->db) == "Markaos\BakAPI\MySQLDatabase") {
        $db = $this->db->getMySQLDatabase();
        $uid = $this->uid;
        $s = $db->prepare("INSERT INTO WebSettings (field_uid, field_key, field_value)
          VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE field_value = VALUES(field_value)");
        $s->execute([$uid, $name, $value]);
      } else {
        $conds = [
          ["column" => "key", "condition" => "equals", "value" => $name],
          ["column" => "uid", "condition" => "equals", "value" => $this->uid]
        ];
        $cols = ["uid", "key", "value"];
        $vals = [$this->uid, $name, $value];

        $res = $this->db->query("WebSettings", $cols, $conds, false);
        if(count($res) < 1) {
          $this->db->insert("WebSettings", $cols, [$vals]);
        } else {
          $this->db->modify("WebSettings", $conds, $cols, $vals);
        }
      }
    }
  }
}
?>
