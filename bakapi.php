<?php
require_once "BakAPI.php";

// It's time to load custom files
$settings = \Markaos\BakAPI\Util::getSettings();
if(isset($settings["include"])) {
  foreach ($settings["include"] as $file) {
    require_once $file;
  }
}

\Markaos\BakAPI\Util::initBakAPI();
?>
