<?php

require __DIR__ . "/misc/media.php";
require __DIR__ . "/misc/hls.php";

// Single media proxy for images, GIFs, videos and HLS. The path-based
// /media/{host}/{path} shape keeps relative HLS playlist references inside the
// proxy, so manifests and segments never reach Pinterest from the visitor's
// browser. The legacy /image_proxy.php?url=... route redirects into it.

$request_path = parse_url($_SERVER["REQUEST_URI"] ?? "", PHP_URL_PATH);
if ($request_path === "/image_proxy.php") {
    $url = $_GET["url"] ?? null;
    if (!is_string($url) || !valid_media_url($url)) {
        http_response_code(400);
        exit;
    }
    $target = media_proxy_path($url);
    if ($target === "") {
        http_response_code(400);
        exit;
    }
    header("Cache-Control: public, max-age=3600");
    header("Location: " . $target, true, 302);
    exit;
}

$request_uri = $_SERVER["REQUEST_URI"] ?? "";
$path_only = explode("?", $request_uri, 2)[0];
$prefix = "/media/";
if (!str_starts_with($path_only, $prefix)) {
    http_response_code(404);
    exit;
}

$rest = substr($path_only, strlen($prefix));
$slash = strpos($rest, "/");
if ($slash === false) {
    http_response_code(404);
    exit;
}

$host = strtolower(substr($rest, 0, $slash));
$media_path = substr($rest, $slash);
if (!valid_media_host($host) || !valid_media_path($media_path)) {
    http_response_code(404);
    exit;
}

$query = media_upstream_query($_SERVER["QUERY_STRING"] ?? "");
$target = "https://" . $host . $media_path . ($query !== "" ? "?" . $query : "");
$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
if ($method !== "GET" && $method !== "HEAD") {
    http_response_code(405);
    exit;
}

set_time_limit(0);
ini_set("zlib.output_compression", "0");
while (ob_get_level() > 0) {
    ob_end_clean();
}

$download = isset($_GET["download"]);
$download_name = "";
if ($download) {
    $requested_name = $_GET["name"] ?? "";
    if (!is_string($requested_name)) {
        $requested_name = "";
    }
    $requested_name = safe_download_name($requested_name);

    if (is_hls($media_path)) {
        if ($method === "HEAD") {
            http_response_code(405);
            exit;
        }
        if (stream_hls_download($target, $media_path, $requested_name)) {
            exit;
        }
        if (!headers_sent()) {
            http_response_code(502);
            header("Content-Type: text/plain; charset=utf-8");
            echo "Video download is unavailable.";
        }
        exit;
    }
    $download_name = $requested_name !== "" ? $requested_name : media_filename($media_path);
}

$redirects = 0;
while (true) {
    $outcome = stream_media($target, $method, $download_name);

    if ($outcome["redirect"] !== null) {
        if (++$redirects > 5) {
            if (!headers_sent()) {
                http_response_code(502);
            }
            exit;
        }
        $target = resolve_media_url($target, $outcome["redirect"]);
        if (!valid_media_url($target)) {
            if (!headers_sent()) {
                http_response_code(502);
            }
            exit;
        }
        continue;
    }

    if (!$outcome["ok"] && !headers_sent()) {
        http_response_code(($outcome["status"] ?? 0) === 404 ? 404 : 502);
    }
    exit;
}

function stream_media(string $url, string $method, string $download_name = ""): array
{
    $state = [
        "status" => 0,
        "headers" => [],
        "started" => false,
        "redirect" => null,
        "rejected" => false,
        "download" => $download_name,
    ];

    $request_headers = [
        "Accept: */*",
        "Referer: https://www.pinterest.com/",
    ];
    foreach (["range", "if-range", "if-none-match", "if-modified-since"] as $name) {
        $key = "HTTP_" . strtoupper(str_replace("-", "_", $name));
        if (!empty($_SERVER[$key])) {
            $request_headers[] = $name . ": " . $_SERVER[$key];
        }
    }

    $http_version = defined("CURL_HTTP_VERSION_2TLS") ? CURL_HTTP_VERSION_2TLS : CURL_HTTP_VERSION_1_1;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_LOW_SPEED_LIMIT => 1,
        CURLOPT_LOW_SPEED_TIME => 30,
        CURLOPT_HTTP_VERSION => $http_version,
        CURLOPT_TCP_KEEPALIVE => 1,
        CURLOPT_TCP_NODELAY => 1,
        CURLOPT_BUFFERSIZE => 65536,
        CURLOPT_USERAGENT => "Mozilla/5.0 (X11; Linux x86_64; rv:156.0) Gecko/20100101 Firefox/156.0",
        CURLOPT_HTTPHEADER => $request_headers,
        CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$state) {
            return handle_media_header($line, $state);
        },
        CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$state) {
            if (!$state["started"] || $state["rejected"] || connection_aborted()) {
                return 0;
            }
            echo $data;
            flush();
            return strlen($data);
        },
    ]);
    if ($method === "HEAD") {
        curl_setopt($ch, CURLOPT_NOBODY, true);
    }

    curl_exec($ch);
    $error = curl_errno($ch);
    unset($ch);

    if ($state["redirect"] !== null) {
        return ["redirect" => $state["redirect"], "ok" => false, "status" => $state["status"]];
    }
    if ($state["rejected"] || ($error !== 0 && !$state["started"])) {
        return ["redirect" => null, "ok" => false, "status" => $state["status"]];
    }
    return ["redirect" => null, "ok" => $state["started"], "status" => $state["status"]];
}

function handle_media_header(string $line, array &$state): int
{
    $length = strlen($line);
    $trimmed = trim($line);

    if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $trimmed, $matches)) {
        $state["status"] = (int) $matches[1];
        $state["headers"] = [];
        return $length;
    }

    if ($trimmed === "") {
        if (in_array($state["status"], [301, 302, 303, 307, 308], true)) {
            $location = $state["headers"]["location"] ?? "";
            if ($location !== "") {
                $state["redirect"] = $location;
                return 0;
            }
        }
        if ($state["status"] < 200 || $state["status"] >= 400) {
            $state["rejected"] = true;
            return 0;
        }
        if ($state["status"] !== 304 && !allowed_media_content_type($state["headers"]["content-type"] ?? "")) {
            $state["rejected"] = true;
            return 0;
        }

        send_media_headers($state);
        $state["started"] = true;
        return $length;
    }

    $colon = strpos($line, ":");
    if ($colon === false) {
        return $length;
    }
    $name = strtolower(trim(substr($line, 0, $colon)));
    $value = trim(substr($line, $colon + 1));
    if ($name === "") {
        return $length;
    }
    if (isset($state["headers"][$name])) {
        $state["headers"][$name] .= ", " . $value;
    } else {
        $state["headers"][$name] = $value;
    }
    return $length;
}

function send_media_headers(array $state): void
{
    http_response_code($state["status"]);

    $forward = [
        "content-type",
        "content-length",
        "content-range",
        "accept-ranges",
        "etag",
        "last-modified",
        "cache-control",
    ];
    foreach ($forward as $name) {
        if (!isset($state["headers"][$name])) {
            continue;
        }
        $value = str_replace(["\r", "\n"], "", $state["headers"][$name]);
        header(header_name($name) . ": " . $value, true);
    }
    if (!isset($state["headers"]["content-type"]) && $state["status"] !== 304) {
        header("Content-Type: application/octet-stream");
    }
    if (!isset($state["headers"]["cache-control"])) {
        header("Cache-Control: public, max-age=86400");
    }
    if (($state["download"] ?? "") !== "") {
        header("Content-Disposition: " . content_disposition($state["download"]));
    }

    header("X-Content-Type-Options: nosniff");
    header("X-Accel-Buffering: no");
}

function header_name(string $name): string
{
    return implode("-", array_map("ucfirst", explode("-", $name)));
}
