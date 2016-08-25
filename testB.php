<pre>
<?php
require "bakapi.php";

echo "Trying to register...\n";

$res = \Markaos\BakAPI\BakAPI::register($_GET["server"], $_GET["username"], $_GET["password"]);
if(!$res["status"]) {
  echo "  <b>Failed!</b> (" . $res["result"] . ")\n";
  exit(1);
}

echo "  <b>" . $res["result"] . "</b>\n";
echo "Logging in...\n";
$client = \Markaos\BakAPI\BakAPI::getClient($res["result"]);
echo "  <b>" . (is_object($client) ? get_class($client) : "Failed") . "</b>\n";
?>
</pre>
