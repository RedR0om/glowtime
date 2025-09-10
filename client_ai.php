<?php
require_once 'inc/bootstrap.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: login.php');
    exit;
}

// Initialize chat session
if (!isset($_SESSION['chat_history'])) $_SESSION['chat_history'] = [];
if (!isset($_SESSION['conversation_state'])) $_SESSION['conversation_state'] = 'start';

$suggested_messages = ["Services", "Prices", "Hairstyle tips", "Haircare advice"];
$response = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = strtolower(trim($_POST['message']));
    $_SESSION['chat_history'][] = ["role" => "user", "text" => htmlspecialchars($message)];

    // Conversation flow
    switch($_SESSION['conversation_state']) {
        case 'start':
            if (strpos($message, 'service') !== false || strpos($message, 'services') !== false) {
                $response = "We offer Haircuts, Hair Coloring, and Hair Spa treatments. Which one would you like to know more about?";
                $_SESSION['conversation_state'] = 'service_selected';
            } elseif (strpos($message, 'price') !== false || strpos($message, 'cost') !== false) {
                $response = "Our prices are:\n- Haircut: ₱300-₱500\n- Hair Coloring: ₱800-₱2000\n- Hair Spa: ₱500-₱1000\nWhat service price are you curious about?";
                $_SESSION['conversation_state'] = 'price_inquiry';
            } elseif (strpos($message, 'tip') !== false || strpos($message, 'hairstyle') !== false || strpos($message, 'advice') !== false) {
                $response = "Sure! I can suggest hairstyles or haircare tips. What kind of advice are you looking for?";
                $_SESSION['conversation_state'] = 'advice';
            } else {
                $response = "👋 Hi! I can help you with Services, Prices, Hairstyle tips, or Haircare advice. What would you like to know?";
            }
            break;

        case 'service_selected':
            if (strpos($message, 'haircut') !== false) {
                $response = "Haircuts help shape your look! Options: Layered Cut, Bob, Undercut. Want hairstyle tips for your haircut?";
                $_SESSION['conversation_state'] = 'advice';
            } elseif (strpos($message, 'color') !== false || strpos($message, 'coloring') !== false) {
                $response = "Hair coloring can be fun! Trendy colors: Balayage, Pastel tones. Want haircare tips for colored hair?";
                $_SESSION['conversation_state'] = 'advice';
            } elseif (strpos($message, 'spa') !== false) {
                $response = "Hair Spa rejuvenates your scalp and hair. Regular treatments keep hair healthy. Want more haircare tips?";
                $_SESSION['conversation_state'] = 'advice';
            } else {
                $response = "Hmm, I didn’t get that. You can ask about Haircut, Coloring, or Spa.";
            }
            break;

        case 'price_inquiry':
            if (strpos($message, 'haircut') !== false) {
                $response = "Haircuts cost around ₱300-₱500 depending on style and length.";
            } elseif (strpos($message, 'color') !== false || strpos($message, 'coloring') !== false) {
                $response = "Hair Coloring ranges from ₱800-₱2000 depending on technique and hair length.";
            } elseif (strpos($message, 'spa') !== false) {
                $response = "Hair Spa treatments cost ₱500-₱1000 depending on package.";
            } else {
                $response = "You can ask about the prices of Haircut, Coloring, or Spa.";
            }
            $_SESSION['conversation_state'] = 'start';
            break;

        case 'advice':
            $tips = [
                "💡 Tip: A layered bob adds volume to your hair.",
                "💡 Tip: Use sulfate-free shampoo to protect hair color.",
                "💡 Tip: Regular hair spa keeps scalp healthy and nourished.",
                "💡 Tip: Try deep conditioning once a week for shiny hair."
            ];
            $response = $tips[array_rand($tips)] . " Would you like another tip or advice on haircare?";
            $_SESSION['conversation_state'] = 'start';
            break;

        default:
            $response = "🤔 Sorry, I didn’t understand that. Try asking about Services, Prices, or Hairstyle tips.";
            $_SESSION['conversation_state'] = 'start';
            break;
    }

    $_SESSION['chat_history'][] = ["role" => "bot", "text" => nl2br($response)];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>AI Salon Assistant</title>
<style>
body { font-family: "Segoe UI", sans-serif; background: #faf5ff; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
.chat-container { width: 100%; max-width: 600px; background: white; padding: 20px; border-radius: 20px; box-shadow: 0 8px 25px rgba(0,0,0,0.1); display: flex; flex-direction: column; height: 80vh; }
h1 { color: #ec4899; text-align: center; margin: 0 0 15px; font-size: 1.6rem; font-weight: 700; }
.chat-box { flex: 1; overflow-y: auto; margin-bottom: 15px; display: flex; flex-direction: column; gap: 8px; scroll-behavior: smooth; }
.bubble { max-width: 75%; padding: 12px 16px; border-radius: 16px; white-space: pre-line; word-wrap: break-word; line-height: 1.4; font-size: 0.95rem; }
.user { background: #ec4899; color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
.bot { background: #fce7f3; color: #6b21a8; align-self: flex-start; border-bottom-left-radius: 4px; }
form { display: flex; gap: 10px; }
input { flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 10px; font-size: 1rem; }
input:focus { border-color: #ec4899; outline: none; }
button { padding: 12px 20px; background: #a21caf; color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 1rem; }
button:hover { background: #7e22ce; }
.suggestions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
.suggestion { background: #f3e8ff; color: #6b21a8; padding: 8px 12px; border-radius: 8px; cursor: pointer; font-size: 0.9rem; }
.suggestion:hover { background: #e9d5ff; }
</style>
</head>
<body>
<div class="chat-container">
<h1>🤖 AI Salon Assistant</h1>
<div class="chat-box" id="chatBox">
    <?php if (!empty($_SESSION['chat_history'])): ?>
        <?php foreach ($_SESSION['chat_history'] as $chat): ?>
            <div class="bubble <?= $chat['role'] ?>"><?= $chat['text'] ?></div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="bubble bot">👋 Hi! I can help you with Services, Prices, Hairstyle tips, or Haircare advice. Try clicking one below.</div>
    <?php endif; ?>
</div>
<form method="post" id="chatForm">
    <input type="text" name="message" placeholder="Type your message..." required autocomplete="off">
    <button type="submit">Send</button>
</form>
<div class="suggestions" id="suggestions">
    <?php foreach($suggested_messages as $msg): ?>
        <div class="suggestion" onclick="sendSuggestion('<?= $msg ?>')"><?= $msg ?></div>
    <?php endforeach; ?>
</div>
</div>

<script>
const chatBox = document.getElementById("chatBox");
chatBox.scrollTop = chatBox.scrollHeight;

function sendSuggestion(msg){
    const input = document.querySelector('input[name="message"]');
    input.value = msg;
    document.getElementById('chatForm').submit();
}
</script>
</body>
</html>
