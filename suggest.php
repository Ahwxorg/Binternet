<?php

require __DIR__ . "/misc/tools.php";
require __DIR__ . "/misc/cache.php";

$term = is_string($_GET["q"] ?? null) ? trim($_GET["q"]) : "";
if ($term === "" || strlen($term) > 64) {
    http_response_code(400);
    exit();
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: public, max-age=300");

// Search suggestions are public and cheap, so cache them like search results.
$cache_key = "suggest\n" . $term;
$cached = cache_get($cache_key, 300);
if ($cached !== null) {
    echo $cached;
    exit();
}

$source_url = "/search/pins/?q=" . rawurlencode($term);
$response = pinterest_resource("AdvancedTypeaheadResource", $source_url, "www/search/[scope].js", [
    "pin_scope" => "pins",
    "autocomplete_request_surface" => 0,
    "count" => 8,
    "term" => $term,
], true);

$items = [];
foreach ($response->data->items ?? [] as $item) {
    $query = $item->query ?? null;
    if (($item->type ?? "") === "query" && is_string($query) && $query !== "") {
        $items[$query] = true;
    }
}

$body = json_encode(array_keys($items), JSON_UNESCAPED_UNICODE);
cache_set($cache_key, $body);
echo $body;
