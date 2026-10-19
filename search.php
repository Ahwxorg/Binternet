<?php

require_once "misc/tools.php";
require_once "misc/media.php";
require_once "misc/cache.php";

// Pinterest API endpoint and short-lived result cache.
$url = "https://www.pinterest.com/resource/BaseSearchResource/get/";
$cache_ttl = 120;

$query = is_string($_REQUEST["q"] ?? null) ? trim($_REQUEST["q"]) : '';
if (strlen($query) < 1 || strlen($query) > 64) {
    header("Location: ./");
    exit();
}

$bookmark = is_string($_GET["bookmark"] ?? null) ? $_GET["bookmark"] : null;
$csrftoken = $_GET["csrftoken"] ?? null;
if (!is_string($csrftoken) || !preg_match('/^[A-Za-z0-9_-]{1,256}$/', $csrftoken)) {
    $csrftoken = null;
}

$media_filter = $_GET["media"] ?? "all";
if (!is_string($media_filter) || !in_array($media_filter, ["all", "image", "gif", "video"], true)) {
    $media_filter = "all";
}

// Header function to capture CSRF token from response
$header_function = function ($ch, $rawheader) use (&$csrftoken) {
    if (preg_match('/^set-cookie:\s*csrftoken=([^;]*)/i', $rawheader, $matches)) {
        $csrftoken = $matches[1];
    }
    return strlen($rawheader);
};

// Prepare CURL object for search request
$prepare_search_curl_obj = function ($query, $bookmark) use ($url, $header_function, &$csrftoken) {
    $data_param_obj = [
        "options" => [
            "query" => $query,
            "scope" => "pins",
            "filter_genai" => true,
            "source_url" => "/search/pins/?q=$query&rs=typed",
            "page_size" => 50,
        ],
    ];

    if ($bookmark !== null) {
        $data_param_obj["options"]["bookmarks"] = [$bookmark];
    }

    $data_param = urlencode(json_encode($data_param_obj));
    $headers = [
        "x-pinterest-pws-handler: www/search/[scope].js",
        "User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:156.0) Gecko/20100101 Firefox/156.0",
        "Referer: https://www.pinterest.com/",
        "x-requested-with: XMLHttpRequest",
        "x-pinterest-appstate: active",
        "x-pinterest-source-url: /search/pins/?q=kitties&rs=typed",
    ];

    if ($csrftoken !== null) {
        $headers[] = "x-csrftoken: $csrftoken";
        $headers[] = "cookie: csrftoken=$csrftoken";
    }

    $finalurl = $bookmark === null ? "$url?data=$data_param" : $url;
    $ch = curl_init($finalurl);
    $http_version = defined("CURL_HTTP_VERSION_2TLS") ? CURL_HTTP_VERSION_2TLS : CURL_HTTP_VERSION_1_1;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADERFUNCTION => $header_function,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_ENCODING => "",
        CURLOPT_HTTP_VERSION => $http_version,
        CURLOPT_TCP_KEEPALIVE => 1,
        CURLOPT_TCP_NODELAY => 1,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
    ]);

    if ($bookmark !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "data=$data_param");
    }

    return $ch;
};

// Run the search and return structured pins. Video pins prefer MP4, but
// anonymous Pinterest search mostly returns HLS.
$search = function ($query, $bookmark) use ($prepare_search_curl_obj, &$csrftoken, $cache_ttl) {
    $cache_key = $query . "\n" . ($bookmark ?? '');
    $response = cache_get($cache_key, $cache_ttl);
    $from_cache = $response !== null;

    if (!$from_cache) {
        // Bookmarked pages are POSTs and need the anonymous token. A fresh
        // process has none until its first GET, so prime it when needed.
        if ($bookmark !== null && $csrftoken === null) {
            $prime = $prepare_search_curl_obj($query, null);
            curl_exec($prime);
            unset($prime);
        }

        $ch = $prepare_search_curl_obj($query, $bookmark);
        $response = curl_exec($ch);
        unset($ch);
        if ($response === false) {
            return ["pins" => [], "bookmark" => null];
        }
    }

    $data = json_decode($response);
    $pins = [];

    if (!$from_cache && $data && isset($data->resource_response->data->results)) {
        cache_set($cache_key, $response);
        cache_prune($cache_ttl);
    }
    if (!headers_sent()) {
        header("X-Binternet-Cache: " . ($from_cache ? "HIT" : "MISS"));
    }

    if ($data && isset($data->resource_response->data->results)) {
        foreach ($data->resource_response->data->results as $result) {
            $video = pick_video($result->videos ?? null);
            $image = (string) (pin_image($result) ?? '');
            if ($image === '' && $video !== null) {
                $image = $video["poster"];
            }
            if ($image === '' && $video === null) {
                continue;
            }

            $display = display_image($result->images ?? null, $image);
            $poster = $display !== '' ? $display : ($video["poster"] ?? $image);
            $video_path = "";
            if ($video !== null) {
                $video_path = media_proxy_path($video["url"]);
                if ($video_path === "") {
                    $video = null;
                }
            }
            if ($video === null && $display === '') {
                continue;
            }

            $width = (int) ($result->images->orig->width ?? 0);
            $height = (int) ($result->images->orig->height ?? 0);
            if ($video !== null && $video["width"] > 0 && $video["height"] > 0) {
                $width = $video["width"];
                $height = $video["height"];
            }

            $pins[] = [
                "image" => $image,
                "display" => $display,
                "poster" => $poster,
                "video" => $video,
                "video_path" => $video_path,
                "width" => $width,
                "height" => $height,
                "title" => (string) ($result->title ?? ''),
                "description" => (string) ($result->description ?? ''),
                "author_name" => (string) ($result->pinner->full_name ?? ''),
                "author_user" => (string) ($result->pinner->username ?? ''),
                "is_gif" => is_gif_image($image),
            ];
        }
    }

    $bookmark = $data->resource_response->bookmark ?? null;
    return [
        "pins" => $pins,
        "bookmark" => is_string($bookmark) && $bookmark !== '' ? $bookmark : null,
    ];
};

$result = $search($query, $bookmark);
$pins = $result["pins"];

if ($media_filter !== "all") {
    $pins = array_values(array_filter($pins, function ($pin) use ($media_filter) {
        if ($media_filter === "video") {
            return $pin["video"] !== null;
        }
        if ($pin["video"] !== null) {
            return false;
        }
        if ($media_filter === "gif") {
            return $pin["is_gif"];
        }
        return !$pin["is_gif"];
    }));
}

$result_count = count($pins);
$needs_filter = true;

$needs_player = false;
foreach ($pins as $pin) {
    if ($pin["video"] !== null) {
        $needs_player = true;
        break;
    }
}

$esc = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$download_icon = download_icon_svg();
$play_icon = play_icon_svg();

require "misc/header.php";
?>
<title><?php echo $esc($query); ?> - Binternet</title>
</head>
<body>
    <header class="search-header">
        <form class="search-container" method="get" autocomplete="off">
            <h1><a class="no-decoration accent" href="./">Binternet</a></h1>
            <div class="search-field">
                <input type="text" name="q" placeholder="Search images and videos" value="<?php echo $esc($query); ?>">
                <span class="result-count"><?php echo (int) $result_count; ?> result<?php echo $result_count === 1 ? "" : "s"; ?> found</span>
                <select name="media" aria-label="Filter results">
<?php foreach (["all" => "All", "image" => "Images", "gif" => "GIFs", "video" => "Videos"] as $value => $label): ?>
                    <option value="<?php echo $value; ?>"<?php echo $media_filter === $value ? " selected" : ""; ?>><?php echo $label; ?></option>
<?php endforeach; ?>
                </select>
                <noscript><button type="submit">Filter</button></noscript>
            </div>
        </form>
    </header>

<?php
echo "<div class='img-container'>";

if ($result_count === 0) {
    $label = $media_filter === "video"
        ? "videos"
        : ($media_filter === "gif" ? "GIFs" : ($media_filter === "image" ? "images" : "results"));
    echo "<p>No $label found. Try a different filter or query.</p>";
}

foreach ($pins as $index => $pin) {
    $author_name = $pin["author_name"] !== '' ? $pin["author_name"] : $pin["author_user"];
    if ($author_name === '') {
        $author_name = "Pinterest";
    }
    $author_url = $pin["author_user"] !== ''
        ? "https://pinterest.com/" . rawurlencode($pin["author_user"])
        : "";
    $alt = $pin["title"] !== '' ? $pin["title"] : $pin["description"];
    if ($alt === '') {
        $alt = $pin["video"] !== null ? "Pinterest video" : "Pinterest image";
    }

    echo "<div class='img-result'>";

    if ($pin["video"] !== null) {
        $video = $pin["video"];
        $source = $esc($pin["video_path"]);
        $poster = media_proxy_path($pin["poster"]);

        $download_name = pin_filename(
            $pin["title"] !== "" ? $pin["title"] : $pin["description"],
            $video["url"],
            $video["hls"]
        );
        $download_url = $pin["video_path"] !== ""
            ? $pin["video_path"] . (str_contains($pin["video_path"], "?") ? "&" : "?") . "download=1&name=" . rawurlencode($download_name)
            : "";

        echo "<div class='media-frame'>";
        echo "<video preload='none' playsinline";
        if ($poster !== '') {
            echo " data-poster='" . $esc($poster) . "'";
        }
        if ($video["width"] > 0 && $video["height"] > 0) {
            echo " width='" . $video["width"] . "' height='" . $video["height"] . "'";
        }
        echo " " . ($video["hls"] ? "data-hls" : "data-src") . "='" . $source . "'";
        echo " aria-label='" . $esc($alt) . "'></video>";
        echo "<button class='video-play' type='button' title='Play video' aria-label='Play video'>"
            . $play_icon . "</button>";

        if ($download_url !== "") {
            echo "<a class='media-download' href='" . $esc($download_url)
                . "' download='" . $esc($download_name)
                . "' title='Download video' aria-label='Download video'>"
                . $download_icon . "</a>";
        }
        echo "</div>";
    } else {
        $href = media_proxy_path($pin["image"]);
        $src = media_proxy_path($pin["display"]);
        if ($src === "") {
            continue;
        }
        if ($href === "") {
            $href = $src;
        }

        $download_name = pin_filename(
            $pin["title"] !== "" ? $pin["title"] : $pin["description"],
            $pin["image"]
        );
        $download_url = media_proxy_path($pin["image"]);
        if ($download_url !== "") {
            $download_url .= (str_contains($download_url, "?") ? "&" : "?") . "download=1&name=" . rawurlencode($download_name);
        }

        echo "<div class='media-frame'>";
        echo "<a href='" . $esc($href) . "'>";
        echo "<img loading='" . ($index < 4 ? "eager" : "lazy") . "'"
            . ($index < 4 ? " fetchpriority='high'" : "")
            . " decoding='async'";
        if ($pin["width"] > 0 && $pin["height"] > 0) {
            echo " width='" . $pin["width"] . "' height='" . $pin["height"] . "'";
        }
        echo " src='" . $esc($src) . "' alt='" . $esc($alt) . "'>";
        echo "</a>";
        if ($download_url !== "") {
            $label = $pin["is_gif"] ? "Download GIF" : "Download image";
            echo "<a class='media-download' href='" . $esc($download_url)
                . "' download='" . $esc($download_name)
                . "' title='" . $label . "' aria-label='" . $label . "'>"
                . $download_icon . "</a>";
        }
        echo "</div>";
    }

    echo "<p>media by: ";
    if ($author_url !== '') {
        echo "<a class='uploader' href='" . $esc($author_url) . "'>" . $esc($author_name) . "</a>";
    } else {
        echo $esc($author_name);
    }
    echo "</p>";
    echo "</div>";
}

echo "</div>";
?>

<?php
// Pagination link for the next page
if ($result["bookmark"] !== null) {
    $params = ["q" => $query, "bookmark" => $result["bookmark"]];
    if ($media_filter !== "all") {
        $params["media"] = $media_filter;
    }
    if ($csrftoken !== null) {
        $params["csrftoken"] = $csrftoken;
    }
    echo "<h2 class=\"next-page\"><a href=\"/search.php?"
        . $esc(http_build_query($params)) . "\">Next page</a></h2>";
}
?>

<?php include "misc/footer.php"; ?>
</body>
</html>
