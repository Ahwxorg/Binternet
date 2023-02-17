<?php

require "misc/tools.php";

$url = $_REQUEST["url"];
$allowed_domains = array("pinimg.com", "i.pinimg.com", "pinterest.com");

if (in_array(get_root_domain($url), $allowed_domains))
{
  header("Content-type: image/jpeg");
  echo request($url);
}

?>
