<?php require "misc/header.php"; ?>
<title>
<?php
  $query = htmlspecialchars(trim($_REQUEST["q"]));
  echo $query;
?> - Binternet</title>
</head>
    <body>
        <form class="search-container" method="get" autocomplete="off">
            <h1><a class="no-decoration accent" href="./">Binternet</a></h1>
            <input type="text" name="q" placeholder="Search Image"
                <?php
                    $query_encoded = urlencode($query);

                    if (1 > strlen($query) || strlen($query) > 64)
                    {
                        header("Location: ./");
                        die();
                    }

                    echo "value=\"$query\"";
                ?>
            >
            <!-- <div></div> -->
        </form>

<?php

$query = $_GET['q'];

$baseurl = "https://pinterest.com/resource/BaseSearchResource/get/?data=%7B%22options%22%3A%7B%22query%22%3A%22{}%22%7D%7D";

$search = function($query) use($baseurl)
{
    $url = str_replace("{}", str_replace(" ", "%20", $query), $baseurl);
    $json = file_get_contents($url);
    $data = json_decode($json);
    $images = array();
    echo "<div class=img-container>";
        foreach ($data->{"resource_response"}->{"data"}->{"results"} as $result)
        {
            $image = $result->{"images"}->{"orig"};
            $url = $image->{"url"};
            array_push($images, $url);
            echo "<a class=img-result href='/image_proxy.php?url=", $url, "'>";
            echo "<img src='/image_proxy.php?url=", $url, "'></a>";
        }
        return $images;
    echo "</div>";
};

$images = $search("$query");

include "misc/footer.php";

?>
