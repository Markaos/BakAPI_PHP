<?php
namespace Markaos\BakAPI {
  require_once "bakapi.php";
  require_once "Web/Util.php";
  require_once "Web/ContentBuilder.php";
  require_once "Web/BasePage.php";
  require_once "Web/Registration.php";
  require_once "Web/MainPage.php";
  require_once "Web/TimetablePage.php";
  require_once "Web/GradesPage.php";
  require_once "Web/HomeworkPage.php";
  require_once "Web/SubjectsPage.php";

  session_start();

  class WebFrontend implements \Markaos\BakAPI\IFrontend {

    public function handleRequest() {
      $db = $this->getDatabase();
      if($db === false) {
        \Markaos\BakAPI\Web\Registrator::handleRequest($this, false, false);
        return;
      }

      if(isset($_GET["logout"])) {
        unset($_SESSION["UID"]);
        unset($_SESSION["name"]);
        unset($_SESSION["server"]);
        $this->handleRequest();
        return;
      }

      if(isset($_GET["sync"])) {
        \Markaos\BakAPI\BakAPI::syncData($_SESSION["UID"]);
        $db = $this->getDatabase();
      }

      if(!isset($_GET["action"])) {
        \Markaos\BakAPI\Web\MainPage::handleRequest($this, $db, $_SESSION["UID"]);
      } else if ($_GET["action"] == "timetable") {
        \Markaos\BakAPI\Web\TimetablePage::handleRequest($this, $db, $_SESSION["UID"]);
      } else if ($_GET["action"] == "grades") {
        \Markaos\BakAPI\Web\GradesPage::handleRequest($this, $db, $_SESSION["UID"]);
      } else if ($_GET["action"] == "homework") {
        \Markaos\BakAPI\Web\HomeworkPage::handleRequest($this, $db, $_SESSION["UID"]);
      } else if ($_GET["action"] == "subjects") {
        \Markaos\BakAPI\Web\SubjectsPage::handleRequest($this, $db, $_SESSION["UID"]);
      }
    }

    private function getDatabase() {
      if(!isset($_SESSION["UID"])) {
        // Not registered yet
        return false;
      }

      return \Markaos\BakAPI\BakAPI::getFullDatabase($_SESSION["UID"]);
    }
  }
}
?>
