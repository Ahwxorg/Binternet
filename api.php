<?php

// Prepare cURL object with options for the search query
function prepareSearchCurlObj($query, $bookmark = null, $url, $csrftoken = null, $header_function) {
    $data_param_obj = [
        "options" => [
            "query" => $query,
            "bookmarks" => $bookmark ? [$bookmark] : null
        ]
    ];

    $data_param = urlencode(json_encode(array_filter($data_param_obj['options'])));

    $headers = [];
    if ($csrftoken) {
        $headers[] = "x-csrftoken: $csrftoken";
        $headers[] = "cookie: csrftoken=$csrftoken";
    }

    $finalurl = $bookmark ? $url : "$url?data=$data_param";

    $ch = curl_init($finalurl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, $header_function);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($bookmark) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "data=$data_param");
    }

    return $ch;
}

// Search function to execute the cURL request and process the response
function search($query, $bookmark, $url, $csrftoken, $header_function) {
    $ch = prepareSearchCurlObj($query, $bookmark, $url, $csrftoken, $header_function);
    
    $response = curl_exec($ch);
    
    if ($response === false) {
        // Handle cURL error
        $error = curl_error($ch);
        curl_close($ch);
        return json_encode(['error' => 'CURL Error: ' . $error]);
    }

    curl_close($ch); // Close cURL handle

    $data = json_decode($response);
    $images = [];

    if (isset($data->resource_response->data->results)) {
        foreach ($data->resource_response->data->results as $result) {
            $url = $result->images->orig->url ?? null; // Use null coalescing for safety
            if ($url) {
                $images[] = $url;
            }
        }
    }

    $result = new SearchResult();
    $result->images = $images;
    $result->bookmark = $data->resource_response->bookmark ?? null;

    return $result;
}

// Main execution
header("Content-Type: application/json");
$result = search($query, $bookmark, $url, $csrftoken, $header_function);

// Handle bookmark for pagination
if ($result->bookmark) {
    $query_encoded = urlencode($query);
    $bookmark_encoded = urlencode($result->bookmark);
    $csrftoken_encoded = urlencode($csrftoken);
    // Uncomment below line to display the link for next page
    // echo "<h2 style=\"text-align: center;\"><a href=\"/search.php?q=$query_encoded&bookmark=$bookmark_encoded&csrftoken=$csrftoken_encoded\">Next page</a></h2><br><br><br>";
}

echo json_encode($result);
?>
