<?php
require "bakapi.php";
$settings = \Markaos\BakAPI\Util::getSettings();
$db = \Markaos\BakAPI\Util::getDatabase();

\Markaos\BakAPI\Log::i("Synchronization", "Checking for new data...");

$sectionsFast = explode(',', $settings["sync_fast"]);
$sectionsSlow = explode(',', $settings["sync_slow"]);
$fastTime = time() - $settings["sync_interval_fast"];
$slowTime = time() - $settings["sync_interval_slow"];
$columns = ["UID", "lastUpdateFast", "lastUpdateSlow"];
$conditions = [
  ["column" => "lastUpdateFast", "condition" => "le", "value" => $fastTime]
];

$users = $db->query(BAKAPI_TABLE_USERS, $columns, $conditions, false);
foreach($users as $user) {
  $slowUpdated = false;
  if($user["lastUpdateSlow"] <= $slowTime) {
    // Let's start with slow ring
    \Markaos\BakAPI\Log::i("Synchronization", "Synchronizing slow ring for " .
      $user["UID"]);
    \Markaos\BakAPI\BakAPI::syncData($user["UID"], $sectionsSlow);
    $slowUpdated = true;
  }
  \Markaos\BakAPI\Log::i("Synchronization", "Synchronizing fast ring for " .
    $user["UID"]);
  \Markaos\BakAPI\BakAPI::syncData($user["UID"], $sectionsFast);
  \Markaos\BakAPI\Log::i("Synchronization", "Data up-to-date for " .
    $user["UID"]);

  $columns = ["lastUpdateFast"];
  if($slowUpdated) $columns[] = "lastUpdateSlow";

  $values = [time()];
  if($slowUpdated) $values[] = time();

  $conditions = [
    ["column" => "UID", "condition" => "equals", "value" => $user["UID"]]
  ];

  $db->modify(BAKAPI_TABLE_USERS, $conditions, $columns, $values);
}

\Markaos\BakAPI\Log::i("Synchronization", "Check done");
?>
