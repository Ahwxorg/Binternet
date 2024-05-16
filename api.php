<?php
require "misc/tools.php";
$query = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_STRING);
if (!$query) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid query parameter."]);
    exit;
}
$bookmark = isset($_GET["bookmark"]) ? urldecode($_GET["bookmark"]) : null;
$csrftoken = $_GET["csrftoken"]??null;
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
    if (trim($header[0]) != "set-cookie") {
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
    $headers = [];
    if ($csrftoken !== null) {
        $headers[] = "x-csrftoken: $csrftoken";
        $headers[] = "cookie: csrftoken=$csrftoken";
    }
    $finalurl = "$url?data=" . urlencode($data_param);
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
        http_response_code(500);
        echo json_encode(["error" => curl_error($ch) ]);
        curl_close($ch);
        exit;
    }
    $data = json_decode($response);
    curl_close($ch);
    $result = new SearchResult();
    if (isset($data->resource_response->data->results)) {
        foreach ($data->resource_response->data->results as $item) {
            if (isset($item->images->orig->url)) {
                $result->images[] = $item->images->orig->url;
            }
        }
    }
    if (isset($data->resource_response->bookmark)) {
        $result->bookmark = $data->resource_response->bookmark;
    }
    return $result;
};
$result = $search($query, $bookmark);
header("Content-Type: application/json");
echo json_encode($result);
?>
