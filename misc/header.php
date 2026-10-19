<!DOCTYPE html >
<html lang="en">
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0"/>
    <meta charset="UTF-8"/>
    <meta name="description" content="A privacy respecting Pinterest image, GIF and video search"/>
    <meta name="referrer" content="no-referrer"/>
<?php $css_version = @filemtime(__DIR__ . "/style-dark.css") ?: 1; ?>
    <link rel="stylesheet" type="text/css" href="misc/style-dark.css?v=<?php echo $css_version; ?>">
<?php if (!empty($needs_player)): ?>
<?php $player_version = @filemtime(__DIR__ . "/../static/js/player.js") ?: 1; ?>
    <script src="/static/js/player.js?v=<?php echo $player_version; ?>" defer></script>
<?php endif; ?>
<?php if (!empty($needs_filter)): ?>
<?php $filter_version = @filemtime(__DIR__ . "/../static/js/filter.js") ?: 1; ?>
    <script src="/static/js/filter.js?v=<?php echo $filter_version; ?>" defer></script>
<?php endif; ?>
