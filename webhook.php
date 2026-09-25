
<?php

// ==========================================
// CONFIGURATION
// ==========================================

$verify_token = "my_secret_token_123";

// Get secrets from Railway environment variables
$whatsappAccessToken = getenv('EAArC1ZBrUHQ0BSrG7eYhzcG3YZAN9eZBAZBKTcoxwzr9sQUfl2FKBWmlVFYHZAZBj5QzYeAydqQpmJsvIwwuS5XLg8IhGMBweqy88wYw0ZBVHWLWYckZBcqcrg10YRqZBX0vcXxpD0hvaL25Sni8HHWPDWkUx5Gn1YQpiEIJZBhwORwkoTc73PqQmOQeWMWBkJ7cFCSubakyuGLIzbF4MMd2heGvz1UpwyYLV0d4217qA9EkQuWiEMb3dHo0ml9ZBeNswbjKgi5kPZCmLWf00rFOx9oobDO8');
$nvidiaApiKey = getenv('Nnvapi-0GCPIQbFiwX6a_-SExA4bOXGnm_zQ6RHtglanWTiEMYkqG2KdLiBJ37KFTigbVm_');

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

    // Show the actual incoming webhook in Railway logs.
    file_put_contents(
        'php://stdout',
        "========================================" . PHP_EOL .
        "WHATSAPP INCOMING:" . PHP_EOL .
        $input . PHP_EOL .
        "========================================" . PHP_EOL
    );

    $data = json_decode($input, true);

    // ==========================================
    // GET MESSAGE
    // ==========================================

    $message = $data['entry'][0]['changes'][0]['value']['messages'][0] ?? null;

    // This could be a status/update event rather than a message.
    if (!$message) {

        file_put_contents(
            'php://stdout',
            "NO MESSAGE FOUND IN WEBHOOK EVENT" . PHP_EOL
        );

        http_response_code(200);
        echo "EVENT_RECEIVED";
        exit;
    }

    // Only process text messages.
    if (($message['type'] ?? '') !== 'text') {

        file_put_contents(
            'php://stdout',
            "MESSAGE TYPE IS NOT TEXT: " .
            ($message['type'] ?? 'unknown') .
            PHP_EOL
        );

        http_response_code(200);
        echo "EVENT_RECEIVED";
        exit;
    }

    // Sender's WhatsApp number
    $from = $message['from'] ?? '';

    // Incoming text
    $incomingText = $message['text']['body'] ?? '';

    file_put_contents(
        'php://stdout',
        "FROM: " . $from . PHP_EOL .
        "MESSAGE: " . $incomingText . PHP_EOL
    );

    if ($from === '' || $incomingText === '') {

        file_put_contents(
            'php://stdout',
            "MISSING SENDER OR MESSAGE TEXT" . PHP_EOL
        );

        http_response_code(200);
        echo "EVENT_RECEIVED";
        exit;
    }


    // ==========================================
    // CHECK API KEYS
    // ==========================================

    if (!$whatsappAccessToken) {

        file_put_contents(
            'php://stdout',
            "ERROR: WHATSAPP_ACCESS_TOKEN IS NOT SET" . PHP_EOL
        );

        http_response_code(200);
        echo "EVENT_RECEIVED";
        exit;
    }

    if (!$nvidiaApiKey) {

        file_put_contents(
            'php://stdout',
            "ERROR: NVIDIA_API_KEY IS NOT SET" . PHP_EOL
        );

        http_response_code(200);
        echo "EVENT_RECEIVED";
        exit;
    }


    // ==========================================
    // SEND MESSAGE TO NVIDIA
    // ==========================================

    $nvidiaUrl =
        "https://integrate.api.nvidia.com/v1/chat/completions";

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

    $nvidiaCurlError = curl_error($ch);

    curl_close($ch);


    // ==========================================
    // LOG NVIDIA RESPONSE
    // ==========================================

    file_put_contents(
        'php://stdout',
        "NVIDIA HTTP: " . $nvidiaHttpCode . PHP_EOL .
        "NVIDIA RESPONSE: " . $nvidiaResponse . PHP_EOL .
        "NVIDIA CURL ERROR: " . $nvidiaCurlError . PHP_EOL
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

    $whatsappCurlError = curl_error($ch);

    curl_close($ch);


    // ==========================================
    // LOG WHATSAPP RESPONSE
    // ==========================================

    file_put_contents(
        'php://stdout',
        "WHATSAPP HTTP: " . $whatsappHttpCode . PHP_EOL .
        "WHATSAPP RESPONSE: " . $whatsappResponse . PHP_EOL .
        "WHATSAPP CURL ERROR: " . $whatsappCurlError . PHP_EOL .
        "========================================" . PHP_EOL
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


