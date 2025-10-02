<?php
require_once 'inc/bootstrap.php';
require_once 'inc/data_loader.php';

echo "<h2>Testing Data Loader Fix</h2>";

try {
    // Test loading services data
    $services = SalonDataLoader::getServicesData();
    echo "<p style='color: green;'>✅ Services data loaded successfully</p>";
    
    if ($services && isset($services['services'])) {
        echo "<p>Found " . count($services['services']) . " services</p>";
        
        // Test context building
        $context = SalonDataLoader::buildAIContext(['Haircut', 'Color']);
        echo "<p style='color: green;'>✅ Context built successfully</p>";
        
        // Test formatting (this was causing the error)
        $formattedContext = SalonDataLoader::formatContextForAI($context);
        echo "<p style='color: green;'>✅ Context formatted successfully</p>";
        
        echo "<div style='background: #f0f8ff; padding: 15px; margin: 10px 0; border-left: 4px solid #007bff;'>";
        echo "<strong>Formatted Context:</strong><br>";
        echo "<pre style='white-space: pre-wrap; font-size: 12px;'>" . htmlspecialchars($formattedContext) . "</pre>";
        echo "</div>";
        
        echo "<p style='color: green;'>✅ All tests passed! The error is fixed.</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ No services data found, but no errors occurred</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Line: " . $e->getLine() . " in " . $e->getFile() . "</p>";
}

echo "<hr>";
echo "<p><a href='test_custom_data.php'>Go to Full Data Test</a> | <a href='client_ai.php'>Go to AI Chatbot</a></p>";
?>
