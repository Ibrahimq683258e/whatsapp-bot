<?php

$verify_token = 'my_secret_token_123';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';

    if ($mode === 'subscribe' && hash_equals($verify_token, $token)) {
        http_response_code(200);
        header('Content-Type: text/plain');
        echo $challenge;
        exit;
    }

    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $input = file_get_contents('php://input');

    file_put_contents(
        __DIR__ . '/log.txt',
        date('Y-m-d H:i:s') . " - " . $input . PHP_EOL . PHP_EOL,
        FILE_APPEND
    );

    http_response_code(200);
    echo 'EVENT_RECEIVED';
    exit;
}

http_response_code(404);
echo 'Not Found';
