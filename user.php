<?php

require "misc/tools.php";

$username = $_GET["user"] ?? "";
$bookmark = $_GET["bookmark"] ?? null;
if (!is_string($username) || !preg_match('/^\w+$/D', $username)) {
    not_found();
}
if (!is_string($bookmark) && $bookmark !== null) {
    not_found();
}

$user_response = pinterest_resource("UserResource", "/$username/", "www/[username].js", [
    "username" => $username,
    "field_set_key" => "profile",
]);
$user = $user_response->data ?? null;
if ($user === null) {
    not_found();
}

$options = ["username" => $username, "page_size" => 50];
if ($bookmark !== null) {
    $options["bookmarks"] = [$bookmark];
}
$pins_response = pinterest_resource("UserActivityPinsResource", "/$username/_created/", "www/[username]/_created.js", $options);
$pins = $pins_response->data ?? [];

$boards = [];
if ($bookmark === null) {
    $boards_response = pinterest_resource("BoardsResource", "/$username/", "www/[username].js", [
        "username" => $username,
        "page_size" => 50,
    ]);
    $boards = $boards_response->data ?? [];
}

$name = htmlspecialchars(($user->full_name ?? "") ?: $username);
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
<?php
if (!empty($user->about)) {
    echo "<p>" . htmlspecialchars($user->about) . "</p>";
}

if ($boards) {
    echo "<p>Boards: ";
    $links = [];
    foreach ($boards as $board) {
        $parts = explode("/", trim((string) ($board->url ?? ""), "/"));
        $slug = $parts[1] ?? "";
        if ($slug === "") {
            continue;
        }
        $href = "/board.php?" . http_build_query(["user" => $username, "board" => rawurldecode($slug)]);
        $links[] = "<a href=\"" . htmlspecialchars($href) . "\">" . htmlspecialchars($board->name ?? "") . "</a>";
    }
    echo implode(", ", $links) . "</p>";
}
?>
    </div>
<?php
print_pins($pins);
print_next_page("user.php", $pins_response->bookmark ?? null, ["user" => $username]);
include "misc/footer.php";
?>
</body>
</html>
