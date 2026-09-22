<?php
$verify_token = "my_secret_token_123";

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    if ($mode === 'subscribe' && $token === $verify_token) {
        echo $challenge;
        exit;
    } else {
        http_response_code(403);
        echo "Forbidden";
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    file_put_contents('log.txt', date('Y-m-d H:i:s') . " - " . $input . "\n\n", FILE_APPEND);

    http_response_code(200);
    echo "EVENT_RECEIVED";
    exit;
}

http_response_code(404);
echo "Not Found";