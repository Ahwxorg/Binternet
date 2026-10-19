<?php

require "misc/tools.php";

$id = $_GET["id"] ?? "";
if (!is_string($id) || !preg_match('/^\d+$/D', $id)) {
    not_found();
}

$response = pinterest_resource("PinResource", "/pin/$id/", "www/pin/[id].js", [
    "id" => $id,
    "field_set_key" => "detailed",
]);
$pin = $response->data ?? null;
if ($pin === null) {
    not_found();
}

$raw_title = (string) (($pin->title ?? "") ?: ($pin->grid_title ?? "") ?: "Pin");
$raw_description = trim((string) ($pin->description ?? ""));
$filename_title = (string) (($pin->title ?? "") ?: ($pin->grid_title ?? "") ?: ($pin->description ?? "") ?: "Pinterest pin");
$title = htmlspecialchars($raw_title);
$description = htmlspecialchars($raw_description);

$image = pin_image($pin);
$video = pick_video($pin->videos ?? null);
$video_path = "";
if ($video !== null) {
    $video_path = media_proxy_path($video["url"]);
    if ($video_path === "") {
        $video = null;
    }
}

if ($video === null && $image === null) {
    not_found();
}

$needs_player = $video !== null;
$poster = $image !== null
    ? display_image($pin->images ?? null, $image)
    : (string) ($video["poster"] ?? "");
$poster_path = $poster !== "" ? media_proxy_path($poster) : "";

if ($video !== null) {
    $download_name = pin_filename($filename_title, $video["url"], $video["hls"]);
    $download = $video_path . (str_contains($video_path, "?") ? "&" : "?") . "download=1&name=" . rawurlencode($download_name);
    $width = $video["width"] > 0 ? $video["width"] : (int) ($pin->images->orig->width ?? 0);
    $height = $video["height"] > 0 ? $video["height"] : (int) ($pin->images->orig->height ?? 0);
    $download_label = "Download video";
} else {
    $proxy = media_proxy_path($image);
    if ($proxy === "") {
        not_found();
    }
    $download_name = pin_filename($filename_title, $image);
    $download = $proxy . (str_contains($proxy, "?") ? "&" : "?") . "download=1&name=" . rawurlencode($download_name);
    $download_label = is_gif_image($image) ? "Download GIF" : "Download image";
}

$author_user = (string) ($pin->pinner->username ?? "");
$author_name = (string) ($pin->pinner->full_name ?? "");
if ($author_name === "") {
    $author_name = $author_user;
}

require "misc/header.php";
?>
<title><?php echo $title; ?> - Binternet</title>
</head>
<body>
    <header class="search-header">
        <form class="search-container" action="/search.php" method="get" autocomplete="off">
            <h1><a class="no-decoration accent" href="/">Binternet</a></h1>
            <div class="search-field">
                <input type="text" name="q" placeholder="Search images and videos">
            </div>
        </form>
    </header>
    <div class="info">
        <h2><?php echo $title; ?></h2>
        <div class="media-frame">
            <?php if ($video !== null) { ?>
                <video preload="none" playsinline
                    <?php if ($poster_path !== "") { ?>data-poster="<?php echo htmlspecialchars($poster_path); ?>"<?php } ?>
                    <?php if ($width > 0 && $height > 0) { ?>width="<?php echo $width; ?>" height="<?php echo $height; ?>"<?php } ?>
                    <?php echo $video["hls"] ? "data-hls" : "data-src"; ?>="<?php echo htmlspecialchars($video_path); ?>"
                    aria-label="<?php echo $title; ?>"></video>
                <button class="video-play" type="button" title="Play video" aria-label="Play video"><?php echo play_icon_svg(); ?></button>
            <?php } else { ?>
                <a href="<?php echo htmlspecialchars($proxy); ?>"><img src="<?php echo htmlspecialchars($proxy); ?>" alt="<?php echo $title; ?>"></a>
            <?php } ?>
            <a class="media-download" href="<?php echo htmlspecialchars($download); ?>"
                download="<?php echo htmlspecialchars($download_name); ?>"
                title="<?php echo $download_label; ?>" aria-label="<?php echo $download_label; ?>"><?php echo download_icon_svg(); ?></a>
        </div>
        <?php if ($description !== "") { ?>
            <p><?php echo $description; ?></p>
        <?php } ?>
        <?php if ($author_user !== "") { ?>
            <p>media by: <a class="uploader" href="/user.php?user=<?php echo rawurlencode($author_user); ?>"><?php echo htmlspecialchars($author_name); ?></a></p>
        <?php } ?>
    </div>
<?php include "misc/footer.php"; ?>
</body>
</html>
