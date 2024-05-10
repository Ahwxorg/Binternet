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

                if (1 > strlen($query) || strlen($query) > 64) {
                    header("Location: ./");
                    die();
                }

                echo "value=\"$query\"";
                ?>
            >
            <!-- <div></div> -->
        </form>

<?php
$query = $_GET["q"];

$bookmark = null;
if (array_key_exists("bookmark", $_GET)) {
    $bookmark = urldecode($_GET["bookmark"]);
}

$csrftoken = null;
if (array_key_exists("csrftoken", $_GET)) {
    $csrftoken = $_GET["csrftoken"];
}

$url = "https://www.pinterest.com/resource/BaseSearchResource/get/";

class SearchResult
{
    public $images;
    public $bookmark;
}

$header_function = function ($ch, $rawheader) {
    global $csrftoken;
    $len = strlen($rawheader);

    $header = explode(":", $rawheader, 2);
    if (count($header) != 2) {
        return $len;
    }

    // we are only interested in set-cookie header
    if (trim($header[0]) != "set-cookie") {
        return $len;
    }

    $cookie = explode(";", trim($header[1]), 2);
    $cookie = explode("=", $cookie[0], 2);

    switch ($cookie[0]) {
        case "csrftoken":
            $csrftoken = $cookie[1];
    }

    return $len;
};

$prepare_search_curl_obj = function ($query, $bookmark) use (
    $url,
    $header_function,
    $csrftoken
) {
    $data_param_obj = [
        "options" => [
            "query" => $query,
        ],
    ];
    if ($bookmark != null) {
        $data_param_obj["options"]["bookmarks"] = [$bookmark];
    }

    $data_param = urlencode(json_encode($data_param_obj));

    $headers = [];
    if ($csrftoken != null) {
        $headers[] = "x-csrftoken: $csrftoken";
        $headers[] = "cookie: csrftoken=$csrftoken";
    }

    $finalurl = $url;
    if ($bookmark == null) {
        $finalurl = "$url?data=$data_param";
    }

    $ch = curl_init($finalurl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, $header_function);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($bookmark != null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "data=$data_param");
    }
    return $ch;
};

$search = function ($query, $bookmark) use ($prepare_search_curl_obj) {
    $ch = $prepare_search_curl_obj($query, $bookmark);
    $response = curl_exec($ch);
    $data = json_decode($response);
    $images = [];
    echo "<div class=img-container>";
    if (
        $data &&
        property_exists($data, "resource_response") &&
        property_exists($data->{"resource_response"}, "data") &&
        property_exists($data->{"resource_response"}->{"data"}, "results")
    ) {
        foreach (
            $data->{"resource_response"}->{"data"}->{"results"}
            as $result
        ) {
            $image = $result->{"images"}->{"orig"};
            $url = $image->{"url"};
            array_push($images, $url);
            echo "<a class=img-result href='/image_proxy.php?url=", $url, "'>";
            echo "<img loading='lazy' src='/image_proxy.php?url=",
                $url,
                "'></a>";
        }
    } else {
        echo "<p>No results found.</p>";
    }
    echo "</div>";
    $result = new SearchResult();
    $result->images = $images;
    if (
        $data &&
        property_exists($data, "resource_response") &&
        property_exists($data->{"resource_response"}, "bookmark")
    ) {
        $result->bookmark = $data->{"resource_response"}->{"bookmark"};
    }
    return $result;
};

$result = $search($query, $bookmark);

if ($result->bookmark != null) {
    $query_encoded = urlencode($query);
    $bookmark_encoded = urlencode($result->bookmark);
    $csrftoken_encoded = $csrftoken ? urlencode($csrftoken) : "";

    echo "<h2 style=\"text-align: center;\"><a href=\"/search.php?q=$query_encoded&bookmark=$bookmark_encoded&csrftoken=$csrftoken_encoded\">Next page</a></h2><br><br><br>";
}

include "misc/footer.php";


?>
