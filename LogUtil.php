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
    private static $context = null;
    private static $queue = null;

    private static function init() {
      register_shutdown_function("Markaos\BakAPI\Log::send");
      if(self::$db === null) {
        $settings = \Markaos\BakAPI\Util::getSettings();
        self::$db = new $settings["database"]("log");
        self::$db->createTable($settings["log_table"], array(
          "level"     => "string:2",
          "component" => "string:256",
          "message"   => "string:1024",
          "context"   => "string:2048"
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

    // Context allows you  to add useful informations  without requiring you to
    // write it in every log() call. Useful when processing user data or things
    // like that.
    public static function addContext($context) {
      $count = count(self::$context);
      self::$context[$count] = $context;
      return $count;
    }

    // Remove context level $id and all context levels added after it
    public static function removeContext($id) {
      for($i = count(self::$context); $i > $id; $i--) {
        unset(self::$context[$i - 1]);
      }
    }

    public static function send() {
      if(self::$queue != null) {
        $msg = "";
        foreach(self::$queue as $error) {
          $msg .= "\r\n\r\nLevel: " . $error["level"] . "\r\n";
          $msg .= "Component: " . $error["component"] . "\r\n";
          $msg .= "Message: " . $error["message"] . "\r\n";
          $msg .= "Context: " . $error["context"] . "\r\n";
        }
        $from = self::$mailName . " <" . self::$mailFrom . ">";
        $to = self::$mailTo;
        mail($to, self::$mailSubject, wordwrap($message, 70, "\r\n"),
          "From: $from");
      }
    }

    private static function getContext() {
      $first = true;
      $str = "";
      foreach(self::$context as $context) {
        if(!$first) $str .= " > ";
        $str .= $context;
        $first = false;
      }
      return $str;
    }

    private static function log($level, $component, $message) {
      self::init();
      if(self::$levels[$level] >= self::$levels[self::$saveLevel]) {
        self::$db->insert(self::$table, ["level", "component", "message", "context"],
          [[$level, $component, $message, self::getContext()]]);
      }

      if(self::$levels[$level] >= self::$levels[self::$mailLevel]) {
        if(self::$queue == null) {
          self::$queue = array();
        }
        self::$queue[] = [
          "level"     => $level,
          "component" => $component,
          "message"   => $message,
          "context"   => self::getContext()
        ];
      }
    }
  }
}
?>
