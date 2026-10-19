<?php

require "misc/tools.php";

$username = $_GET["user"] ?? "";
$slug = $_GET["board"] ?? "";
$bookmark = $_GET["bookmark"] ?? null;
if (!is_string($username) || !preg_match('/^\w+$/D', $username)) {
    not_found();
}
if (!is_string($slug) || $slug === "") {
    not_found();
}
if (!is_string($bookmark) && $bookmark !== null) {
    not_found();
}

$source_url = "/$username/" . rawurlencode($slug) . "/";
$board_response = pinterest_resource("BoardResource", $source_url, "www/[username]/[slug].js", [
    "username" => $username,
    "slug" => $slug,
    "field_set_key" => "detailed",
]);
$board = $board_response->data ?? null;
if ($board === null) {
    not_found();
}

$options = ["board_id" => $board->id, "page_size" => 50];
if ($bookmark !== null) {
    $options["bookmarks"] = [$bookmark];
}
$pins_response = pinterest_resource("BoardFeedResource", $source_url, "www/[username]/[slug].js", $options);
$pins = $pins_response->data ?? [];

$name = htmlspecialchars($board->name ?? "Board");
$user = htmlspecialchars($username);
$result_count = count($pins);
$needs_player = pins_have_video($pins);

require "misc/header.php";
?>
<title><?php echo $name; ?> - Binternet</title>
</head>
<body>
    <header class="search-header">
        <form class="search-container" action="/search.php" method="get" autocomplete="off">
            <h1><a class="no-decoration accent" href="/">Binternet</a></h1>
            <div class="search-field">
                <input type="text" name="q" placeholder="Search images and videos">
                <span class="result-count"><?php echo (int) $result_count; ?> result<?php echo $result_count === 1 ? "" : "s"; ?> found</span>
            </div>
        </form>
    </header>
    <div class="info">
        <h2><?php echo $name; ?></h2>
        <p>board by: <a class="uploader" href="/user.php?user=<?php echo rawurlencode($username); ?>"><?php echo $user; ?></a></p>
    </div>
<?php
print_pins($pins);
print_next_page("board.php", $pins_response->bookmark ?? null, ["user" => $username, "board" => $slug]);
include "misc/footer.php";
?>
</body>
</html>
