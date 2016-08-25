<?php
namespace Markaos\BakAPI {
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
      $table = "users";
      $structure = [
        "UID" => "string:255",
        "client" => "string:127",
        "data" => "string:511",
        "lastUpdateFast" => "int",
        "lastUpdateSlow" => "int"
      ];

      $db->createTable($table, $structure);
    }
  }
}
?>
