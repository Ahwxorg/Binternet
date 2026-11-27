<?php
require "misc/tools.php";

$username = $_GET["user"] ?? "";
$bookmark = $_GET["bookmark"] ?? null;
if (!preg_match('/^\w+$/D', $username)) {
    not_found();
}

$user = pinterest_resource("UserResource", "/$username/", "www/[username].js", [
    "username" => $username,
    "field_set_key" => "profile",
])->data ?? null;
if ($user === null) {
    not_found();
}

$options = ["username" => $username, "page_size" => 50];
if ($bookmark !== null) {
    $options["bookmarks"] = [$bookmark];
}
$pins = pinterest_resource("UserActivityPinsResource", "/$username/_created/", "www/[username]/_created.js", $options);

$boards = [];
if ($bookmark === null) {
    $boards = pinterest_resource("BoardsResource", "/$username/", "www/[username].js", [
        "username" => $username,
        "page_size" => 50,
    ])->data ?? [];
}

$name = htmlspecialchars(($user->full_name ?? "") ?: $username);

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
<?php
if (!empty($user->about)) {
    echo "<p>" . htmlspecialchars($user->about) . "</p>";
}

if ($boards) {
    echo "<p>Boards: ";
    $links = [];
    foreach ($boards as $board) {
        $slug = explode("/", trim($board->url, "/"))[1] ?? "";
        $href = htmlspecialchars("/board.php?" . http_build_query(["user" => $username, "board" => rawurldecode($slug)]));
        $links[] = "<a href=\"$href\">" . htmlspecialchars($board->name) . "</a>";
    }
    echo implode(", ", $links) . "</p>";
}
echo "</div>";

print_pins($pins->data ?? []);
print_next_page("user.php", $pins->bookmark ?? null, ["user" => $username]);

include "misc/footer.php";
?>
