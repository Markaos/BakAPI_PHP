<?php
if (ob_get_level())
   ob_end_clean();

echo "<pre>";

require "bakapi.php";
require "TestUtil.php";

\Markaos\BakAPI\Log::i(basename(__FILE__), "Running database test...");

echo "Trying to register...\n";
flush();

$res = \Markaos\BakAPI\BakAPI::register($_GET["server"], $_GET["username"], $_GET["password"]);
if(!$res["status"]) {
  echo "  <b>Failed!</b> (" . $res["result"] . ")\n";
  exit(1);
}

echo "  <b>" . $res["result"] . "</b>\n";
echo "Logging in...\n";
flush();
$client = \Markaos\BakAPI\BakAPI::getClient($res["result"]);
echo "  <b>" . (is_object($client) ? get_class($client) : "Failed") . "</b>\n";
echo "Synchronizing...\n";
flush();
\Markaos\BakAPI\BakAPI::syncData($res["result"]);
$data = \Markaos\BakAPI\BakAPI::getFullDatabase($res["result"]);
echo "  <b>Done</b> (TransactionID=" . $data["transaction"] . ")\n";
echo "Loading timetable...\n\n";
flush();
$cycleNow = $data[BAKAPI_SECTION_TIMETABLE_CYCLES][0];
$cycleNext = $data[BAKAPI_SECTION_TIMETABLE_CYCLES][1];
$cycleThird = $data[BAKAPI_SECTION_TIMETABLE_CYCLES][2];
$timetable = TestUtil::merge_timetable($data[BAKAPI_SECTION_TIMETABLE_STABLE],
  $data[BAKAPI_SECTION_TIMETABLE_OVERLAY], $cycleNow, $cycleNext);
TestUtil::print_timetable($timetable, $data[BAKAPI_SECTION_TIMETABLE_CAPTIONS],
  $cycleNow);
echo "\n";

$timetable = TestUtil::merge_timetable($data[BAKAPI_SECTION_TIMETABLE_STABLE],
  $data[BAKAPI_SECTION_TIMETABLE_OVERLAY], $cycleNext, $cycleThird);
TestUtil::print_timetable($timetable, $data[BAKAPI_SECTION_TIMETABLE_CAPTIONS],
  $cycleNext);
echo "\n";
?>
</pre>
