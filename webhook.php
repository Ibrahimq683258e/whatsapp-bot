<?php

// ==========================================
// CONFIGURATION
// ==========================================

$verify_token = "my_secret_token_123";

// Get secrets from Railway environment variables
$whatsappAccessToken = getenv('WHATSAPP_ACCESS_TOKEN');
$nvidiaApiKey = getenv('NVIDIA_API_KEY');

// Your WhatsApp Phone Number ID
$phoneNumberId = "1350151684842334";

// NVIDIA model
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

    error_log("========================================");
    error_log("WHATSAPP INCOMING:");
    error_log($input);
    error_log("========================================");

    $data = json_decode($input, true);

    // ==========================================
    // GET MESSAGE
    // ==========================================

    $message = $data['entry'][0]['changes'][0]['value']['messages'][0] ?? null;

    // This could be a status/update event rather than a message.
    if (!$message) {
        error_log("NO MESSAGE FOUND IN WEBHOOK EVENT");
        http_response_code(200);
        echo "EVENT_RECEIVED";
        exit;
    }

    // Only process text messages.
    if (($message['type'] ?? '') !== 'text') {
        error_log("MESSAGE TYPE IS NOT TEXT: " . ($message['type'] ?? 'unknown'));
        http_response_code(200);
        echo "EVENT_RECEIVED";
        exit;
    }

    // Sender's WhatsApp number
    $from = $message['from'] ?? '';

    // Incoming text
    $incomingText = $message['text']['body'] ?? '';

    error_log("FROM: " . $from);
    error_log("MESSAGE: " . $incomingText);

    if ($from === '' || $incomingText === '') {
        error_log("MISSING SENDER OR MESSAGE TEXT");
        http_response_code(200);
        echo "EVENT_RECEIVED";
        exit;
    }


    // ==========================================
    // CHECK API KEYS
    // ==========================================

    if (!$whatsappAccessToken) {
        error_log("ERROR: WHATSAPP_ACCESS_TOKEN IS NOT SET");
        http_response_code(200);
        echo "EVENT_RECEIVED";
        exit;
    }

    if (!$nvidiaApiKey) {
        error_log("ERROR: NVIDIA_API_KEY IS NOT SET");
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
                "content" => "You are a friendly WhatsApp business assistant. Keep your answers helpful, clear, and reasonably concise."
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
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($nvidiaData));

    $nvidiaResponse = curl_exec($ch);
    $nvidiaHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $nvidiaCurlError = curl_error($ch);

    curl_close($ch);

    error_log("NVIDIA HTTP: " . $nvidiaHttpCode);
    error_log("NVIDIA RESPONSE: " . $nvidiaResponse);
    error_log("NVIDIA CURL ERROR: " . $nvidiaCurlError);


    // ==========================================
    // GET AI RESPONSE
    // ==========================================

    $nvidiaDataResponse = json_decode($nvidiaResponse, true);

    $aiReply = $nvidiaDataResponse['choices'][0]['message']['content']
        ?? 'Sorry, I could not generate a response right now.';


    // ==========================================
    // SEND AI RESPONSE TO WHATSAPP
    // ==========================================

    $whatsappUrl = "https://graph.facebook.com/v20.0/" . $phoneNumberId . "/messages";

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
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($whatsappData));

    $whatsappResponse = curl_exec($ch);
    $whatsappHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $whatsappCurlError = curl_error($ch);

    curl_close($ch);

    error_log("WHATSAPP HTTP: " . $whatsappHttpCode);
    error_log("WHATSAPP RESPONSE: " . $whatsappResponse);
    error_log("WHATSAPP CURL ERROR: " . $whatsappCurlError);
    error_log("========================================");


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


