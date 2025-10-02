<?php
require_once 'inc/bootstrap.php';
require_once 'inc/data_loader.php';

echo "<h2>Debug AI Response System</h2>";

// Test different types of questions
$testQuestions = [
    "What hair style would suit my face shape?",
    "What hair color would look good on me?", 
    "What skincare routine should I follow?",
    "How do I maintain healthy nails?",
    "How do I book an appointment?",
    "Hello, I need help with my hair"
];

echo "<h3>Testing AI Response Logic</h3>";

foreach ($testQuestions as $question) {
    echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<strong>Question:</strong> " . htmlspecialchars($question) . "<br><br>";
    
    // Test with debug mode
    $_GET['debug'] = true;
    
    // Build context
    $context = SalonDataLoader::buildAIContext(['Haircut', 'Color']);
    $formattedContext = SalonDataLoader::formatContextForAI($context);
    
    // Get AI response
    $response = generateAIResponse($question, $formattedContext);
    
    echo "<strong>Response:</strong> " . htmlspecialchars($response) . "<br>";
    
    // Check if it's OpenAI or rule-based
    if (strpos($response, '[OpenAI Response]') !== false) {
        echo "<span style='color: green;'>✅ Using OpenAI API</span>";
    } elseif (strpos($response, '[Rule-based Response]') !== false) {
        echo "<span style='color: blue;'>📝 Using Rule-based fallback</span>";
    } else {
        echo "<span style='color: orange;'>⚠️ Unknown response type</span>";
    }
    
    echo "</div>";
}

// Test OpenAI API directly
echo "<h3>Direct OpenAI API Test</h3>";
$testPrompt = "What hair color would look good on me?";
$systemPrompt = "You are a professional beauty assistant.";
$userPrompt = "Context: Salon services available\n\nClient question: {$testPrompt}";

$openaiResponse = openai_call_with_context($systemPrompt, $userPrompt);

if ($openaiResponse) {
    echo "<div style='background: #f0f8ff; padding: 15px; border-left: 4px solid #007bff;'>";
    echo "<strong>OpenAI Direct Response:</strong><br>";
    echo htmlspecialchars($openaiResponse);
    echo "</div>";
    echo "<p style='color: green;'>✅ OpenAI API is working</p>";
} else {
    echo "<p style='color: red;'>❌ OpenAI API failed</p>";
}

echo "<hr>";
echo "<p><a href='client_ai.php?debug=1'>Test AI Chatbot with Debug Mode</a> | <a href='client_ai.php'>Test AI Chatbot Normal Mode</a></p>";
?>
