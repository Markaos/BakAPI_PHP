<?php
namespace Markaos\BakAPI {
  class Log {
    private static $db = null;
    private static $table = null;
    private static $levels = [
      "I"  => 10,
      "W"  => 20,
      "E"  => 30,
      "CE" => 40,
      "NONE" => 1000
    ]; // Enough space for future additions
    private static $saveLevel = null;
    private static $mailLevel = null;
    private static $mailFrom = null;
    private static $mailName = null;
    private static $mailTo = null;
    private static $mailSubject = null;

    private static function init() {
      if(self::$db === null) {
        $settings = \Markaos\BakAPI\Util::getSettings();
        self::$db = new $settings["database"]("log");
        self::$db->createTable($settings["log_table"], array(
          "level"     => "string:2",
          "component" => "string:256",
          "message"   => "string:1024"
        ));
        self::$table = $settings["log_table"];
        self::$saveLevel = $settings["log_save_level"];
        self::$mailLevel = $settings["log_mail_level"];
        self::$mailFrom = $settings["log_mail_sender_email"];
        self::$mailName = $settings["log_mail_sender_name"];
        self::$mailTo = $settings["log_mail_to"];
        self::$mailSubject = $settings["log_mail_subject"];
      }
    }

    // Info - use for normal information like "job started", "job finished" etc.
    public static function i($component, $message) {
      self::log("I", $component, $message);
    }

    // Warning - something went wrong, but we've recovered from that
    public static function w($component, $message) {
      self::log("W", $component, $message);
    }

    // Error - unrecoverable error occured, but it may be due to malformed user
    //         request
    public static function e($component, $message) {
      self::log("E", $component, $message);
    }

    // Critical error - something went wrong in the inner workings of our page
    //                  Instant review needed
    public static function critical($component, $message) {
      self::log("CE", $component, $message);
    }

    private static function log($level, $component, $message) {
      self::init();
      if(self::$levels[$level] >= self::$levels[self::$saveLevel]) {
        self::$db->insert(self::$table, ["level", "component", "message"],
          [[$level, $component, $message]]);
      }

      if(self::$levels[$level] >= self::$levels[self::$mailLevel]) {
        $from = self::$mailName . " <" . self::$mailFrom . ">";
        $to = self::$mailTo;
        $message = "Level: $level\r\nComponent: $component\r\nMessage: $message";
        mail($to, self::$mailSubject, wordwrap($message, 70, "\r\n"),
          "From: $from");
      }
    }
  }
}
?>
