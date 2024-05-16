<?php
require "misc/tools.php";
$url = filter_var($_REQUEST["url"], FILTER_SANITIZE_URL);
$requested_root_domain = get_root_domain($url);
$allowed_domains = array("pinimg.com", "i.pinimg.com", "pinterest.com", "pin.it");
if (in_array($requested_root_domain, $allowed_domains)) {
    $image_src = request($url);
    if ($image_src !== false) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->buffer($image_src);
        if (strpos($mime_type, 'image/') === 0) {
            header("Content-Type: $mime_type");
            echo $image_src;
        } else {
            header("HTTP/1.1 415 Unsupported Media Type");
            echo "Error: The requested URL does not contain a valid image.";
        }
    } else {
        header("HTTP/1.1 404 Not Found");
        echo "Error: Unable to fetch the image.";
    }
} else {
    header("HTTP/1.1 400 Bad Request");
    echo "Error: Invalid domain.";
}
?>
