<?php
namespace Markaos\BakAPI {
  require_once "bakapi.php";

  // This client is used to connect to official ("legacy") servers
  class LegacyClient implements \Markaos\BakAPI\IClient {
    private $server = null;
    private $hash = null;
    private $data = null;
    private $timetableCache = null;
    private $subjectsCache = null;

    public function debug($action) {
      $store = \Markaos\BakAPI\Util::loadPage($this->server .
        "/login.aspx?hx=" . $this->hash . "&pm=$action");

      $dom = new \DOMDocument();
      $dom->preserveWhiteSpace = false;
      $dom->loadXML($store);
      $dom->formatOutput = true;
      return \htmlspecialchars($dom->saveXml());
    }

    public function checkAndStore($server) {
      $store = \Markaos\BakAPI\Util::loadPage($server . "/login.aspx?gethx=null");

      \libxml_use_internal_errors(true);
      $xml = \simplexml_load_string($store);

      if($xml === false) {
        return false;
      }

      if((string) $xml->res == BAKAPI_STATUS_ERROR) {
        $this->server = $server;
        return true;
      }
    }

    public function connect($name, $password) {
      $store = \Markaos\BakAPI\Util::loadPage($this->server . "/login.aspx?gethx=$name");

      \libxml_use_internal_errors(true);
      $xml = \simplexml_load_string($store);
      if($xml === false || !((string) $xml->res == BAKAPI_STATUS_OK)) {
        return false;
      }

      $type = (string) $xml->typ;
      $internalCode = (string) $xml->ikod;
      $salt = (string) $xml->salt;

      $passHash = base64_encode(hash("sha512", $salt . $internalCode . $type . $password, true));
      $passHash = "*login*" . $name . "*pwd*" . $passHash . "*sgn*ANDR";

      $loginHash = base64_encode(hash("sha512", $passHash . date("Ymd"), true));
      $loginHash = str_replace(['\\', '/', '+'], ['_', '_', '-'], $loginHash);
      $this->hash = $loginHash;

      $store = \Markaos\BakAPI\Util::loadPage($this->server .
        "/login.aspx?hx=$loginHash&pm=login");

      \libxml_use_internal_errors(true);
      $xml = \simplexml_load_string($store);
      if($xml === false || !((string) $xml->result == BAKAPI_STATUS_OK)) {
        return false;
      }

      $uname = $name;
      $name = (string) $xml->jmeno;
      $cls = substr($name, strpos($name, ',') + 2);
      $name = substr($name, 0, strpos($name, ','));
      $name = explode(' ', $name);
      $name = $name[1] . " " . $name[0];

      $version = (string) $xml->verze;

      $this->data = [
        "name"    => $name,
        "class"   => $cls,
        "version" => $version,
        "token"   => $passHash,
        "server"  => $this->server,
        "uid"     => str_replace(['/', '\\', ':'], ['_', '_', '_'],
                       $uname . "@" . $this->server)
      ];

      return $this->getData();
    }

    public function reconstruct($data, $verify = false) {
      $loginHash = base64_encode(hash("sha512", $data["token"] . date("Ymd"), true));
      $loginHash = str_replace(['\\', '/', '+'], ['_', '_', '-'], $loginHash);

      $this->server = $data["server"];
      $this->hash = $loginHash;
      $this->data = $data;

      if($verify) {
        $store = \Markaos\BakAPI\Util::loadPage($this->server .
          "/login.aspx?hx=$" . data["token"] . "&pm=login");

        \libxml_use_internal_errors(true);
        $xml = \simplexml_load_string($store);
        if($xml === false || !((string) $xml->result == BAKAPI_STATUS_OK)) {
          return false;
        }
      }
    }

    public function getData() {
      return $this->data;
    }

    public function load($sections) {
      $sections = explode(',', $sections);
      $rArr = [];
      foreach($sections as $section) {
        switch($section) {
          case BAKAPI_SECTION_GRADES:
            $rArr[BAKAPI_SECTION_GRADES] = $this->loadGrades();
            break;
          case BAKAPI_SECTION_SUBJECTS:
            $rArr[BAKAPI_SECTION_SUBJECTS] = $this->loadSubjects();
            break;
          case BAKAPI_SECTION_MESSAGES:
            $rArr[BAKAPI_SECTION_MESSAGES] = $this->loadMessages();
            break;
          case BAKAPI_SECTION_EVENTS:
            $rArr[BAKAPI_SECTION_EVENTS] = $this->loadEvents();
            break;
          case BAKAPI_SECTION_HOMEWORK:
            $rArr[BAKAPI_SECTION_HOMEWORK] = $this->loadHomework();
            break;
          case BAKAPI_SECTION_TIMETABLE_STABLE:
            $rArr[BAKAPI_SECTION_TIMETABLE_STABLE] = $this->loadStableTimetable();
            break;
          case BAKAPI_SECTION_TIMETABLE_CAPTIONS:
            $rArr[BAKAPI_SECTION_TIMETABLE_CAPTIONS] = $this->loadTimetableCaptions();
            break;
          case BAKAPI_SECTION_TIMETABLE_OVERLAY:
            $rArr[BAKAPI_SECTION_TIMETABLE_OVERLAY] = $this->loadTimetableOverlay();
            break;
          case BAKAPI_SECTION_TIMETABLE_CYCLES:
            $rArr[BAKAPI_SECTION_TIMETABLE_CYCLES] = $this->loadTimetableCycles();
            break;
        }
      }
      return $rArr;
    }

    private function loadGrades() {
      $store = \Markaos\BakAPI\Util::loadPage($this->server .
        "/login.aspx?hx=" . $this->hash . "&pm=znamky");

      \libxml_use_internal_errors(true);
      $xml = \simplexml_load_string($store);
      if($xml === false || !((string) $xml->result == BAKAPI_STATUS_OK)) {
        return false;
      }

      // We need subjects table to determine shortened name for subject
      $subjects = $this->loadSubjects();

      $arr = array();
      foreach($xml->predmety->children() as $subject) {
        // Get subject shortcut
        $sub = "";
        foreach($subjects as $k => $subj) {
          if($subj["name"] == (string) $subject->jmeno) {
            $sub = $subj["short"];
            unset($subjects[$k]);
            break;
          }
        }

        foreach($subject->znamky->children() as $grade) {
          $arr[] = [
            "subject"     => (string) $sub,
            "title"       => (string) $grade->caption,
            "description" => (string) $grade->notice,
            "grade"       => (string) $grade->znamka,
            "weight"      => (int)    $grade->vaha,
            "date"        => (int)    \strtotime((string) $grade->udeleno)
          ];
        }
      }

      return $arr;
    }

    private function loadSubjects() {
      if($this->subjectsCache !== null) {
        return $this->subjectsCache;
      }

      $store = \Markaos\BakAPI\Util::loadPage($this->server .
        "/login.aspx?hx=" . $this->hash . "&pm=predmety");

      \libxml_use_internal_errors(true);
      $xml = \simplexml_load_string($store);
      if($xml === false || !((string) $xml->result == BAKAPI_STATUS_OK)) {
        return false;
      }

      $arr = [];
      foreach ($xml->predmety->children() as $subject) {
        $arr[] = [
          "name" => (string) $subject->nazev,
          "teacher" => (string) $subject->ucitel,
          "teacherEmail" => (string) $subject->mailuc,
          "short" => (string) $subject->zkratka
        ];
      }
      $this->subjectsCache = $arr;
      return $arr;
    }

    private function loadMessages() {
      $store = \Markaos\BakAPI\Util::loadPage($this->server .
        "/login.aspx?hx=" . $this->hash . "&pm=znamky");

      \libxml_use_internal_errors(true);
      $xml = \simplexml_load_string($store);
      if($xml === false || !((string) $xml->result == BAKAPI_STATUS_OK)) {
        return false;
      }

      $arr = array();
      foreach($xml->zpravy->children() as $message) {
        $arr[] = [
          "from"      => (string) $message->od,
          "contents"  => (string) $message->text,
          "sysid"     => (string) $message->id,
          "date"      => (int)    \strtotime((string) $message->cas)
        ];
      }

      return $arr;
    }

    private function loadEvents() {
      $store = \Markaos\BakAPI\Util::loadPage($this->server .
        "/login.aspx?hx=" . $this->hash . "&pm=akce");

      \libxml_use_internal_errors(true);
      $xml = \simplexml_load_string($store);
      if($xml === false || !((string) $xml->result == BAKAPI_STATUS_OK)) {
        return false;
      }

      $arr = [];
      foreach ($xml->akceall->children() as $event) {
        $arr[] = [
          "name" => (string) $event->nazev,
          "date" => \strtotime((string) $event->datum),
          "time" => (string) $event->cas,
          "desc" => (string) $event->popis,
          "show" => (string) $event->zobrazit
        ];
      }
      return $arr;
    }

    private function loadHomework() {
      $store = \Markaos\BakAPI\Util::loadPage($this->server .
        "/login.aspx?hx=" . $this->hash . "&pm=ukoly");

      \libxml_use_internal_errors(true);
      $xml = \simplexml_load_string($store);
      if($xml === false || !((string) $xml->result == BAKAPI_STATUS_OK)) {
        return false;
      }

      $arr = array();
      foreach ($xml->ukoly->children() as $homework) {
        $arr[] = [
          "subject"     => (string) $homework->predmet,
          "issued"      => \strtotime((string) $homework->zadano),
          "deadline"    => \strtotime((string) $homework->nakdy),
          "state"       => (string) $homework->status,
          "description" => (string) $homework->popis
        ];
      }

      return $arr;
    }

    private function loadStableTimetable() {
      $xml = null;
      if($this->timetableCache === null) {
        $store = \Markaos\BakAPI\Util::loadPage($this->server .
          "/login.aspx?hx=" . $this->hash . "&pm=rozvrh&pmd=perm");

        \libxml_use_internal_errors(true);
        $xml = \simplexml_load_string($store);
        if($xml === false || !((string) $xml->result == BAKAPI_STATUS_OK)) {
          return false;
        }
        $this->timetableCache = $xml;
      } else {
        $xml = $this->timetableCache;
      }

      $arr = array();
      foreach($xml->rozvrh->dny->children() as $day) {
        if(!isset($day->zkratka)) continue;
        $dayShort = (string) $day->zkratka;
        foreach ($day->hodiny->children as $lesson) {
          // Filter out free hours
          if((string) $lesson->typ == "X") continue;
          $short = "";
          if((string) $lesson->typ == "A") {
            $short = (string) $lesson->zkratka;
          } else if ((string) $lesson->typ == "H") {
            $short = (string) $lesson->zkrpr;
          }
          $arr[] = [
            "caption"     => (string) $lesson->caption,
            "day"         => (string) $dayShort,
            "type"        => (string) $lesson->typ,
            "short"       => (string) $short,
            "steacher"    => (string) $lesson->zkruc,
            "teacher"     => (string) $lesson->uc,
            "shortRoom"   => (string) $lesson->zkrmist,
            "shortGroup"  => (string) $lesson->zkrskup,
            "group"       => (string) $lesson->skup,
            "cycle"       => (string) $lesson->cycle
          ];
        }
      }

      return $arr;
    }

    private function loadTimetableCaptions() {
      if($this->timetableCache === null) {
        $this->loadStableTimetable();
      }

      $arr = array();
      foreach($this->timetableCache->rozvrh->hodiny->children() as $caption) {
        if(!isset($caption->caption)) continue;
        $arr[] = [
          "caption" => (string) $caption->caption,
          "begin"   => (string) $caption->begintime,
          "end"     => (string) $caption->endtime,
        ];
      }

      return $arr;
    }

    private function loadTimetableOverlay() {
      $stable = $this->loadStableTimetable();

      // This is really simple in PHP
      $thisMonday = \date("Ymd", \strtotime("this week monday 00:00:00"));

      $store = \Markaos\BakAPI\Util::loadPage($this->server .
        "/login.aspx?hx=" . $this->hash . "&pm=rozvrh&pmd=$thisMonday");

      \libxml_use_internal_errors(true);
      $xml = \simplexml_load_string($store);
      if($xml === false || !((string) $xml->result == BAKAPI_STATUS_OK)) {
        return false;
      }

      $arr = array();
      foreach($xml->rozvrh->dny->children() as $day) {
        if(!isset($day->zkratka)) continue;
        $dayShort = (string) $day->zkratka;
        $nextMonday = \date("Ymd", \strtotime("next week monday 00:00:00",
          \strtotime((string) $day->datum)));
        foreach ($day->hodiny->children as $lesson) {
          // Filter out free hours
          if((string) $lesson->typ == "X") continue;
          // TODO: check whether 'A' lessons contain captions
          $short = "";
          if((string) $lesson->typ == "A") {
            $short = (string) $lesson->zkratka;
          } else if ((string) $lesson->typ == "H") {
            $short = (string) $lesson->zkrpr;
          }
          $a = [
            "caption"     => (string) $lesson->caption,
            "day"         => (string) $dayShort,
            "type"        => (string) $lesson->typ,
            "short"       => (string) $short,
            "steacher"    => (string) $lesson->zkruc,
            "teacher"     => (string) $lesson->uc,
            "shortRoom"   => (string) $lesson->zkrmist,
            "shortGroup"  => (string) $lesson->zkrskup,
            "group"       => (string) $lesson->skup,
            "theme"       => (string) $lesson->tema,
            "date"        => \strtotime((string) $day->datum)
          ];

          if(!\Markaos\BakAPI\Util::compareLessons($stable, $a)) {
            $arr[] = $a;
          }
        }
      }

      $store = \Markaos\BakAPI\Util::loadPage($this->server .
        "/login.aspx?hx=" . $this->hash . "&pm=rozvrh&pmd=$nextMonday");

      \libxml_use_internal_errors(true);
      $xml = \simplexml_load_string($store);
      if($xml === false || !((string) $xml->result == BAKAPI_STATUS_OK)) {
        return false;
      }

      foreach($xml->rozvrh->dny->children() as $day) {
        if(!isset($day->zkratka)) continue;
        $dayShort = (string) $day->zkratka;
        foreach ($day->hodiny->children as $lesson) {
          // Filter out free hours
          if((string) $lesson->typ == "X") continue;
          // TODO: check whether 'A' lessons contain captions
          $short = "";
          if((string) $lesson->typ == "A") {
            $short = (string) $lesson->zkratka;
          } else if ((string) $lesson->typ == "H") {
            $short = (string) $lesson->zkrpr;
          }
          $a = [
            "caption"     => (string) $lesson->caption,
            "day"         => (string) $dayShort,
            "type"        => (string) $lesson->typ,
            "short"       => (string) $short,
            "steacher"    => (string) $lesson->zkruc,
            "teacher"     => (string) $lesson->uc,
            "shortRoom"   => (string) $lesson->zkrmist,
            "shortGroup"  => (string) $lesson->zkrskup,
            "group"       => (string) $lesson->skup,
            "theme"       => (string) $lesson->tema,
            "date"        => \strtotime((string) $day->datum)
          ];

          if(!\Markaos\BakAPI\Util::compareLessons($stable, $a)) {
            $arr[] = $a;
          }
        }
      }

      return $arr;
    }

    private function loadTimetableCycles() {
      $correction = 0;
      $arr = array();
      for($i = 0; $i < 4; $i++) {
        $dateInt = \strtotime(($i + $correction == 0 ? "-" : "+") .
          \abs($i + $correction - 1) . " week monday");
        $date = \date("Ymd", $dateInt);
        $store = \Markaos\BakAPI\Util::loadPage($this->server .
          "/login.aspx?hx=" . $this->hash . "&pm=rozvrh&pmd=$date");

        \libxml_use_internal_errors(true);
        $xml = \simplexml_load_string($store);
        if($xml === false || !((string) $xml->result == BAKAPI_STATUS_OK)) {
          return false;
        }

        $arr[] = [
          "mondayDate" => $dateInt,
          "cycle"      => (string) $xml->rozvrh->zkratkacyklu
        ];
      }

      return $arr;
    }
  }
}
?>
