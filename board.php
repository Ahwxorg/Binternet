<?php

$api_url = "https://api.pinterest.com/v1/boards/{board_id}/pins/";

$board_id = "replace_with_actual_board_id";

$access_token = "replace_with_actual_access_token";

$request_url = $api_url . "?access_token=" . $access_token;

$response = file_get_contents($request_url);

$pins = json_decode($response, true);

foreach ($pins["data"] as $pin) {
  echo '<div>';
  echo '<img src="' . $pin["image"]["original"]["url"] . '" alt="' . $pin["description"] . '">';
  echo '<p>' . $pin["description"] . '</p>';
  echo '</div>';
}

?>
