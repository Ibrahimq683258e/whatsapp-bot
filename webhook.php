```php
<?php

// ==============================
// CONFIGURATION
// ==============================

$verify_token = "my_secret_token_123";

// Paste your NEW access token here.
// Do NOT send the token to me.
$accessToken = "EAArC1ZBrUHQ0BSvX0YUBwiHaQPpAyFi6HxnRWCWzn3ymCAvZCujt7EjJZAWdEeNK0VcVaE4nXcnJJsxncMxrmRWPaWcWankrMyTWVfkqZC8h398RbZAs2dZA8feZAUZCDItKbhaVogWrjwE6CBw1Us2wDgMJZBVvfmWeKoj6ir3oYZBIFgozHCnxp4EZACPMlHPs42j0aO9VDPfnTUU2iykMfBD8hG4T64JTVoJQZBOnmFhZAZAxNhPfJTffQv29NN2r7ZA6rBpXaYP36Eg2CgF5ETdHJQRe0y1";

$phoneNumberId = "1350151684842334";

// Use the API version you are currently using/configured for.
// Your existing sender used v20.0.
$apiVersion = "v20.0";


// ==============================
// WEBHOOK VERIFICATION
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
// RECEIVE WHATSAPP MESSAGE
// ==============================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $input = file_get_contents('php://input');

    // Keep the existing log.
    file_put_contents(
        __DIR__ . '/log.txt',
        date('Y-m-d H:i:s') . ' - ' . $input . PHP_EOL . PHP_EOL,
        FILE_APPEND
    );

    // Convert JSON into PHP array.
    $data = json_decode($input, true);

    // Check that this is a WhatsApp message.
    $message = $data['entry'][0]['changes'][0]['value']['messages'][0] ?? null;

    if ($message) {

        // Sender's WhatsApp number.
        $from = $message['from'] ?? '';

        // Only handle text messages for now.
        $incomingText = $message['text']['body'] ?? '';

        if ($from !== '' && $incomingText !== '') {

            // ==============================
            // BOT REPLY
            // ==============================

            $reply = "Hello! 👋\n\n";
            $reply .= "You said: " . $incomingText . "\n\n";
            $reply .= "Welcome to my Business Bot 🚀";


            // ==============================
            // SEND REPLY THROUGH WHATSAPP
            // ==============================

            $url = "https://graph.facebook.com/"
                 . $apiVersion
                 . "/"
                 . $phoneNumberId
                 . "/messages";

            $payload = [
                "messaging_product" => "whatsapp",
                "recipient_type" => "individual",
                "to" => $from,
                "type" => "text",
                "text" => [
                    "preview_url" => false,
                    "body" => $reply
                ]
            ];

            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);

            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer " . $accessToken,
                "Content-Type: application/json"
            ]);

            curl_setopt(
                $ch,
                CURLOPT_POSTFIELDS,
                json_encode($payload)
            );

            $response = curl_exec($ch);

            $httpCode = curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

            curl_close($ch);

            // Log the API response too.
            file_put_contents(
                __DIR__ . '/log.txt',
                date('Y-m-d H:i:s')
                . " - SEND HTTP "
                . $httpCode
                . " - "
                . $response
                . PHP_EOL . PHP_EOL,
                FILE_APPEND
            );
        }
    }

    // Tell Meta that the webhook was received.
    http_response_code(200);
    echo 'EVENT_RECEIVED';
    exit;
}


// ==============================
// EVERYTHING ELSE
// ==============================

http_response_code(404);
echo 'Not Found';
```

