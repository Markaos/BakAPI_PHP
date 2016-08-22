<?php
namespace Markaos\BakAPI {
  class Util {
    public static function loadPage($url) {
      $ch = \curl_init();
      \curl_setopt($ch, CURLOPT_URL, $url);
      \curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
      \curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
      \curl_setopt ($ch, CURLOPT_USERAGENT, "");
      \curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      \curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      $store = \curl_exec ($ch);
      \curl_close($ch);
      return $store;
    }
  }
}
?>
