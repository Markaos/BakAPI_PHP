<?php
namespace Markaos\BakAPI {
  
  class DataProvider {
    private $db;
    private $client;
    private $columns = ["data"];
    private $conditions = [
        [
          "column" => "UID",
          "condition" => "equals",
        ]
      ];
    
    public function __construct($db, $client) {
      $this->db = $db;
      $this->client = str_replace('\\', '_', get_class($client));
    }

    public function getData($uid) {
      $uid = $this->client . "-" . $uid;
      $this->conditions[0]["value"] = $uid;
      $result = $this->db->query(BAKAPI_TABLE_USERS, $this->columns, $this->conditions, false);
      if(count($result) > 0) {
        if(count($result) > 1) {
          Log::e("DataProvider", "More than one user with UID \"$uid\"");
        }
        return unserialize($result[0]["data"]);
      }
      return null;
    }
  }
}
?>