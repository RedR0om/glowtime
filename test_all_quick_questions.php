<?php
require_once 'inc/bootstrap.php';
require_once 'inc/data_loader.php';

echo "<h2>Testing ALL Quick Questions</h2>";

// Test all the quick question buttons from the interface
$quickQuestionButtons = [
    "What hair style would suit my face shape?",
    "What hair color would look good on me?", 
    "What skincare routine should I follow?",
    "How do I maintain healthy nails?",
    "How do I book an appointment?"
];

echo "<h3>Testing Quick Question Buttons (Should ALL use rule-based responses)</h3>";

foreach ($quickQuestionButtons as $question) {
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
        echo "<span style='color: red;'>❌ Using OpenAI (UNEXPECTED - should be rule-based)</span>";
    } else {
        echo "<span style='color: red;'>❌ Unknown response type</span>";
    }
    
    echo "</div>";
}

// Test the exact button text from the interface
echo "<h3>Testing Exact Button Text</h3>";

$exactButtonTexts = [
    "What hair style would suit my face shape?",
    "What hair color would look good on me?",
    "What skincare routine should I follow?", 
    "How do I maintain healthy nails?",
    "How do I book an appointment?"
];

foreach ($exactButtonTexts as $buttonText) {
    echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<strong>Button Text:</strong> " . htmlspecialchars($buttonText) . "<br><br>";
    
    // Enable debug mode
    $_GET['debug'] = true;
    
    // Build context
    $context = SalonDataLoader::buildAIContext(['Haircut', 'Color']);
    $formattedContext = SalonDataLoader::formatContextForAI($context);
    
    // Get AI response
    $response = generateAIResponse($buttonText, $formattedContext);
    
    echo "<strong>Response:</strong> " . htmlspecialchars($response) . "<br>";
    
    // Check response type
    if (strpos($response, '[Quick Question - Rule-based]') !== false) {
        echo "<span style='color: green;'>✅ Using Quick Question Rule-based Response</span>";
    } elseif (strpos($response, '[Rule-based Response]') !== false) {
        echo "<span style='color: blue;'>📝 Using General Rule-based Response</span>";
    } elseif (strpos($response, '[OpenAI Response]') !== false) {
        echo "<span style='color: red;'>❌ Using OpenAI (UNEXPECTED - should be rule-based)</span>";
    } else {
        echo "<span style='color: red;'>❌ Unknown response type</span>";
    }
    
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='client_ai.php?debug=1'>Test AI Chatbot with Debug Mode</a> | <a href='client_ai.php'>Test AI Chatbot Normal Mode</a></p>";
?>
