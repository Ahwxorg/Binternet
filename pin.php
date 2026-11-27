<?php
require "misc/tools.php";

$id = $_GET["id"] ?? "";
if (!preg_match('/^\d+$/D', $id)) {
    not_found();
}

$response = pinterest_resource("PinResource", "/pin/$id/", "www/pin/[id].js", [
    "id" => $id,
    "field_set_key" => "detailed",
]);
$pin = $response->data ?? null;
$image = $pin ? pin_image($pin) : null;
if ($image === null) {
    not_found();
}

$title = htmlspecialchars(($pin->title ?? "") ?: ($pin->grid_title ?? "") ?: "Pin");
$description = htmlspecialchars(trim($pin->description ?? ""));
$proxy = "/image_proxy.php?url=" . htmlspecialchars(urlencode($image));

require "misc/header.php";
?>
<title><?php echo $title; ?> - Binternet</title>
</head>
<body>
    <form class="search-container" action="/search.php" method="get" autocomplete="off">
        <h1><a class="no-decoration accent" href="/">Binternet</a></h1>
        <input type="text" name="q" placeholder="Search Image">
    </form>
    <div class="info">
    <h2><?php echo $title; ?></h2>
    <a href="<?php echo $proxy; ?>"><img src="<?php echo $proxy; ?>"></a>
<?php
if ($description !== "") {
    echo "<p>$description</p>";
}
if (isset($pin->pinner->username)) {
    $user = htmlspecialchars($pin->pinner->username);
    $name = htmlspecialchars(($pin->pinner->full_name ?? "") ?: $pin->pinner->username);
    echo "<p>image by: <a class='uploader' href='/user.php?user=$user'>$name</a></p>";
}
echo "</div>";

include "misc/footer.php";
?>
