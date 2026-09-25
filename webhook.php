<?php

// ==========================================
// CONFIGURATION
// ==========================================

$verify_token = "my_secret_token_123";

$whatsappAccessToken = getenv('WHATSAPP_ACCESS_TOKEN');
$nvidiaApiKey         = getenv('NVIDIA_API_KEY');
$phoneNumberId        = getenv('WHATSAPP_PHONE_NUMBER_ID') ?: "1350151684842334";
$nvidiaModel          = "meta/llama-3.1-8b-instruct";

$debugLogFile = __DIR__ . '/debug.log';


// ==========================================
// HELPERS
// ==========================================

function debugLog(string $message): void {
    global $debugLogFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($debugLogFile, $line, FILE_APPEND | LOCK_EX);
}

function callApi(string $url, array $headers, array $payload, int $timeoutSeconds = 20): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => $timeoutSeconds,
    ]);

    $body      = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'body'      => $body,
        'http_code' => $httpCode,
        'error'     => $curlError,
    ];
}

function respondAndExit(string $body = "EVENT_RECEIVED", int $code = 200): void {
    http_response_code($code);
    echo $body;
    exit;
}


// ==========================================
// DEBUG VIEWER
// GET /webhook.php?debug=1   -> view last 200 log lines
// GET /webhook.php?clear=1   -> wipe the log
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['debug'])) {
    header('Content-Type: text/plain');
    if (file_exists($debugLogFile)) {
        $lines = file($debugLogFile);
        echo implode('', array_slice($lines, -200));
    } else {
        echo "No debug log yet. Send a test WhatsApp message first.";
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['clear'])) {
    file_put_contents($debugLogFile, '');
    echo "Cleared.";
    exit;
}


// ==========================================
// META WEBHOOK VERIFICATION (GET)
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $mode      = $_GET['hub_mode']          ?? $_GET['hub.mode']          ?? '';
    $token     = $_GET['hub_verify_token']  ?? $_GET['hub.verify_token']  ?? '';
    $challenge = $_GET['hub_challenge']     ?? $_GET['hub.challenge']     ?? '';

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
// RECEIVE WHATSAPP WEBHOOK (POST)
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $input = file_get_contents('php://input');

    debugLog("========================================");
    debugLog("INCOMING: " . $input);

    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        debugLog("JSON DECODE ERROR: " . json_last_error_msg());
        respondAndExit();
    }

    $message = $data['entry'][0]['changes'][0]['value']['messages'][0] ?? null;

    if (!$message) {
        debugLog("NO MESSAGE IN PAYLOAD (likely a status update event)");
        respondAndExit();
    }

    if (($message['type'] ?? '') !== 'text') {
        debugLog("SKIPPED — non-text message type: " . ($message['type'] ?? 'unknown'));
        respondAndExit();
    }

    $from         = $message['from'] ?? '';
    $incomingText = $message['text']['body'] ?? '';

    debugLog("FROM: $from | MESSAGE: $incomingText");

    if ($from === '' || $incomingText === '') {
        debugLog("MISSING sender or message text — aborting");
        respondAndExit();
    }

    if (!$whatsappAccessToken) {
        debugLog("ERROR: WHATSAPP_ACCESS_TOKEN env var is not set");
        respondAndExit();
    }

    if (!$nvidiaApiKey) {
        debugLog("ERROR: NVIDIA_API_KEY env var is not set");
        respondAndExit();
    }

    debugLog("Keys present. WHATSAPP token length=" . strlen($whatsappAccessToken) . ", NVIDIA key length=" . strlen($nvidiaApiKey));


    // ---------- Call NVIDIA ----------

    $nvidiaResult = callApi(
        "https://integrate.api.nvidia.com/v1/chat/completions",
        [
            "Authorization: Bearer " . $nvidiaApiKey,
            "Content-Type: application/json",
        ],
        [
            "model" => $nvidiaModel,
            "messages" => [
                ["role" => "system", "content" => "You are a friendly WhatsApp business assistant. Keep your answers helpful, clear, and reasonably concise."],
                ["role" => "user",   "content" => $incomingText],
            ],
            "temperature" => 0.7,
            "max_tokens"  => 300,
        ]
    );

    debugLog("NVIDIA HTTP: {$nvidiaResult['http_code']} | ERROR: {$nvidiaResult['error']}");
    debugLog("NVIDIA BODY: {$nvidiaResult['body']}");

    $nvidiaData = json_decode($nvidiaResult['body'], true);
    $aiReply = $nvidiaData['choices'][0]['message']['content']
        ?? 'Sorry, I could not generate a response right now.';


    // ---------- Reply on WhatsApp ----------

    $whatsappResult = callApi(
        "https://graph.facebook.com/v20.0/{$phoneNumberId}/messages",
        [
            "Authorization: Bearer " . $whatsappAccessToken,
            "Content-Type: application/json",
        ],
        [
            "messaging_product" => "whatsapp",
            "recipient_type"    => "individual",
            "to"                => $from,
            "type"              => "text",
            "text"              => ["preview_url" => false, "body" => $aiReply],
        ]
    );

    debugLog("WHATSAPP HTTP: {$whatsappResult['http_code']} | ERROR: {$whatsappResult['error']}");
    debugLog("WHATSAPP BODY: {$whatsappResult['body']}");
    debugLog("========================================");

    respondAndExit();
}


// ==========================================
// EVERYTHING ELSE
// ==========================================

http_response_code(404);
echo "Not Found";
