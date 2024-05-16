<?php require "misc/header.php"; ?>

<?php
$query = htmlspecialchars(trim($_GET['q']??''), ENT_QUOTES, 'UTF-8');
if (strlen($query) < 1 || strlen($query) > 64) {
    header("Location: ./");
    die();
}
?>

<title><?php echo $query; ?> - Binternet</title>
</head>
<body>
    <form class="search-container" method="get" autocomplete="off">
        <h1><a class="no-decoration accent" href="./">Binternet</a></h1>
        <input type="text" name="q" placeholder="Search Image" value="<?php echo $query; ?>">
    </form>

<?php
$bookmark = $_GET['bookmark']??null;
$csrftoken = $_GET['csrftoken']??null;
$page = intval($_GET['page']??1);
$url = "https://www.pinterest.com/resource/BaseSearchResource/get/";
class SearchResult {
    public $images = [];
    public $bookmark = null;
}
$header_function = function ($ch, $rawheader) use (&$csrftoken) {
    $len = strlen($rawheader);
    $header = explode(":", $rawheader, 2);
    if (count($header) != 2) {
        return $len;
    }
    if (trim($header[0]) !== "set-cookie") {
        return $len;
    }
    $cookie = explode(";", trim($header[1]), 2);
    $cookie = explode("=", $cookie[0], 2);
    if ($cookie[0] === "csrftoken") {
        $csrftoken = $cookie[1];
    }
    return $len;
};
$prepare_search_curl_obj = function ($query, $bookmark) use ($url, $header_function, $csrftoken) {
    $data_param_obj = ["options" => ["query" => $query]];
    if ($bookmark !== null) {
        $data_param_obj["options"]["bookmarks"] = [$bookmark];
    }
    $data_param = json_encode($data_param_obj);
    $finalurl = $url . "?data=" . urlencode($data_param);
    $headers = [];
    if ($csrftoken !== null) {
        $headers[] = "x-csrftoken: $csrftoken";
        $headers[] = "cookie: csrftoken=$csrftoken";
    }
    $ch = curl_init($finalurl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, $header_function);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($bookmark !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "data=" . urlencode($data_param));
    }
    return $ch;
};
$search = function ($query, $bookmark) use ($prepare_search_curl_obj) {
    $ch = $prepare_search_curl_obj($query, $bookmark);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo "cURL error: " . curl_error($ch);
        return new SearchResult();
    }
    $data = json_decode($response);
    $result = new SearchResult();
    if ($data && isset($data->resource_response->data->results)) {
        echo "<div class='img-container'>";
        foreach ($data->resource_response->data->results as $item) {
            $image_url = $item->images->orig->url??null;
            if ($image_url) {
                $result->images[] = $image_url;
                echo "<a class='img-result' href='/image_proxy.php?url=" . urlencode($image_url) . "'>";
                echo "<img loading='lazy' src='/image_proxy.php?url=" . urlencode($image_url) . "'></a>";
            }
        }
        echo "</div>";
    } else {
        echo "<p>No results found.</p>";
    }
    if (isset($data->resource_response->bookmark)) {
        $result->bookmark = $data->resource_response->bookmark;
    }
    return $result;
};
$result = $search($query, $bookmark);
if ($result->bookmark !== null) {
    $query_encoded = urlencode($query);
    $csrftoken_encoded = $csrftoken ? urlencode($csrftoken) : "";
    $prev_page = $page > 1 ? $page - 1 : 1;
    $next_page = $page + 1;
    $bookmark_encoded = urlencode($result->bookmark);
    echo "<div class='pagination' style='text-align: center;'>";
    if ($page > 1) {
        echo "<a href='/search.php?q=$query_encoded&bookmark=$bookmark_encoded&csrftoken=$csrftoken_encoded&page=$prev_page'>Previous</a> ";
    }
    echo "Page $page ";
    echo "<a href='/search.php?q=$query_encoded&bookmark=$bookmark_encoded&csrftoken=$csrftoken_encoded&page=$next_page'>Next</a>";
    echo "</div><br><br><br>";
}
include "misc/footer.php";
?>
