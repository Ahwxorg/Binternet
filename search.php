<?php require "misc/header.php"; ?>
<title>
<?php
$query = htmlspecialchars(trim($_REQUEST["q"] ?? ''));
echo $query ?: 'Search' . ' - Binternet';
?> - Binternet</title>
</head>
<body>
    <form class="search-container" method="get" autocomplete="off">
        <h1><a class="no-decoration accent" href="./">Binternet</a></h1>
        <input type="text" name="q" placeholder="Search Image"
            <?php
            // Validate query length
            if (strlen($query) < 1 || strlen($query) > 64) {
                header("Location: ./");
                exit();
            }
            echo "value=\"" . htmlspecialchars($query) . "\"";
            ?>
        >
    </form>

<?php
// Fetching query and optional parameters
$bookmark = $_GET["bookmark"] ?? null;
$csrftoken = $_GET["csrftoken"] ?? null;

// Pinterest API endpoint
$url = "https://www.pinterest.com/resource/BaseSearchResource/get/";

class SearchResult
{
    public $images;
    public $bookmark;
}

// Header function to capture CSRF token from response
$header_function = function ($ch, $rawheader) use (&$csrftoken) {
    if (preg_match('/^set-cookie:\s*csrftoken=([^;]*)/', $rawheader, $matches)) {
        $csrftoken = $matches[1];
    }
    return strlen($rawheader);
};

// Prepare CURL object for search request
$prepare_search_curl_obj = function ($query, $bookmark) use ($url, $header_function, $csrftoken) {
    $data_param_obj = [
        "options" => [
            "query" => $query,
        ],
    ];
    
    if ($bookmark !== null) {
        $data_param_obj["options"]["bookmarks"] = [$bookmark];
    }

    $data_param = urlencode(json_encode($data_param_obj));
    $headers = [];
    
    if ($csrftoken !== null) {
        $headers[] = "x-csrftoken: $csrftoken";
        $headers[] = "cookie: csrftoken=$csrftoken";
    }

    $finalurl = $bookmark === null ? "$url?data=$data_param" : $url;
    
    $ch = curl_init($finalurl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, $header_function);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($bookmark !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "data=$data_param");
    }
    
    return $ch;
};

// Function to perform the search and display results
$search = function ($query, $bookmark) use ($prepare_search_curl_obj) {
    $ch = $prepare_search_curl_obj($query, $bookmark);
    $response = curl_exec($ch);
    $data = json_decode($response);
    
    $images = [];
    echo "<div class='img-container'>";
    
    if ($data && isset($data->resource_response->data->results)) {
        foreach ($data->resource_response->data->results as $result) {
            $image = $result->images->orig;
            $url = $image->url;
            $images[] = $url;
            echo "<a class='img-result' href='/image_proxy.php?url=" . htmlspecialchars($url) . "'>";
            echo "<img loading='lazy' src='/image_proxy.php?url=" . htmlspecialchars($url) . "'></a>";
        }
    } else {
        echo "<p>No results found.</p>";
    }
    
    echo "</div>";
    
    $result = new SearchResult();
    $result->images = $images;
    
    if (isset($data->resource_response->bookmark)) {
        $result->bookmark = $data->resource_response->bookmark;
    }
    
    return $result;
};

$result = $search($query, $bookmark);

// Pagination link for the next page
if ($result->bookmark !== null) {
    $query_encoded = urlencode($query);
    $bookmark_encoded = urlencode($result->bookmark);
    $csrftoken_encoded = $csrftoken ? urlencode($csrftoken) : "";

    echo "<h2 style=\"text-align: center;\"><a href=\"/search.php?q=$query_encoded&bookmark=$bookmark_encoded&csrftoken=$csrftoken_encoded\">Next page</a></h2><br><br><br>";
}

include "misc/footer.php";
?>
