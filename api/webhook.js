export default async function handler(req, res) {
  const VERIFY_TOKEN = "my_secret_token_123";
  const PHONE_NUMBER_ID = "1350151684842334";
  const ACCESS_TOKEN = "EAArC1ZBrUHQ0BSsQ70tveSx4svVaRLbdDJh69z3M8knnJzt3YktdADZA4fkDRZCqjHeTHOgqGdAWmZC09Y1HTHs6WZCel1YJYsI6R8itfTZC3LMgKJbbG0xldnvFZAyto5HWDwCi4JeRliZBJhwyi8wC4rQZCaAZCIT1INNqAZAZAnRVZBKZCT4tNfyGy9pR7R1LX0vl4CNa1rwiK50yPqS6hiWNKPv66cEA5bI6E9qlFfvZBRSReJIxRyRcwVin7DVU3Uo8TzZB84dsGHzc9LCvHH27nQqzuUgM";

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

      // Always reply 200 quickly to Meta
      res.status(200).send("EVENT_RECEIVED");

      // Safely extract the message
      const message = body?.entry?.[0]?.changes?.[0]?.value?.messages?.[0];

      if (message && message.type === "text") {
        const from = message.from;
        const text = message.text.body;

        console.log(`Received message from ${from}: ${text}`);

        // Send reply
        await sendReply(from, `You said: ${text}`);
      } else {
        console.log("No text message found in this webhook");
      }
    } catch (error) {
      console.error("Error processing message:", error.message);
      console.error(error.stack);
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