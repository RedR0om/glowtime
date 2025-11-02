<?php
header('Content-Type: application/json');
session_start();

// Allow CORS for public access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/data_loader.php';

$response = [];
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $message = isset($input['message']) ? trim($input['message']) : '';
        
        if (!empty($message)) {
            // Get user's booking history if logged in (optional context)
            $userHistory = [];
            if (isset($_SESSION['user_id'])) {
                $stmt = pdo()->prepare("
                    SELECT s.name as service_name, a.style, a.start_at
                    FROM appointments a 
                    JOIN services s ON s.id = a.service_id 
                    WHERE a.client_id = ? AND a.status IN ('confirmed', 'completed')
                    ORDER BY a.start_at DESC 
                    LIMIT 5
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $history = $stmt->fetchAll();
                
                if ($history) {
                    foreach ($history as $h) {
                        $userHistory[] = $h['service_name'] . ($h['style'] ? " ({$h['style']})" : '');
                    }
                }
            }
            
            // Build comprehensive context using custom data
            $customContext = SalonDataLoader::buildAIContext($userHistory);
            $formattedContext = SalonDataLoader::formatContextForAI($customContext);
            
            // Generate AI response
            $aiResponse = generateAIResponse($message, $formattedContext);
            
            $response = [
                'success' => true,
                'message' => $aiResponse
            ];
        } else {
            $response = [
                'success' => false,
                'message' => 'Please enter a message.'
            ];
        }
    } else {
        $response = [
            'success' => false,
            'message' => 'Invalid request method.'
        ];
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ];
    error_log("Chatbot API Error: " . $e->getMessage());
}

echo json_encode($response);
exit;

function generateAIResponse($message, $context) {
    $messageLower = strtolower($message);
    
    // Define quick questions that should ALWAYS use rule-based responses
    $quickQuestions = [
        'how do i book an appointment',
        'how to book appointment',
        'book appointment',
        'appointment booking',
        'how do i book',
        'booking process',
        'schedule appointment',
        'what hair style would suit my face shape',
        'what hair style suits me',
        'hair style would suit',
        'what hair color would look good on me',
        'recommend hair color',
        'hair color would look good',
        'what skincare routine should i follow',
        'skincare advice',
        'skincare routine should i follow',
        'how do i maintain healthy nails',
        'nail care tips',
        'maintain healthy nails'
    ];
    
    // Check if this is a quick question that should use rule-based responses
    $isQuickQuestion = false;
    foreach ($quickQuestions as $quickQ) {
        if (strpos($messageLower, $quickQ) !== false) {
            $isQuickQuestion = true;
            break;
        }
    }
    
    // If it's a quick question, skip OpenAI and go straight to rule-based
    if ($isQuickQuestion) {
        return getQuickQuestionResponse($messageLower);
    }
    
    // For other questions, try OpenAI API first
    if (defined('OPENAI_API_KEY') && OPENAI_API_KEY) {
        $systemPrompt = "You are a professional beauty and salon assistant for Glowtime Salon. You have access to comprehensive salon data including services, beauty tips, seasonal recommendations, and product suggestions. Use this information to provide expert, personalized advice. Always be friendly, professional, and helpful. Reference specific services, products, or tips when relevant. If you don't know something specific about a client's situation, recommend they consult with our professional stylists.";
        
        $userPrompt = "Context: {$context}\n\nClient question: {$message}\n\nPlease provide a helpful, personalized response as a salon beauty assistant using the provided context information.";
        
        $openaiResponse = openai_call_with_context($systemPrompt, $userPrompt);
        
        // Only use OpenAI response if it's valid and not empty
        if ($openaiResponse && trim($openaiResponse) !== '' && strlen(trim($openaiResponse)) > 10) {
            return trim($openaiResponse);
        }
        
        // Log when OpenAI fails for debugging
        error_log("OpenAI API failed or returned invalid response. Falling back to rule-based responses. Message: " . $message);
    }
    
    // Fallback to rule-based responses if OpenAI fails
    $message = strtolower($message);
    
    // Hair style recommendations
    if (strpos($message, 'hair') !== false || strpos($message, 'style') !== false || strpos($message, 'cut') !== false) {
        $responses = [
            "Based on current trends, I'd recommend trying a layered bob cut - it's versatile and flattering on most face shapes!",
            "Have you considered a balayage? It adds natural-looking highlights that can brighten your overall look.",
            "A pixie cut might be perfect if you're looking for something low-maintenance yet stylish.",
            "Long layers with face-framing pieces are very popular right now and work well with most hair textures."
        ];
        return $responses[array_rand($responses)];
    }
    
    // Color recommendations
    if (strpos($message, 'color') !== false || strpos($message, 'highlight') !== false) {
        $responses = [
            "For a natural look, try honey blonde highlights or caramel lowlights - they complement most skin tones.",
            "Balayage is a great technique for adding dimension without harsh lines. Consider warm tones for a sun-kissed effect.",
            "If you want something bold, consider rose gold or copper tones - they're very trendy right now!",
            "Ash tones are perfect if you prefer cooler colors - they can make your eyes pop!"
        ];
        return $responses[array_rand($responses)];
    }
    
    // Skin care
    if (strpos($message, 'skin') !== false || strpos($message, 'facial') !== false) {
        $responses = [
            "For healthy skin, I recommend our hydrating facial treatment followed by a good skincare routine at home.",
            "Consider a deep cleansing facial if you have oily or acne-prone skin - it can work wonders!",
            "Anti-aging facials with vitamin C are great for maintaining youthful, glowing skin.",
            "Don't forget daily SPF protection - it's the best anti-aging treatment you can use!"
        ];
        return $responses[array_rand($responses)];
    }
    
    // Nail care
    if (strpos($message, 'nail') !== false || strpos($message, 'manicure') !== false || strpos($message, 'pedicure') !== false) {
        $responses = [
            "For long-lasting nails, try gel polish - it can last up to 2-3 weeks without chipping!",
            "French manicures are timeless and professional-looking for any occasion.",
            "Consider nail art with subtle designs - it's a fun way to express your personality!",
            "Regular cuticle care and moisturizing are key to healthy, beautiful nails."
        ];
        return $responses[array_rand($responses)];
    }
    
    // Booking related
    if (strpos($message, 'book') !== false || strpos($message, 'appointment') !== false) {
        return "I'd be happy to help you book an appointment! You can use our online booking system to choose your preferred service, date, and time. Would you like me to guide you through the process?";
    }
    
    // General greeting/help
    if (strpos($message, 'hello') !== false || strpos($message, 'hi') !== false || strpos($message, 'help') !== false) {
        return "Hello! I'm your AI beauty assistant. I can help you with style recommendations, answer questions about our services, or guide you through booking an appointment. What would you like to know?";
    }
    
    // Default response
    return "That's an interesting question! While I'd love to give you personalized advice, I recommend consulting with one of our professional stylists who can assess your specific needs. Would you like me to help you book a consultation?";
}

// Function to handle quick questions with specific responses
function getQuickQuestionResponse($message) {
    // Booking related questions
    if (strpos($message, 'book') !== false || strpos($message, 'appointment') !== false) {
        return "I'd be happy to help you book an appointment! You can use our online booking system to choose your preferred service, date, and time. Would you like me to guide you through the process?";
    }
    
    // Hair style questions
    if (strpos($message, 'hair') !== false && (strpos($message, 'style') !== false || strpos($message, 'suit') !== false)) {
        return "## Perfect Hairstyle Consultation\n\nFor the perfect hairstyle, I'd recommend consulting with one of our **professional stylists** who can assess:\n\n- Your face shape\n- Hair texture and type\n- Lifestyle and maintenance preferences\n- Current hair condition\n\nWe offer **complimentary consultations** to help you find the ideal look!";
    }
    
    // Hair color questions
    if (strpos($message, 'hair') !== false && (strpos($message, 'color') !== false || strpos($message, 'highlight') !== false)) {
        return "## Hair Color Consultation\n\nOur **color specialists** can help you find the perfect shade! We offer complimentary color consultations where we'll analyze:\n\n- Your skin tone and undertones\n- Eye color and natural features\n- Natural hair color and texture\n- Lifestyle and maintenance preferences\n\nWe'll recommend the most **flattering options** for your unique features!";
    }
    
    // Skincare questions
    if (strpos($message, 'skin') !== false || strpos($message, 'skincare') !== false) {
        return "## Personalized Skincare Consultation\n\nFor personalized skincare advice, I recommend booking a consultation with our **skincare specialist**. They can:\n\n- Analyze your skin type and concerns\n- Create a **customized routine** just for you\n- Recommend professional treatments\n- Provide home care guidance\n\nBook your consultation today for **healthy, glowing skin**!";
    }
    
    // Nail care questions
    if (strpos($message, 'nail') !== false || strpos($message, 'manicure') !== false) {
        return "## Beautiful Nail Care\n\nFor **healthy, beautiful nails**, I recommend:\n\n- Regular manicures and pedicures with our skilled technicians\n- **Nail strengthening treatments** for weak nails\n- Gel polish options for long-lasting results\n- Professional nail art and design\n\nOur nail specialists can help you achieve the perfect look!";
    }
    
    // Default response for quick questions
    return "I'd be happy to help! For personalized advice, I recommend booking a consultation with one of our professional stylists who can assess your specific needs and provide expert recommendations.";
}

// Enhanced OpenAI function with better context handling
function openai_call_with_context($systemPrompt, $userPrompt) {
    // Ensure OpenAI API key is loaded
    if (!defined('OPENAI_API_KEY') || empty(OPENAI_API_KEY)) {
        error_log("⚠️ Missing OpenAI API key in bootstrap.php");
        return false;
    }

    $api_key = trim(OPENAI_API_KEY);
    $url = "https://api.openai.com/v1/chat/completions";

    $payload = [
        "model" => "gpt-4o-mini",
        "messages" => [
            ["role" => "system", "content" => $systemPrompt],
            ["role" => "user", "content" => $userPrompt]
        ],
        "temperature" => 0.7,
        "max_tokens" => 400
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer {$api_key}"
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30,
    ]);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        error_log("OpenAI API Error: $err");
        return false;
    }

    $data = json_decode($res, true);

    if ($httpCode !== 200) {
        error_log("OpenAI HTTP $httpCode Response: $res");
        return false;
    }

    if (isset($data['choices'][0]['message']['content'])) {
        return trim($data['choices'][0]['message']['content']);
    } else {
        error_log("Unexpected OpenAI response: " . $res);
        return false;
    }
}
?>



