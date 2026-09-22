
<?php

// ==============================
// CONFIGURATION
// ==============================

$verify_token = "my_secret_token_123";

// USE YOUR NEW ACCESS TOKEN HERE.
// Do not send it to me.
$accessToken = "EAArC1ZBrUHQ0BSt9rZBzCHPfQ9zrb6tydpkUZC73RRRpDz88g6Bi1LRhcWLbYawocFfO64VqoDD2zTIMMdQWZBmrDVAJYdbmDuGuZA5ZCifZCSrZBkOoxSIPuHMZCiCCjpNZAdhGrHdjEBlISvbZClOt697ZCL7jnQ0eZCEK37R3QhLB9pFnJLSGnBc9Hbsawjy7CshjnNDZAZCSz1W5U9Qa0o4sNEizBQfLVcdFCMrvutPPADvz2N04EY3u8lj4udRIeu25ahSV4HCCNSYQW4ElnH6U8Q0WDar";

$phoneNumberId = "1350151684842334";


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

    // Save incoming webhook.
    file_put_contents(
        __DIR__ . '/log.txt',
        date('Y-m-d H:i:s') . ' - ' . $input . PHP_EOL . PHP_EOL,
        FILE_APPEND
    );

    $data = json_decode($input, true);

    // Get the incoming WhatsApp message.
    $message = $data['entry'][0]['changes'][0]['value']['messages'][0] ?? null;

    if ($message) {

        // Person who sent the message.
        $from = $message['from'] ?? '';

        // Text they sent.
        $incomingText = $message['text']['body'] ?? '';

        if ($from !== '' && $incomingText !== '') {

            // ==============================
            // BOT RESPONSE
            // ==============================

            $reply = "Hello! 👋\n\n";
            $reply .= "You said: " . $incomingText . "\n\n";
            $reply .= "Welcome to my Business Bot 🚀";


            // ==============================
            // SEND WHATSAPP MESSAGE
            // ==============================

            $url = "https://graph.facebook.com/v20.0/"
                 . $phoneNumberId
                 . "/messages";

            $dataToSend = [
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
                json_encode($dataToSend)
            );

            $response = curl_exec($ch);

            $httpCode = curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

            curl_close($ch);

            // Save API response for debugging.
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

    http_response_code(200);
    echo 'EVENT_RECEIVED';
    exit;
}


// ==============================
// EVERYTHING ELSE
// ==============================

http_response_code(404);
echo 'Not Found';



