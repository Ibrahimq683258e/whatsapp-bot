export default function handler(req, res) {
  const VERIFY_TOKEN = "my_secret_token_123"; // ← You can change this later

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
    const body = req.body;

    console.log("Incoming message:", JSON.stringify(body, null, 2));

    // Always reply 200 quickly so Meta is happy
    res.status(200).send("EVENT_RECEIVED");

    // Later we will add the logic to reply to messages here
  }
}