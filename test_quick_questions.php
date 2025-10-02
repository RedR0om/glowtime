<?php
require_once 'inc/bootstrap.php';
require_once 'inc/data_loader.php';

echo "<h2>Testing Quick Questions Logic</h2>";

// Test the quick questions that should ALWAYS use rule-based responses
$quickQuestions = [
    "How do I book an appointment?",
    "How to book appointment?",
    "Book appointment",
    "Appointment booking",
    "How do I book?",
    "Booking process",
    "Schedule appointment",
    "What hair style would suit my face shape?",
    "What hair color would look good on me?",
    "What skincare routine should I follow?",
    "How do I maintain healthy nails?"
];

echo "<h3>Testing Quick Questions (Should use rule-based responses)</h3>";

foreach ($quickQuestions as $question) {
    echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<strong>Question:</strong> " . htmlspecialchars($question) . "<br><br>";
    
    // Enable debug mode
    $_GET['debug'] = true;
    
    // Build context
    $context = SalonDataLoader::buildAIContext(['Haircut', 'Color']);
    $formattedContext = SalonDataLoader::formatContextForAI($context);
    
    // Get AI response
    $response = generateAIResponse($question, $formattedContext);
    
    echo "<strong>Response:</strong> " . htmlspecialchars($response) . "<br>";
    
    // Check response type
    if (strpos($response, '[Quick Question - Rule-based]') !== false) {
        echo "<span style='color: green;'>✅ Using Quick Question Rule-based Response</span>";
    } elseif (strpos($response, '[Rule-based Response]') !== false) {
        echo "<span style='color: blue;'>📝 Using General Rule-based Response</span>";
    } elseif (strpos($response, '[OpenAI Response]') !== false) {
        echo "<span style='color: orange;'>⚠️ Using OpenAI (unexpected for quick questions)</span>";
    } else {
        echo "<span style='color: red;'>❌ Unknown response type</span>";
    }
    
    echo "</div>";
}

// Test regular questions that should use OpenAI
echo "<h3>Testing Regular Questions (Should use OpenAI)</h3>";

$regularQuestions = [
    "I have curly hair and want to try something new",
    "What's the best treatment for damaged hair?",
    "Can you recommend a good shampoo for color-treated hair?",
    "I'm looking for a low-maintenance hairstyle"
];

foreach ($regularQuestions as $question) {
    echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<strong>Question:</strong> " . htmlspecialchars($question) . "<br><br>";
    
    // Enable debug mode
    $_GET['debug'] = true;
    
    // Build context
    $context = SalonDataLoader::buildAIContext(['Haircut', 'Color']);
    $formattedContext = SalonDataLoader::formatContextForAI($context);
    
    // Get AI response
    $response = generateAIResponse($question, $formattedContext);
    
    echo "<strong>Response:</strong> " . htmlspecialchars($response) . "<br>";
    
    // Check response type
    if (strpos($response, '[Quick Question - Rule-based]') !== false) {
        echo "<span style='color: orange;'>⚠️ Using Quick Question (unexpected for regular questions)</span>";
    } elseif (strpos($response, '[Rule-based Response]') !== false) {
        echo "<span style='color: blue;'>📝 Using Rule-based Fallback</span>";
    } elseif (strpos($response, '[OpenAI Response]') !== false) {
        echo "<span style='color: green;'>✅ Using OpenAI Response</span>";
    } else {
        echo "<span style='color: red;'>❌ Unknown response type</span>";
    }
    
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='client_ai.php?debug=1'>Test AI Chatbot with Debug Mode</a> | <a href='client_ai.php'>Test AI Chatbot Normal Mode</a></p>";
?>
