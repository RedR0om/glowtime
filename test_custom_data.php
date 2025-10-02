<?php
require_once 'inc/bootstrap.php';
require_once 'inc/data_loader.php';

echo "<h2>Testing Custom Data Integration</h2>";

// Test 1: Load services data
echo "<h3>1. Services Data</h3>";
$services = SalonDataLoader::getServicesData();
if ($services) {
    echo "<p style='color: green;'>✅ Services data loaded successfully</p>";
    echo "<p>Found " . count($services['services']) . " services</p>";
    
    // Show first service as example
    if (!empty($services['services'])) {
        $firstService = $services['services'][0];
        echo "<div style='background: #f0f8ff; padding: 10px; margin: 10px 0; border-left: 4px solid #007bff;'>";
        echo "<strong>{$firstService['name']}</strong><br>";
        echo "Description: {$firstService['description']}<br>";
        echo "Price: \${$firstService['price']} | Duration: {$firstService['duration']} minutes<br>";
        echo "Category: {$firstService['category']}<br>";
        echo "Trends: " . implode(', ', $firstService['trends']);
        echo "</div>";
    }
} else {
    echo "<p style='color: red;'>❌ Failed to load services data</p>";
}

// Test 2: Load knowledge data
echo "<h3>2. Knowledge Base</h3>";
$knowledge = SalonDataLoader::getKnowledgeData();
if ($knowledge) {
    echo "<p style='color: green;'>✅ Knowledge data loaded successfully</p>";
    
    if (isset($knowledge['salon_info'])) {
        $salonInfo = $knowledge['salon_info'];
        echo "<div style='background: #f0fff0; padding: 10px; margin: 10px 0; border-left: 4px solid #28a745;'>";
        echo "<strong>Salon: {$salonInfo['name']}</strong><br>";
        echo "Specialties: " . implode(', ', $salonInfo['specialties']) . "<br>";
        echo "Experience: {$salonInfo['experience_years']} years<br>";
        echo "Awards: " . implode(', ', $salonInfo['awards']);
        echo "</div>";
    }
} else {
    echo "<p style='color: red;'>❌ Failed to load knowledge data</p>";
}

// Test 3: Get beauty tips
echo "<h3>3. Beauty Tips</h3>";
$tips = SalonDataLoader::getBeautyTips();
if ($tips) {
    echo "<p style='color: green;'>✅ Beauty tips loaded successfully</p>";
    foreach ($tips as $category => $tipList) {
        echo "<strong>{$category}:</strong><br>";
        foreach ($tipList as $tip) {
            echo "• {$tip}<br>";
        }
        echo "<br>";
    }
} else {
    echo "<p style='color: red;'>❌ Failed to load beauty tips</p>";
}

// Test 4: Build AI context
echo "<h3>4. AI Context Building</h3>";
$userHistory = ["Haircut", "Balayage Highlights"];
$context = SalonDataLoader::buildAIContext($userHistory);
$formattedContext = SalonDataLoader::formatContextForAI($context);

echo "<p style='color: green;'>✅ AI context built successfully</p>";
echo "<div style='background: #fff3cd; padding: 15px; margin: 10px 0; border-left: 4px solid #ffc107;'>";
echo "<strong>Formatted Context for AI:</strong><br>";
echo "<pre style='white-space: pre-wrap; font-size: 12px;'>" . htmlspecialchars($formattedContext) . "</pre>";
echo "</div>";

// Test 5: Test AI with custom data
echo "<h3>5. AI Response with Custom Data</h3>";
$testMessage = "What hair color would look good on me?";
$aiResponse = generateAIResponse($testMessage, $formattedContext);

if ($aiResponse) {
    echo "<div style='background: #d1ecf1; padding: 15px; margin: 10px 0; border-left: 4px solid #17a2b8;'>";
    echo "<strong>AI Response:</strong><br>";
    echo htmlspecialchars($aiResponse);
    echo "</div>";
    echo "<p style='color: green;'>✅ AI response generated successfully with custom data!</p>";
} else {
    echo "<p style='color: red;'>❌ AI response failed</p>";
}

echo "<hr>";
echo "<p><a href='client_ai.php'>Go to AI Chatbot</a> | <a href='client_dashboard.php'>Go to Dashboard</a></p>";
?>
