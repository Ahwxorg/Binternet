<?php
require "misc/tools.php";

$username = $_GET["user"] ?? "";
$slug = $_GET["board"] ?? "";
$bookmark = $_GET["bookmark"] ?? null;
if (!preg_match('/^\w+$/D', $username) || $slug === "") {
    not_found();
}

$source_url = "/$username/" . rawurlencode($slug) . "/";
$board = pinterest_resource("BoardResource", $source_url, "www/[username]/[slug].js", [
    "username" => $username,
    "slug" => $slug,
    "field_set_key" => "detailed",
])->data ?? null;
if ($board === null) {
    not_found();
}

$options = ["board_id" => $board->id, "page_size" => 50];
if ($bookmark !== null) {
    $options["bookmarks"] = [$bookmark];
}
$pins = pinterest_resource("BoardFeedResource", $source_url, "www/[username]/[slug].js", $options);

$name = htmlspecialchars($board->name);
$user = htmlspecialchars($username);

require "misc/header.php";
?>
<title><?php echo $name; ?> - Binternet</title>
</head>
<body>
    <form class="search-container" action="/search.php" method="get" autocomplete="off">
        <h1><a class="no-decoration accent" href="/">Binternet</a></h1>
        <input type="text" name="q" placeholder="Search Image">
    </form>
    <div class="info">
    <h2><?php echo $name; ?></h2>
    <p>board by: <a class="uploader" href="/user.php?user=<?php echo $user; ?>"><?php echo $user; ?></a></p>
    </div>
<?php
print_pins($pins->data ?? []);
print_next_page("board.php", $pins->bookmark ?? null, ["user" => $username, "board" => $slug]);

include "misc/footer.php";
?>
