<?php
require "static/header.php";
require "engines/pinterest.php";

$query = $_GET['q'];

$imgResults = get_image_results($query);
print_image_results($imgResults);

?>
