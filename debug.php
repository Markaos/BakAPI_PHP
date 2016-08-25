<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <title>BakAPI</title>
</head>
<body>
<?php
include("bakapi.php");

if (ob_get_level())
   ob_end_clean();

if(!isset($_GET["formok"])) {
  ?>
  <form>
    <label for="server">Server:</label>
    <input name="server" value="https://bakalari.gfpvm.cz" />
    <br>
    <label for="name">Jméno:</label>
    <input name="name" />
    <br>
    <label for="password">Heslo:</label>
    <input name="password" type="password" />
    <input type="hidden" name="formok" value="y" />
    <br>
    <label for="action">Akce:</label>
    <input name="action" />
    <br>
    <input type="submit" value="Odeslat" />
  </form>
</body>
</html>
  <?php
  exit(0);
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre>";
$client = new \Markaos\BakAPI\LegacyClient();
$client->checkAndStore($_GET["server"]);
$client->connect($_GET["name"], $_GET["password"]);
echo $client->debug($_GET["action"]);
?>
  </pre>
</body>
</html>
