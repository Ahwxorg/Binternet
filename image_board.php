<?php
require "misc/tools.php";
require "misc/header.php";
?>

<title>
<?php
  $query = htmlspecialchars(trim($_REQUEST["q"]));
  echo $query;
?> - Binternet</title>
</head>
    <body>
        <form class="searchContainer" method="get" autocomplete="off">
            <h1><a class="no-decoration" href="./"><span>B</span>inter<span>n</span>et</a></h1>
            <input type="text" name="q"
                <?php
                    $query_encoded = urlencode($query);

                    if (1 > strlen($query) || strlen($query) > 64)
                    {
                        // header("Location: ./");
                        // die();
                    }

                    echo "value=\"$query\"";
                ?>
            <br>
       <hr>
</form>
<div class="imageBoard">
<?php

if (!$_REQUEST["imgurl"])
{
  $imgurl = $_REQUEST["imgurl"];
}
else
{
  $imgurl = "/404.jpg";
}

$instance_url = getenv('HTTP_HOST');
echo $instance_url;
$allowed_domains = array("pinimg.com", "i.pinimg.com", "pinterest.com", "localhost", "$instance_url"); # TODO

if (!$imgurl)
{
  echo "No URL passed, showing images matching query.";
}
else 
{
  if (in_array(get_root_domain($imgurl), $allowed_domains))
  {
    // header("Content-type: image/jpeg");
    echo "<img src='/image_proxy.php?url=", $imgurl, "'></a>";
  }
}

echo "</div>";
echo "<br><br><br><br>";

include "misc/footer.php";

?>

<style>
.imageBoard img {
  display: block;
  margin-left: auto;
  margin-right: auto;
  width: auto;
  height: auto;
}

</style>
