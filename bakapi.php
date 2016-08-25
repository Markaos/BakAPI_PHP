<?php
require_once "BakAPI.php";

// It's time to load custom files
$settings = \Markaos\BakAPI\Util::getSettings();
foreach ($settings["include"] as $file) {
  require_once $file;
}
?>
