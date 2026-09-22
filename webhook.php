<?php

$verify_token = "my_secret_token_123";

// Webhook verification
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $mode = $_GET['hub.mode'] ?? '';
    $token = $_GET['hub.verify_token'] ?? '';
    $challenge = $_GET['hub.challenge'] ?? '';

    if ($mode === 'subscribe' && $token === $verify_token) {
        http_response_code(200);
        echo $challenge;
        exit;
    }

    http_response_code(403);
    echo "Forbidden";
    exit;
}

// Receive webhook events
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $input = file_get_contents('php://input');

    file_put_contents(
        __DIR__ . '/log.txt',
        date('Y-m-d H:i:s') . " - " . $input . PHP_EOL . PHP_EOL,
        FILE_APPEND
    );

    http_response_code(200);
    echo "EVENT_RECEIVED";
    exit;
}

http_response_code(404);
echo "Not Found";
