<?php

$verify_token = "my_secret_token_123";

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // Log exactly what Meta/browser sends
    error_log("WEBHOOK GET RECEIVED");
    error_log("QUERY STRING: " . ($_SERVER['QUERY_STRING'] ?? ''));
    error_log("GET DATA: " . print_r($_GET, true));

    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    error_log("MODE: " . $mode);
    error_log("TOKEN MATCH: " . ($token === $verify_token ? 'YES' : 'NO'));
    error_log("CHALLENGE: " . $challenge);

    if ($mode === 'subscribe' && $token === $verify_token) {
        http_response_code(200);
        header('Content-Type: text/plain');
        echo $challenge;
        exit;
    }

    http_response_code(403);
    echo "Forbidden";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $input = file_get_contents('php://input');

    error_log("WEBHOOK POST RECEIVED");
    error_log($input);

    http_response_code(200);
    echo "EVENT_RECEIVED";
    exit;
}

http_response_code(404);
echo "Not Found";
