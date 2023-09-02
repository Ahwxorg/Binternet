<?php
    function get_root_domain($url) {
      return parse_url($url, PHP_URL_HOST);
    }

    function request($url)
    {
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_HEADER, false);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $response = curl_exec($ch);

      return $response;
    }
?>
