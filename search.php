<?php require "misc/header.php"; ?>
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
                        header("Location: ./");
                        die();
                    }

                    echo "value=\"$query\"";
                ?>
            >
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
    foreach ($data->{"resource_response"}->{"data"}->{"results"} as $result)
    {
        $image = $result->{"images"}->{"orig"};
        $url = $image->{"url"};
        array_push($images, $url);
        echo "<a href='/image_proxy.php?url=", $url, "'>";
        echo "<img src='/image_proxy.php?url=", $url, "'></a>";
    }
    return $images;
};

$images = $search("$query");
echo "<br><br><br><br>";

include "misc/footer.php";

?>
