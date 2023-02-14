<?php require "static/header.php";

  $q = $_GET['q'];

  $url = "https://pinterest.com/search/pins/?q=$q";
  $pinterestPage = file_get_contents($url);

  $doc = new DOMDocument();
  @$doc->loadHTML($html);

  $items = $doc->getElementsByTagName('img');

  foreach ($items as $item) {
      echo "<img src='", $item, "'>";
  }

//  echo $pinterestPage;

?>
