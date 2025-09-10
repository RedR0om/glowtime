<?php
require __DIR__ . "/bootstrap.php";  // ✅ load DB + helper functions

header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);
$user_id = $_SESSION['user_id'] ?? 1; // ✅ demo: assume logged-in user

$message = strtolower(trim($input['message'] ?? ''));

$response = "Sorry, I didn’t understand that. Try asking about services or booking.";

/* ---- Handle booking flow ---- */
if (strpos($message, "haircut") !== false) {
    // ✅ recommend time slots for haircut
    $service_id = 1; // assume Haircut has ID=1
    $slots = suggest_available_slots($service_id, null, 3, 5);

    if ($slots) {
        $response = "✅ Haircut selected.\nHere are some available times:\n\n";
        foreach ($slots as $s) {
            $time = date("H:i", strtotime($s['start']));
            $response .= "⏰ $time\n";
        }
        $response .= "\nPlease reply with your preferred time (e.g., '10:30').";
    } else {
        $response = "❌ Sorry, no available slots in the next few days.";
    }
}

/* ---- Handle style recommendations ---- */
elseif (strpos($message, "suggest") !== false || strpos($message, "style") !== false) {
    $rec = style_recommendation_for_user($user_id, true); // ✅ use AI if key is set

    if ($rec['source'] === 'openai') {
        $response = "✨ AI Style Suggestion:\n" . $rec['text'];
    } else {
        $response = "✨ Based on your past visits, here are some tips:\n";
        foreach ($rec['items'] as $r) {
            $response .= "💡 $r\n";
        }
    }
}

/* ---- Handle time selection ---- */
elseif (preg_match('/^([0-9]{1,2}:[0-9]{2})$/', $message, $m)) {
    $chosen_time = $m[1];
    $response = "✅ You selected **$chosen_time**. Your booking is being processed...";

    // TODO: Insert into appointments table here
}

echo json_encode(["reply" => $response]);
