<?php
$VERIFY_TOKEN = "12345";

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if ($_GET['hub_verify_token'] == $VERIFY_TOKEN) {
        echo $_GET['hub_challenge'];
        exit;
    } else {
        http_response_code(403);
        exit;
    }
}

// POST - messages from WhatsApp
$input = file_get_contents('php://input');
file_put_contents('log.txt', $input); // save for debug
http_response_code(200);
echo "OK";
?>
