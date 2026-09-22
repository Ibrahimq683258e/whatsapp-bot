
<?php

// ==========================================
// CONFIGURATION
// ==========================================

$verify_token = "my_secret_token_123";

// Meta WhatsApp access token
$whatsappAccessToken = "EAArC1ZBrUHQ0BSt9rZBzCHPfQ9zrb6tydpkUZC73RRRpDz88g6Bi1LRhcWLbYawocFfO64VqoDD2zTIMMdQWZBmrDVAJYdbmDuGuZA5ZCifZCSrZBkOoxSIPuHMZCiCCjpNZAdhGrHdjEBlISvbZClOt697ZCL7jnQ0eZCEK37R3QhLB9pFnJLSGnBc9Hbsawjy7CshjnNDZAZCSz1W5U9Qa0o4sNEizBQfLVcdFCMrvutPPADvz2N04EY3u8lj4udRIeu25ahSV4HCCNSYQW4ElnH6U8Q0WDar";

// NVIDIA API key
$nvidiaApiKey = "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";

// Your WhatsApp Phone Number ID
$phoneNumberId = "1350151684842334";

// NVIDIA model
// Use the model name shown in your NVIDIA API Catalog/API page.
$nvidiaModel = "meta/llama-3.1-8b-instruct";


// ==========================================
// META WEBHOOK VERIFICATION
// ==========================================

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
    echo "Forbidden";
    exit;
}


// ==========================================
// RECEIVE WHATSAPP WEBHOOK
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $input = file_get_contents('php://input');

    // Save incoming webhook for debugging.
    file_put_contents(
        __DIR__ . '/log.txt',
        date('Y-m-d H:i:s')
        . " - INCOMING: "
        . $input
        . PHP_EOL . PHP_EOL,
        FILE_APPEND
    );

    $data = json_decode($input, true);

    // ==========================================
    // GET MESSAGE
    // ==========================================

    $message = $data['entry'][0]['changes'][0]['value']['messages'][0] ?? null;

    if (!$message) {

        http_response_code(200);
        echo "EVENT_RECEIVED";
        exit;
    }

    // Sender's WhatsApp number
    $from = $message['from'] ?? '';

    // Incoming text
    $incomingText = $message['text']['body'] ?? '';

    if ($from === '' || $incomingText === '') {

        http_response_code(200);
        echo "EVENT_RECEIVED";
        exit;
    }


    // ==========================================
    // SEND MESSAGE TO NVIDIA
    // ==========================================

    $nvidiaUrl = "https://integrate.api.nvidia.com/v1/chat/completions";

    $nvidiaData = [
        "model" => $nvidiaModel,

        "messages" => [
            [
                "role" => "system",
                "content" =>
                    "You are a friendly WhatsApp business assistant. "
                    . "Keep your answers helpful, clear, and reasonably concise."
            ],
            [
                "role" => "user",
                "content" => $incomingText
            ]
        ],

        "temperature" => 0.7,
        "max_tokens" => 300
    ];


    $ch = curl_init($nvidiaUrl);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $nvidiaApiKey,
        "Content-Type: application/json"
    ]);

    curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        json_encode($nvidiaData)
    );

    $nvidiaResponse = curl_exec($ch);

    $nvidiaHttpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);


    // ==========================================
    // SAVE NVIDIA RESPONSE
    // ==========================================

    file_put_contents(
        __DIR__ . '/log.txt',
        date('Y-m-d H:i:s')
        . " - NVIDIA HTTP: "
        . $nvidiaHttpCode
        . " - "
        . $nvidiaResponse
        . PHP_EOL . PHP_EOL,
        FILE_APPEND
    );


    // ==========================================
    // GET AI RESPONSE
    // ==========================================

    $nvidiaDataResponse = json_decode(
        $nvidiaResponse,
        true
    );

    $aiReply =
        $nvidiaDataResponse['choices'][0]['message']['content']
        ?? 'Sorry, I could not generate a response right now.';


    // ==========================================
    // SEND AI RESPONSE TO WHATSAPP
    // ==========================================

    $whatsappUrl =
        "https://graph.facebook.com/v20.0/"
        . $phoneNumberId
        . "/messages";


    $whatsappData = [

        "messaging_product" => "whatsapp",

        "recipient_type" => "individual",

        "to" => $from,

        "type" => "text",

        "text" => [
            "preview_url" => false,
            "body" => $aiReply
        ]
    ];


    $ch = curl_init($whatsappUrl);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $whatsappAccessToken,
        "Content-Type: application/json"
    ]);

    curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        json_encode($whatsappData)
    );

    $whatsappResponse = curl_exec($ch);

    $whatsappHttpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);


    // ==========================================
    // SAVE WHATSAPP API RESPONSE
    // ==========================================

    file_put_contents(
        __DIR__ . '/log.txt',
        date('Y-m-d H:i:s')
        . " - WHATSAPP HTTP: "
        . $whatsappHttpCode
        . " - "
        . $whatsappResponse
        . PHP_EOL . PHP_EOL,
        FILE_APPEND
    );


    // ==========================================
    // TELL META WE RECEIVED THE EVENT
    // ==========================================

    http_response_code(200);

    echo "EVENT_RECEIVED";

    exit;
}


// ==========================================
// OTHER REQUESTS
// ==========================================

http_response_code(404);
echo "Not Found";


