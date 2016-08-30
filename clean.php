<?php
require "bakapi.php";
$db = \Markaos\BakAPI\Util::getDatabase();
$threshold = \Markaos\BakAPI\Util::getSettings()["cleanup_threshold"];

\Markaos\BakAPI\Log::i("Cleanup", "Starting cleanup...");

if(get_class($db) == "Markaos\BakAPI\MySQLDatabase") {
  \Markaos\BakAPI\Log::i("Cleanup", "Using optimized MySQL query");
  $db = $db->getMySQLDatabase();

  $sql = "DELETE " . BAKAPI_TABLE_CHANGES . "
FROM   " . BAKAPI_TABLE_CHANGES . " NATURAL LEFT JOIN (
         SELECT   field_UID, MAX(_DATE) _DATE
         FROM     " . BAKAPI_TABLE_CHANGES . "
         GROUP BY field_UID
       ) t
WHERE  _DATE < CURRENT_DATE - INTERVAL $threshold DAY
   AND t._DATE IS NULL;";
} else {
  $columns = ["_DATE", "UID"];
  $conditions = [];
  $res = $db->query(BAKAPI_TABLE_CHANGES, $columns, $conditions, false);
  $users = array();
  foreach($res as $row) {
    if(!isset($users[$row["UID"]])) {
      $users[$row["UID"]] = $row["_DATE"];
    } else {
      $users[$row["UID"]] = \max($users[$row["UID"]], $row["_DATE"]);
    }
  }

  foreach($users as $user => $date) {
    $oldDate = time() - 60 * 60 * 24 * $threshold;
    if($date > $oldDate) {
      $conditions = [
        ["column" => "UID", "condition" => "equals", "value" => $user],
        ["column" => "_DATE", "condition" => "le", "value" => $oldDate]
      ];
      $db->remove(BAKAPI_TABLE_CHANGES, $conditions);
    } else {
      // Whoops... this user doesn't have any changes in last 7 days
      // Either it's holiday (and this branch should be fixed already) or
      // somebody forgot to fix it. Let's warn him...
      \Markaos\BakAPI\Log::critical("Cleanup",
        "User " . $user . " has no activity in last week and we don't use " .
        "MySQL database, so we use codepath with wrong implementation of this" .
        " (E_NOIMPL on IDatabase codepath)");
    }
  }
}

\Markaos\BakAPI\Log::i("Cleanup", "Finished");
?>
