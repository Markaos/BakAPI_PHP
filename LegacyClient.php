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
    private $themesCache = null;
    private $fullCache = null;

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
      $server = parse_url($server, PHP_URL_SCHEME) . "://" .
                parse_url($server, PHP_URL_HOST) .
                str_replace("index.aspx", "", parse_url($server, PHP_URL_PATH));
      if(substr($server, -1) == "/") $server = substr($server, 0, -1);
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
        "updating"=> false,
        "hx"      => $uname,
        "sit"     => $salt . $internalCode . $type,
        "uid"     => str_replace(['/', '\\', ':'], ['_', '_', '_'],
                       $uname . "@" . $this->server)
      ];

      return $this->getData();
    }

    public function reconstruct($data, $provider, $verify = false) {
      $loginHash = base64_encode(hash("sha512", $data["token"] . date("Ymd"), true));
      $loginHash = str_replace(['\\', '/', '+'], ['_', '_', '-'], $loginHash);

      $this->server = $data["server"];
      $this->hash = $loginHash;
      $this->data = $data;

      if($verify) {
        $store = \Markaos\BakAPI\Util::loadPage($this->server .
          "/login.aspx?hx=$" . $loginHash . "&pm=login");

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
      $ctx = Log::addContext("Loading data for " . $this->data["name"]);
      $sections = explode(',', $sections);

      $store = \Markaos\BakAPI\Util::loadPage($this->server .
        "/login.aspx?hx=" . $this->hash . "&pm=all");

      \libxml_use_internal_errors(true);
      $xml = \simplexml_load_string($store);
      if($xml === false) {
        // LegacyClient has proven to be working fine, but "legacy" servers
        // often return malformed data (just plain HTML page) when they are
        // given correct request data - this part shouldn't cause ~5 reports
        // per user per day, log level has thus been lowered
        Log::i("LegacyClient", "Couldn't load action \"all\"");
        return false;
      }

      $this->fullCache = $xml;

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
          case BAKAPI_SECTION_TIMETABLE_THEMES:
            $rArr[BAKAPI_SECTION_TIMETABLE_THEMES] = $this->loadTimetableThemes();
            break;
        }
      }
      Log::removeContext($ctx);
      return $rArr;
    }

    public function update() {
      // We don't support updates, so there's nothing to do here
      return false;
    }

    public function login($server, $name, $password, $data) {
      // Reconstruct UID
      $ctx = Log::addContext("Offline login");
      $server = parse_url($server, PHP_URL_SCHEME) . "://" .
                parse_url($server, PHP_URL_HOST) .
                str_replace("index.aspx", "", parse_url($server, PHP_URL_PATH));
      if(substr($server, -1) == "/") $server = substr($server, 0, -1);
      $uid = str_replace(['/', '\\', ':'], ['_', '_', '_'], $name . "@" . $server);
      $d = $data->getData($uid);
      if($d == null) return false;
      $token = base64_encode(hash("sha512", $d["sit"] . $password, true));
      $token = "*login*" . $name . "*pwd*" . $token . "*sgn*ANDR";
      Log::removeContext($ctx);
      if($d["token"] == $token) return $uid;
      return false;
    }

    private function loadGrades() {
      $xml = null;
      if($this->fullCache == null) {
        $store = \Markaos\BakAPI\Util::loadPage($this->server .
          "/login.aspx?hx=" . $this->hash . "&pm=znamky");

        \libxml_use_internal_errors(true);
        $xml = \simplexml_load_string($store);
        if($xml === false || !((string) $xml->result == BAKAPI_STATUS_OK)) {
          return false;
        }
      } else {
        $xml = $this->fullCache->xmlznamky->results;
      }

      // We need subjects table to determine shortened name for subject
      $subjects = $this->loadSubjects();

      $arr = array();
      foreach($xml->predmety->children() as $subject) {
        // Get subject shortcut
        $sub = "";
        foreach($subjects as $k => $subj) {
          if($subj["name"] == (string) $subject->nazev) {
            $sub = $subj["short"];
            break;
          }
        }

        foreach($subject->znamky->children() as $grade) {
          $arr[] = [
            "subject"     => (string) $sub,
            "title"       => (string) $grade->caption,
            "description" => (string) $grade->poznamka,
            "grade"       => (string) $grade->znamka,
            "weight"      => (int)    $grade->vaha,
            "date"        => (int)    \strtotime((string) $grade->udeleno)
          ];
        }
      }

      return $arr;
    }

    private function loadSubjects() {
      $xml = null;
      if($this->fullCache === null) { 
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
      } else {
        $xml = $this->fullCache->xmlpredmety->results;
      }

      $arr = [];
      foreach ($xml->predmety->children() as $subject) {
        $arr[] = [
          "name" => (string) $subject->nazev,
          "teachers" => (string) $subject->ucitel,
          "emails" => (string) $subject->mailuc,
          "short" => (string) $subject->zkratka
        ];
      }
      $this->subjectsCache = $arr;
      return $arr;
    }

    private function loadMessages() {
      $store = \Markaos\BakAPI\Util::loadPage($this->server .
        "/login.aspx?hx=" . $this->hash . "&pm=prijate");

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
      $xml = null;
      if($this->fullCache === null) {
        $store = \Markaos\BakAPI\Util::loadPage($this->server .
          "/login.aspx?hx=" . $this->hash . "&pm=akce");

        \libxml_use_internal_errors(true);
        $xml = \simplexml_load_string($store);
        if($xml === false || !((string) $xml->result == BAKAPI_STATUS_OK)) {
          return false;
        }
      } else {
        $xml = $this->fullCache->xmlakce->results;
      }

      $arr = [];
      foreach ($xml->akceall->children() as $event) {
        $arr[] = [
          "name" => (string) $event->nazev,
          "description" => (string) $event->popis,
          "timerange" => (string) $event->cas,
          "rooms" => (string) $event->promistnosti,
          "teachers" => (string) $event->proucitele,
          "classes" => (string) $event->protridy,
          "show" => (int) $event->zobrazit,
          "date" => \strtotime((string) $event->datum)
        ];
      }
      return $arr;
    }

    private function loadHomework() {
      $xml = null;
      if($this->fullCache === null) {
        $store = \Markaos\BakAPI\Util::loadPage($this->server .
          "/login.aspx?hx=" . $this->hash . "&pm=ukoly");

        \libxml_use_internal_errors(true);
        $xml = \simplexml_load_string($store);
        if($xml === false || !((string) $xml->result == BAKAPI_STATUS_OK)) {
          return false;
        }
      } else {
        $xml = $this->fullCache->xmlukoly->results;
      }

      $arr = array();
      foreach ($xml->ukoly->children() as $homework) {
        $iorig = (string) $homework->zadano;
        $issued = substr($iorig, 4, 2) . "." . substr($iorig, 2, 2) . ".20" .
          substr($iorig, 0, 2);
        $iorig = (string) $homework->nakdy;
        $deadline = substr($iorig, 4, 2) . "." . substr($iorig, 2, 2) . ".20" .
          substr($iorig, 0, 2);
        $arr[] = [
          "subject"     => (string) $homework->predmet,
          "issued"      => \strtotime($issued),
          "deadline"    => \strtotime($deadline),
          "state"       => (string) $homework->status,
          "description" => (string) $homework->popis
        ];
      }

      return $arr;
    }

    private function loadStableTimetable() {
      $xml = null;
      if($this->fullCache === null) {
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
      } else {
        $xml = $this->fullCache->xmlrozvrhperm->results;
        $this->timetableCache = $xml;
      }

      $arr = array();
      foreach($xml->rozvrh->dny->children() as $day) {
        if(!isset($day->zkratka)) continue;
        $dayShort = (string) $day->zkratka;
        foreach ($day->hodiny->children() as $lesson) {
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
      $captions = $this->loadTimetableCaptions();
      $cpts = array();
      foreach($captions as $cpt) {
        $cpts[] = $cpt["caption"];
      }
      $cpts = \array_flip($cpts);

      // This is really simple in PHP
      $thisMonday = \date("Ymd", \strtotime("this week monday 00:00:00"));

      $xml = null;
      if($this->fullCache === null) {
        $store = \Markaos\BakAPI\Util::loadPage($this->server .
          "/login.aspx?hx=" . $this->hash . "&pm=rozvrh&pmd=$thisMonday");

        \libxml_use_internal_errors(true);
        $xml = \simplexml_load_string($store);
        if($xml === false || !((string) $xml->result == BAKAPI_STATUS_OK)) {
          return false;
        }
      } else {
        $xml = $this->fullCache->xmlrozvrhakt->results;
      }

      if($this->themesCache === null) {
        $this->themesCache = array();
      }

      $arr = array();
      $cycle = (string) $xml->rozvrh->zkratkacyklu;
      foreach($xml->rozvrh->dny->children() as $day) {
        if(!isset($day->zkratka)) continue;
        $dayShort = (string) $day->zkratka;
        $nextMonday = \date("Ymd", \strtotime("next week monday 00:00:00",
          \strtotime((string) $day->datum)));

        if(!isset($this->themesCache[$dayShort])) {
          $this->themesCache[$dayShort] = array();
        }

        $i = 0;
        foreach ($day->hodiny->children() as $lesson) {
          if((string) $lesson->typ == "X") {
            $ids = \Markaos\BakAPI\Util::getLessonIndexes($stable, $dayShort,
              $captions[$i]["caption"]);
            foreach ($ids as $id) {
              $l = $stable[$id];
              if(!isset($l["cycle"]) || $l["cycle"] == "" ||
                  $l["cycle"] == $cycle) {
                $arr[] = [
                  "caption"     => (string) $captions[$i]["caption"],
                  "day"         => (string) $dayShort,
                  "type"        => (string) $lesson->typ,
                  "short"       => "",
                  "steacher"    => "",
                  "teacher"     => "",
                  "shortRoom"   => "",
                  "shortGroup"  => "",
                  "group"       => "",
                  "date"        => \strtotime((string) $day->datum)
                ];
              }
            }
            $i++;
            continue;
          }

          $short = "";
          if((string) $lesson->typ == "A") {
            $lesson->caption = (string) $captions[$i]["caption"];
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
            "date"        => \strtotime((string) $day->datum)
          ];

          if((string) $lesson->caption != "") {
            $this->themesCache[$dayShort][(string) $lesson->caption] =
              (string) $lesson->tema;
          }

          if(!\Markaos\BakAPI\Util::compareLessons($stable, $a, $cycle)) {
            $arr[] = $a;
          }
          $i++;
        }
      }

      $xml = null;
      if($this->fullCache === null) {
        $store = \Markaos\BakAPI\Util::loadPage($this->server .
          "/login.aspx?hx=" . $this->hash . "&pm=rozvrh&pmd=$nextMonday");

        \libxml_use_internal_errors(true);
        $xml = \simplexml_load_string($store);
        if($xml === false || !((string) $xml->result == BAKAPI_STATUS_OK)) {
          return false;
        }
      } else {
        $xml = $this->fullCache->xmlrozvrhnext->results;
      }

      $cycle = (string) $xml->rozvrh->zkratkacyklu;
      foreach($xml->rozvrh->dny->children() as $day) {
        if(!isset($day->zkratka)) continue;
        $dayShort = (string) $day->zkratka;

        $i = 0;
        foreach ($day->hodiny->children() as $lesson) {
          if((string) $lesson->typ == "X") {
            $ids = \Markaos\BakAPI\Util::getLessonIndexes($stable, $dayShort,
              $captions[$i]["caption"]);
            foreach ($ids as $id) {
              $l = $stable[$id];
              if(!isset($l["cycle"]) || $l["cycle"] == "" ||
                  $l["cycle"] == $cycle) {
                $arr[] = [
                  "caption"     => (string) $captions[$i]["caption"],
                  "day"         => (string) $dayShort,
                  "type"        => (string) $lesson->typ,
                  "short"       => "",
                  "steacher"    => "",
                  "teacher"     => "",
                  "shortRoom"   => "",
                  "shortGroup"  => "",
                  "group"       => "",
                  "date"        => \strtotime((string) $day->datum)
                ];
              }
            }
            $i++;
            continue;
          }

          $short = "";
          if((string) $lesson->typ == "A") {
            $lesson->caption = (string) $captions[$i]["caption"];
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
            "date"        => \strtotime((string) $day->datum)
          ];

          if(!\Markaos\BakAPI\Util::compareLessons($stable, $a, $cycle)) {
            $arr[] = $a;
          }
          $i++;
        }
      }

      return $arr;
    }

    private function loadTimetableCycles() {
      $correction = 0;
      $arr = array();
      for($i = 0; $i < 3; $i++) {
        $dateStr = "";
        if($i + $correction == 0) {
          $dateStr = "this week Monday";
        } else if ($i + $correction == 1) {
          $dateStr = "next week Monday";
        } else {
          $dateStr = "+" . ($i + $correction - 1) . " week Monday";
        }
        $dateInt = \strtotime($dateStr);
        $date = \date("Ymd", $dateInt);
        $xml = null;

        if($this->fullCache === null || !($dateStr == "this week Monday" || $dateStr == "next week Monday") ) {
          $store = \Markaos\BakAPI\Util::loadPage($this->server .
            "/login.aspx?hx=" . $this->hash . "&pm=rozvrh&pmd=$date");

          \libxml_use_internal_errors(true);
          $xml = \simplexml_load_string($store);
          if($xml === false || !((string) $xml->result == BAKAPI_STATUS_OK)) {
            return false;
          }
        } else {
          if($dateStr == "this week Monday") { 
            $xml = $this->fullCache->xmlrozvrhakt->results;
          } else {
            $xml = $this->fullCache->xmlrozvrhnext->results;
          }
        }

        $dateX = \strtotime((string) $xml->rozvrh->dny->den->datum);
        $date1 = new \DateTime(\date("Y-m-d", $dateInt));
        $date2 = new \DateTime(\date("Y-m-d", $dateX));

        $diff = $date2->diff($date1)->format("%a");
        $correction = abs($diff / 7);

        $arr[] = [
          "mondayDate" => $dateX,
          "cycle"      => (string) $xml->rozvrh->zkratkacyklu
        ];
      }

      return $arr;
    }

    private function loadTimetableThemes() {
      if(!isset($this->themesCache)) {
        $this->loadTimetableOverlay();
        if(!isset($this->themesCache)) {
          Log::w("LegacyClient",
            "loadTimetableOverlay() failed to set themesCache");
          return [];
        }
      }

      $days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
      $shorts = ["Po", "Út", "St", "Čt", "Pá", "So", "Ne"];
      $shorts = \array_flip($shorts);

      $arr = array();
      foreach($this->themesCache as $day => $captions) {
        foreach($captions as $caption => $theme) {
          $arr[] = [
            "date"    => \strtotime("this week " . $days[$shorts[$day]]),
            "caption" => (string) $caption,
            "theme"   => $theme
          ];
        }
      }

      return $arr;
    }
  }
}
?>
