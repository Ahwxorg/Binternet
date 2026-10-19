<?php

require_once __DIR__ . "/media.php";

function get_root_domain($url)
{
  return parse_url($url, PHP_URL_HOST);
}

function request($url)
{
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_HEADER, false);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($ch);

  return $response;
}

// Pinterest rejects the request ("Invalid Resource Request") unless the
// pws-handler header names the web page that would make this call.
function pinterest_resource($resource, $source_url, $handler, $options)
{
  $data = urlencode(json_encode(["options" => $options]));
  $url = "https://www.pinterest.com/resource/$resource/get/?source_url=" . urlencode($source_url) . "&data=$data";
  $http_version = defined("CURL_HTTP_VERSION_2TLS") ? CURL_HTTP_VERSION_2TLS : CURL_HTTP_VERSION_1_1;

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_ENCODING => "",
    CURLOPT_HTTP_VERSION => $http_version,
    CURLOPT_TCP_KEEPALIVE => 1,
    CURLOPT_TCP_NODELAY => 1,
    CURLOPT_HTTPHEADER => [
      "User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:156.0) Gecko/20100101 Firefox/156.0",
      "Referer: https://www.pinterest.com/",
      "x-requested-with: XMLHttpRequest",
      "x-pinterest-appstate: active",
      "x-pinterest-source-url: $source_url",
      "x-pinterest-pws-handler: $handler",
    ],
  ]);
  $body = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  unset($ch);

  if ($status === 404) {
    return null;
  }
  if ($body === false || $status !== 200) {
    error_page(502, "Pinterest did not respond, try again later");
  }

  $response = json_decode($body);
  if (!isset($response->resource_response)) {
    error_page(502, "Pinterest did not respond, try again later");
  }

  return $response->resource_response;
}

// Some originals are HEIC, which most browsers can't display.
function pin_image($pin)
{
  $url = $pin->images->orig->url ?? $pin->images->{"736x"}->url ?? null;
  if ($url !== null && preg_match('/\.heic(?:[?#]|$)/i', $url)) {
    $url = $pin->images->{"736x"}->url ?? null;
  }

  return $url;
}

function download_icon_svg()
{
  return '<svg class="download-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="18" height="18" aria-hidden="true">'
    . '<path fill="currentColor" d="M480-320 280-520l56-58 104 104v-326h80v326l104-104 56 58-200 200ZM240-160q-33 0-56.5-23.5T160-240v-120h80v120h480v-120h80v120q0 33-23.5 56.5T720-160H240Z"/>'
    . '</svg>';
}

function play_icon_svg()
{
  return '<svg class="play-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true">'
    . '<path fill="currentColor" d="M8 5v14l11-7z"/>'
    . '</svg>';
}

function pins_have_video($pins)
{
  foreach ($pins as $pin) {
    if (pick_video($pin->videos ?? null) !== null) {
      return true;
    }
  }
  return false;
}

function print_pins($pins)
{
  echo "<div class='img-container'>";
  $index = 0;

  foreach ($pins as $pin) {
    $title = (string) ($pin->title ?? "");
    $description = (string) ($pin->description ?? "");

    $author_name = (string) ($pin->pinner->full_name ?? "");
    $author_user = (string) ($pin->pinner->username ?? "");
    if ($author_name === "") {
      $author_name = $author_user !== "" ? $author_user : "Pinterest";
    }
    $author_url = $author_user !== "" ? "/user.php?user=" . rawurlencode($author_user) : "";
    $credit = "<p>media by: ";
    if ($author_url !== "") {
      $credit .= "<a class='uploader' href='" . htmlspecialchars($author_url) . "'>" . htmlspecialchars($author_name) . "</a>";
    } else {
      $credit .= htmlspecialchars($author_name);
    }
    $credit .= "</p>";

    $eager = $index < 4;
    $video = pick_video($pin->videos ?? null);
    $image = pin_image($pin);

    if ($video !== null) {
      $video_path = media_proxy_path($video["url"]);
      if ($video_path !== "") {
        $poster = $image !== null
          ? display_image($pin->images ?? null, $image)
          : (string) ($video["poster"] ?? "");
        $poster_path = $poster !== "" ? media_proxy_path($poster) : "";
        $download_name = pin_filename($title !== "" ? $title : $description, $video["url"], $video["hls"]);
        $download = $video_path . (str_contains($video_path, "?") ? "&" : "?") . "download=1&name=" . rawurlencode($download_name);
        $alt = $title !== "" ? $title : ($description !== "" ? $description : "Pinterest video");
        $width = $video["width"] > 0 ? $video["width"] : (int) ($pin->images->orig->width ?? 0);
        $height = $video["height"] > 0 ? $video["height"] : (int) ($pin->images->orig->height ?? 0);

        echo "<div class='img-result'>";
        echo "<div class='media-frame'>";
        echo "<video preload='none' playsinline";
        if ($poster_path !== "") {
          echo " data-poster='" . htmlspecialchars($poster_path) . "'";
        }
        if ($width > 0 && $height > 0) {
          echo " width='" . $width . "' height='" . $height . "'";
        }
        echo " " . ($video["hls"] ? "data-hls" : "data-src") . "='" . htmlspecialchars($video_path) . "'";
        echo " aria-label='" . htmlspecialchars($alt) . "'></video>";
        echo "<button class='video-play' type='button' title='Play video' aria-label='Play video'>" . play_icon_svg() . "</button>";
        echo "<a class='media-download' href='" . htmlspecialchars($download)
          . "' download='" . htmlspecialchars($download_name)
          . "' title='Download video' aria-label='Download video'>" . download_icon_svg() . "</a>";
        echo "</div>";
        echo $credit;
        echo "</div>";

        $index++;
        continue;
      }
    }

    if ($image === null) {
      continue;
    }

    $display = display_image($pin->images ?? null, $image);
    $src = media_proxy_path($display);
    if ($src === "") {
      continue;
    }

    $id = (string) ($pin->id ?? "");
    $link = ($id !== "" && preg_match('/^\d+$/D', $id))
      ? "/pin.php?id=" . rawurlencode($id)
      : $src;
    $download_name = pin_filename($title !== "" ? $title : $description, $image);
    $download = media_proxy_path($image);
    if ($download !== "") {
      $download .= (str_contains($download, "?") ? "&" : "?") . "download=1&name=" . rawurlencode($download_name);
    }

    $alt = $title !== "" ? $title : ($description !== "" ? $description : "Pinterest image");
    $width = (int) ($pin->images->orig->width ?? 0);
    $height = (int) ($pin->images->orig->height ?? 0);
    $is_gif = is_gif_image($image);

    echo "<div class='img-result'>";
    echo "<div class='media-frame'>";
    echo "<a href='" . htmlspecialchars($link) . "'>";
    echo "<img loading='" . ($eager ? "eager" : "lazy") . "'"
      . ($eager ? " fetchpriority='high'" : "")
      . " decoding='async'";
    if ($width > 0 && $height > 0) {
      echo " width='" . $width . "' height='" . $height . "'";
    }
    echo " src='" . htmlspecialchars($src) . "' alt='" . htmlspecialchars($alt) . "'>";
    echo "</a>";

    if ($download !== "") {
      $label = $is_gif ? "Download GIF" : "Download image";
      echo "<a class='media-download' href='" . htmlspecialchars($download)
        . "' download='" . htmlspecialchars($download_name)
        . "' title='" . $label . "' aria-label='" . $label . "'>"
        . download_icon_svg() . "</a>";
    }
    echo "</div>";
    echo $credit;
    echo "</div>";

    $index++;
  }

  echo "</div>";
}

function print_next_page($page, $bookmark, $params)
{
  if ($bookmark === null || $bookmark === "-end-") {
    return;
  }
  $params["bookmark"] = $bookmark;
  $href = "/" . ltrim($page, "/") . "?" . http_build_query($params);
  echo "<h2 class=\"next-page\"><a href=\"" . htmlspecialchars($href) . "\">Next page</a></h2>";
}

function error_page($status, $message)
{
  http_response_code($status);
  $message = htmlspecialchars($message);
  require "misc/header.php";
  echo "<title>$message - Binternet</title></head><body><div class='info'><p>$message.</p></div>";
  include "misc/footer.php";
  echo "</body></html>";
  exit();
}

function not_found()
{
  error_page(404, "Not found");
}
