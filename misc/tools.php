<?php
    function get_root_domain($url)
    {
        $split_url = explode("/", $url);
        $base_url = $split_url[2];

        $base_url_main_split = explode(".", strrev($base_url));
        $root_domain = strrev($base_url_main_split[1]) . "." . strrev($base_url_main_split[0]);
    
        return $root_domain;
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
