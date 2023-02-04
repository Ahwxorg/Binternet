<?php

// Pinterest API Endpoint
$api_url = "https://api.pinterest.com/v1/boards/{board_id}/pins/";

// Replace {board_id} with the actual board ID
$board_id = "replace_with_actual_board_id";

// Replace {access_token} with a valid Pinterest access token
$access_token = "replace_with_actual_access_token";

// Create the API request URL
$request_url = $api_url . "?access_token=" . $access_token;

// Send a GET request to the Pinterest API
$response = file_get_contents($request_url);

// Decode the JSON response into a PHP array
$pins = json_decode($response, true);

// Loop through the pins and display each one
foreach ($pins["data"] as $pin) {
  echo '<div>';
  echo '<img src="' . $pin["image"]["original"]["url"] . '" alt="' . $pin["description"] . '">';
  echo '<p>' . $pin["description"] . '</p>';
  echo '</div>';
}

?>
