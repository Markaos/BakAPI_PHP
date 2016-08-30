<?php
require "bakapi.php";

define("BAKAPI_LOADER_ERROR_FRONTEND", "1");
define("BAKAPI_LOADER_ERROR_INTERNAL", "2");

$settings = Markaos\BakAPI\Util::getSettings();
$frontend = "";
if(!isset($_GET["frontend"])) {
  $frontend = $settings["frontend_default"];
} else {
  $frontend = $_GET["frontend"];
}

if(!isset($settings["frontends"][$frontend])) {
  Markaos\BakAPI\Log::w("Loader",
    "Non-existent frontend requested: " . $frontend);
  echo BAKAPI_LOADER_ERROR_FRONTEND . " Frontend Not Available\n";
  exit(1);
}

try {
  $frontend = new $settings["frontends"][$frontend]();
} catch (Exception $e) {
  Markaos\BakAPI\Log::w("Loader",
    "Bad frontend class name in configuration: " . $frontend);
  echo BAKAPI_LOADER_ERROR_INTERNAL . " Internal Server Error\n";
  exit(2);
}

$frontend->handleRequest();
?>
