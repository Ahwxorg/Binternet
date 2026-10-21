<?php
    function get_root_domain($url) {
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
      $ch = curl_init("https://www.pinterest.com/resource/$resource/get/?source_url=" . urlencode($source_url) . "&data=$data");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_TIMEOUT, 15);
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:156.0) Gecko/20100101 Firefox/156.0",
        "Referer: https://www.pinterest.com/",
        "x-requested-with: XMLHttpRequest",
        "x-pinterest-appstate: active",
        "x-pinterest-source-url: $source_url",
        "x-pinterest-pws-handler: $handler",
      ]);
      $response = json_decode(curl_exec($ch));
      $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

      if ($status === 404) {
        return null;
      }
      if ($status !== 200 || !isset($response->resource_response)) {
        error_page(502, "Pinterest did not respond, try again later");
      }

      return $response->resource_response;
    }

    // Some originals are HEIC, which most browsers can't display.
    function pin_image($pin)
    {
      $url = $pin->images->orig->url ?? null;
      if ($url !== null && str_ends_with($url, ".heic")) {
        $url = $pin->images->{"736x"}->url ?? null;
      }

      return $url;
    }

    function print_pins($pins)
    {
      echo "<div class='img-container'>";
      foreach ($pins as $pin) {
        $url = pin_image($pin);
        if ($url === null) {
          continue;
        }
        $proxy = "/image_proxy.php?url=" . htmlspecialchars(urlencode($url));
        echo "<div class='img-result'><a href='/pin.php?id=" . htmlspecialchars($pin->id) . "'><img loading='lazy' src='$proxy'></a></div>";
      }
      echo "</div>";
    }

    function print_next_page($page, $bookmark, $params)
    {
      if ($bookmark === null || $bookmark === "-end-") {
        return;
      }
      $params["bookmark"] = $bookmark;
      $href = htmlspecialchars("/$page?" . http_build_query($params));
      echo "<h2 class=\"next-page\"><a href=\"$href\">Next page</a></h2><br><br><br>";
    }

    function error_page($status, $message)
    {
      http_response_code($status);
      require "misc/header.php";
      echo "<title>$message - Binternet</title></head><body><p>$message.</p>";
      include "misc/footer.php";
      exit();
    }

    function not_found()
    {
      error_page(404, "Not found");
    }
?>
