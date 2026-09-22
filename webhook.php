<?php
$verify_token = "my_secret_token_123";
$accessToken = "EAArC1ZBrUHQ0BSt9rZBzCHPfQ9zrb6tydpkUZC73RRRpDz88g6Bi1LRhcWLbYawocFfO64VqoDD2zTIMMdQWZBmrDVAJYdbmDuGuZA5ZCifZCSrZBkOoxSIPuHMZCiCCjpNZAdhGrHdjEBlISvbZClOt697ZCL7jnQ0eZCEK37R3QhLB9pFnJLSGnBc9Hbsawjy7CshjnNDZAZCSz1W5U9Qa0o4sNEizBQfLVcdFCMrvutPPADvz2N04EY3u8lj4udRIeu25ahSV4HCCNSYQW4ElnH6U8Q0WDar";
$phoneNumberId = "1350151684842334";

// VERIFICATION
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode']?? $_GET['hub.mode']?? '';
    $token = $_GET['hub_verify_token']?? $_GET['hub.verify_token']?? '';
    $challenge = $_GET['hub_challenge']?? $_GET['hub.challenge']?? '';
    if ($mode === 'subscribe' && $token === $verify_token) {
        echo $challenge;
        exit;
    }
    http_response_code(403);
    echo "Forbidden";
    exit;
}

// RECEIVE + REPLY
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    file_put_contents(__DIR__. '/log.txt', date('Y-m-d H:i:s')." IN: ".$input.PHP_EOL, FILE_APPEND);
    $data = json_decode($input, true);
    $message = $data['entry'][0]['changes'][0]['value']['messages'][0]?? null;

    if ($message) {
        $from = $message['from'];
        $incomingText = $message['text']['body']?? '';
        $reply = "Hello! 👋\n\nYou said: $incomingText\n\nWelcome to my Business Bot 🚀";

        $url = "https://graph.facebook.com/v20.0/$phoneNumberId/messages";
        $sendData = [
            "messaging_product" => "whatsapp",
            "to" => $from,
            "type" => "text",
            "text" => ["body" => $reply]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer $accessToken","Content-Type: application/json"],
            CURLOPT_POSTFIELDS => json_encode($sendData)
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        file_put_contents(__DIR__. '/log.txt', date('Y-m-d H:i:s')." OUT $httpCode $response".PHP_EOL.PHP_EOL, FILE_APPEND);
    }
    http_response_code(200);
    echo "EVENT_RECEIVED";
    exit;
}

