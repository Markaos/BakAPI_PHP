<?php
namespace Markaos\BakAPI {
  define("BAKAPI_DB_VERSION", 1);

  class Util {

    public static function loadPage($url) {
      $ch = \curl_init();
      \curl_setopt($ch, CURLOPT_URL, $url);
      \curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
      \curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
      \curl_setopt ($ch, CURLOPT_USERAGENT, "");
      \curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
      \curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
      $store = \curl_exec ($ch);
      \curl_close($ch);
      return $store;
    }

    public static function getSettings() {
      if(!\file_exists("config.ini")) {
        \file_put_contents("config.ini", "; Put your settings here");
      }

      $settings = \parse_ini_file("defaults.ini");
      $custom = \parse_ini_file("config.ini");

      return \array_merge($settings, $custom);
    }

    public static function getDatabase() {
      $settings = \Markaos\BakAPI\Util::getSettings();
      return new $settings["database"]();
    }

    public static function initBakAPI() {
      $db = \Markaos\BakAPI\Util::getDatabase();

      $table = "meta";
      $structure = [
        "version" => "int"
      ];
      $db->createTable($table, $structure);

      $table = "users";
      $structure = [
        "UID" => "string:255",
        "client" => "string:127",
        "data" => "string:511",
        "lastUpdateFast" => "int",
        "lastUpdateSlow" => "int"
      ];
      $db->createTable($table, $structure);

      $table = "grades";
      $structure = [
        "UID"         => "string:255",
        "subject"     => "string:64",
        "title"       => "string:127",
        "description" => "string:255",
        "grade"       => "string:4",
        "weight"      => "int",
        "date"        => "int",
      ];
      $db->createTable($table, $structure);

      $table = "subjects";
      $structure = [
        "UID"         => "string:255",
        "name"        => "string:64",
        "teachers"    => "string:255",
        "emails"      => "string:255",
        "short"       => "string:8"
      ];
      $db->createTable($table, $structure);

      $table = "events";
      $structure = [
        "UID"         => "string:255",
        "name"        => "string:64",
        "description" => "string:512",
        "timerange"   => "string:16",
        "rooms"       => "string:128",
        "teachers"    => "string:128",
        "classes"     => "string:128",
        "show"        => "int",
        "date"        => "int"
      ];
      $db->createTable($table, $structure);

      $table = "messages";
      $structure = [
        "UID"         => "string:255",
        "from"        => "string:64",
        "contents"    => "string:2048",
        "sysid"       => "string:128",
        "date"        => "int"
      ];
      $db->createTable($table, $structure);
    }
  }
}
?>
