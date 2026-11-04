<?php

require "misc/tools.php";

$url = $_REQUEST["url"];
$requested_root_domain = get_root_domain($url);

$allowed_domains = array("pinimg.com", "i.pinimg.com", "pinterest.com");

if (in_array($requested_root_domain, $allowed_domains)
    && parse_url($url, PHP_URL_SCHEME) === "https"
    && parse_url($url, PHP_URL_PORT) === null)
{
  $image = $url;
  $image_src = request($image);

  header("Content-Type: image/png");
  echo $image_src;
}

?>
