
<?php

$verify_token = "my_secret_token_123";


// ==============================
// GET - META WEBHOOK VERIFICATION
// ==============================

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';

    if ($mode === 'subscribe' && $token === $verify_token) {
        http_response_code(200);
        echo $challenge;
        exit;
    }

    http_response_code(403);
    echo 'Forbidden';
    exit;
}


// ==============================
// POST - WHATSAPP WEBHOOK
// ==============================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Read the data sent by Meta.
    $input = file_get_contents('php://input');

    // Save it for debugging.
    file_put_contents(
        __DIR__ . '/log.txt',
        date('Y-m-d H:i:s') . " - " . $input . PHP_EOL . PHP_EOL,
        FILE_APPEND
    );

    // Tell Meta that we received it.
    http_response_code(200);

    echo "WEBHOOK POST RECEIVED";

    exit;
}


// ==============================
// OTHER REQUESTS
// ==============================

http_response_code(404);
echo "Not Found";




