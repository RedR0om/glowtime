<?php
require_once 'inc/bootstrap.php';

echo "<h2>Testing OpenAI Integration</h2>";

// Test if API key is defined
if (defined('OPENAI_API_KEY') && OPENAI_API_KEY) {
    echo "<p style='color: green;'>✅ API Key is configured</p>";
    
    // Test a simple OpenAI call
    $testPrompt = "Hello! I'm testing the OpenAI integration. Can you give me a brief beauty tip?";
    $systemPrompt = "You are a professional beauty assistant. Provide helpful, concise beauty advice.";
    
    echo "<p>Testing OpenAI API call...</p>";
    
    $response = openai_call_with_context($systemPrompt, $testPrompt);
    
    if ($response) {
        echo "<div style='background: #f0f8ff; padding: 15px; border-left: 4px solid #007bff; margin: 10px 0;'>";
        echo "<strong>OpenAI Response:</strong><br>";
        echo htmlspecialchars($response);
        echo "</div>";
        echo "<p style='color: green;'>✅ OpenAI integration is working!</p>";
    } else {
        echo "<p style='color: red;'>❌ OpenAI API call failed</p>";
    }
    
} else {
    echo "<p style='color: red;'>❌ API Key is not configured</p>";
}

// Test the generateAIResponse function
echo "<h3>Testing generateAIResponse Function</h3>";
$testMessage = "What hair color would look good on me?";
$testContext = "User booking history: Haircut, Hair Color";

$aiResponse = generateAIResponse($testMessage, $testContext);

if ($aiResponse) {
    echo "<div style='background: #f0fff0; padding: 15px; border-left: 4px solid #28a745; margin: 10px 0;'>";
    echo "<strong>AI Response:</strong><br>";
    echo htmlspecialchars($aiResponse);
    echo "</div>";
    echo "<p style='color: green;'>✅ AI Response function is working!</p>";
} else {
    echo "<p style='color: red;'>❌ AI Response function failed</p>";
}

echo "<hr>";
echo "<p><a href='client_ai.php'>Go to AI Chatbot</a> | <a href='client_dashboard.php'>Go to Dashboard</a></p>";
?>
