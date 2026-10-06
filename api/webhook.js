const VERIFY_TOKEN = "my_secret_token_123";
const PHONE_NUMBER_ID = "1350151684842334";
const ACCESS_TOKEN = "EAArC1ZBrUHQ0BSnkevizzwFaJvwwNkdjBDAKmO0VwnToMXxNwl1ZCkm3ljF36wA3dwcFcZBJ9kX3DCVdXvGZB6OTRlPqtuwSLVK3gCDaWcTZBSJjDWlzF5zk53OcRvJpUiXONOP0jmjQJEtfS2MlQEqfgSA4M3wRZClytan1N2gx6uPFttmXiMNw3euAaQNoutZBqVLSAPTRqUqgl3ZAgoBSyNLUQyYz6UBgAd5saPKsWcKubC9Y2zx8ekmZCx9sLlMhTSpaNWJKNnvEzxut7xLyeZBD3g";

export default async function handler(req, res) {
  // ========== WEBHOOK VERIFICATION (GET) ==========
  if (req.method === "GET") {
    const mode = req.query["hub.mode"];
    const token = req.query["hub.verify_token"];
    const challenge = req.query["hub.challenge"];

    if (mode === "subscribe" && token === VERIFY_TOKEN) {
      console.log("Webhook verified successfully!");
      return res.status(200).send(challenge);
    } else {
      return res.status(403).send("Forbidden");
    }
  }

  // ========== RECEIVE MESSAGES (POST) ==========
  if (req.method === "POST") {
    try {
      const body = req.body;
      console.log("Full incoming body:", JSON.stringify(body, null, 2));

      // Always reply 200 quickly
      res.status(200).send("EVENT_RECEIVED");

      const message = body?.entry?.[0]?.changes?.[0]?.value?.messages?.[0];

      if (message && message.type === "text") {
        const from = message.from;
        const text = message.text.body;

        console.log(`Received message from ${from}: ${text}`);
        await sendReply(from, `You said: ${text}`);
      } else {
        console.log("No text message found");
      }
    } catch (error) {
      console.error("Error processing message:", error.message);
    }
  }
}

async function sendReply(to, message) {
  try {
    const url = `https://graph.facebook.com/v19.0/${PHONE_NUMBER_ID}/messages`;

    const response = await fetch(url, {
      method: "POST",
      headers: {
        Authorization: `Bearer ${ACCESS_TOKEN}`,
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        messaging_product: "whatsapp",
        to: to,
        type: "text",
        text: { body: message },
      }),
    });

    const data = await response.json();
    console.log("Reply API response:", data);
  } catch (error) {
    console.error("Error sending reply:", error.message);
  }
}