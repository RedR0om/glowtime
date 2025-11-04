<?php
session_start();

// Include chatbot functions (same as client_ai.php) - MUST be included BEFORE AJAX handler
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/data_loader.php';

// Chatbot functions (same as client_ai.php) - MUST be defined BEFORE AJAX handler uses them
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
        return "⚠️ Missing OpenAI API key. Please check your configuration.";
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
        return "❌ Connection error to AI service. Please try again.";
    }

    $data = json_decode($res, true);

    if ($httpCode !== 200) {
        error_log("OpenAI HTTP $httpCode Response: $res");
        if ($httpCode === 401) {
            return "❌ Invalid OpenAI API key. Please check your bootstrap.php file.";
        }
        return "❌ AI request failed with status $httpCode.";
    }

    if (isset($data['choices'][0]['message']['content'])) {
        return trim($data['choices'][0]['message']['content']);
    } else {
        error_log("Unexpected OpenAI response: " . $res);
        return "⚠️ AI did not return a valid message.";
    }
}

// Handle chatbot AJAX request (same logic as client_ai.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && isset($_POST['chatbot_ajax'])) {
    // This is an AJAX chatbot request - return JSON response
    header('Content-Type: application/json');
    
    $message = trim($_POST['message']);
    $response = ['success' => false, 'message' => ''];
    
    if (!empty($message)) {
        // Get user's booking history if logged in (optional context)
        $userHistory = [];
        if (isset($_SESSION['user_id'])) {
            try {
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
            } catch (Exception $e) {
                // If database query fails, continue without history
                error_log("Error fetching user history: " . $e->getMessage());
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
        $response['message'] = "Please enter a message.";
    }
    
    echo json_encode($response);
    exit;
}

// If user is already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: client_dashboard.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Glowtime Salon - Your Beauty, Our Passion</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom Salon CSS -->
    <link href="css/salon-style.css" rel="stylesheet">
    
    <!-- Enhanced Landing Page Styles -->
    <style>
        /* Root Variables - Pink & Flowery Theme */
        :root {
            --salon-primary: #e91e63;
            --salon-primary-dark: #c2185b;
            --salon-secondary: #f8bbd9;
            --salon-light: #fdf2f8;
            --salon-accent: #ff69b4;
            --salon-shadow: 0 10px 30px rgba(233, 30, 99, 0.15);
            --salon-shadow-hover: 0 20px 40px rgba(233, 30, 99, 0.25);
        }

        /* Hero Section - Pink & Flowery Theme */
        .hero-section {
            background: linear-gradient(135deg, var(--salon-light), var(--salon-secondary));
            color: #333;
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            padding: 120px 0 80px;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('https://images.pexels.com/photos/7750108/pexels-photo-7750108.jpeg?auto=compress&cs=tinysrgb&w=1920&h=1080&fit=crop') center/cover no-repeat;
            opacity: 0.4;
            z-index: 1;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .floral-accent {
            display: inline-block;
            font-size: 2rem;
            color: var(--salon-primary);
            margin-bottom: 1rem;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #2d3748;
            line-height: 1.2;
            text-align: center;
        }
        
        .hero-subtitle {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            color: #4a5568;
            font-weight: 400;
            text-align: center;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .hero-image-grid {
            position: relative;
        }

        .hero-image-grid img {
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
        }

        .hero-image-grid img:hover {
            transform: scale(1.05);
        }

        .hero-stats {
            margin-top: 3rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-item h3 {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-item p {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        /* Floral Decorations */
        .floral-decoration {
            position: absolute;
            font-size: 4rem;
            opacity: 0.1;
            animation: float 8s ease-in-out infinite;
        }

        .floral-decoration-1 {
            top: 20%;
            right: 10%;
            animation-delay: 0s;
        }

        .floral-decoration-2 {
            bottom: 20%;
            left: 10%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }
        
        /* Enhanced Cards */
        .service-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--salon-shadow);
            transition: all 0.3s ease;
            height: 100%;
            border: none;
        }
        
        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--salon-shadow-hover);
        }
        
        .service-image {
            position: relative;
            height: 250px;
            overflow: hidden;
        }

        .service-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .service-card-lg {
            min-height: 520px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .service-card-lg:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 12px 40px rgba(233,30,99,0.18), 0 2px 8px rgba(0,0,0,0.04);
        }
        @media (max-width: 991.98px) {
            .service-card-lg {
                min-height: 0;
            }
        }

        .service-card:hover .service-image img {
            transform: scale(1.1);
        }

        .service-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            background: var(--salon-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(231, 84, 128, 0.3);
        }

        .service-list {
            list-style: none;
            padding: 0;
        }

        .service-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #f8f9fa;
        }

        .service-list li:last-child {
            border-bottom: none;
        }

        /* Testimonial Cards */
        .testimonial-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--salon-shadow);
            text-align: left;
            margin-bottom: 2rem;
            border: none;
            transition: all 0.3s ease;
        }

        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--salon-shadow-hover);
        }

        .testimonial-card .stars {
            margin-bottom: 1rem;
        }

        .testimonial-card img {
            width: 60px;
            height: 60px;
            object-fit: cover;
        }

        /* About Section */
        .about-images img {
            border-radius: 20px;
            box-shadow: var(--salon-shadow);
            transition: transform 0.3s ease;
        }

        .about-images img:hover {
            transform: scale(1.05);
        }

        .feature-item {
            text-align: center;
            padding: 1rem;
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, var(--salon-primary), var(--salon-primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            color: white;
            font-size: 1.5rem;
        }

        /* Navigation */
        .navbar-landing {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .brand-text {
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        /* Buttons */
        .btn-glow {
            background: linear-gradient(45deg, var(--salon-primary), var(--salon-primary-dark));
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(231, 84, 128, 0.4);
        }
        
        .btn-glow:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(231, 84, 128, 0.6);
            color: white;
        }
        
        /* Contact Section */
        .contact-info .contact-item {
            margin-bottom: 2rem;
        }

        .contact-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(45deg, var(--salon-primary), var(--salon-primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .booking-form {
            border-radius: 20px;
            box-shadow: var(--salon-shadow);
        }

        .map-container {
            box-shadow: var(--salon-shadow);
        }

        /* Footer */
        .social-links a {
            display: inline-block;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            text-align: center;
            line-height: 40px;
            margin-right: 10px;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background: var(--salon-primary);
            transform: translateY(-2px);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.2rem;
            }

            .floral-decoration {
                display: none;
            }
        }
        
        /* Chatbot Popup Styles */
        .chatbot-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .chatbot-toggle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--salon-primary), var(--salon-primary-dark));
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(231, 84, 128, 0.4);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .chatbot-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(231, 84, 128, 0.6);
        }
        
        .chatbot-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .chatbot-window {
            position: absolute;
            bottom: 80px;
            right: 0;
            width: 350px;
            height: 500px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            display: none;
            flex-direction: column;
            overflow: hidden;
        }
        
        .chatbot-window.show {
            display: flex;
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .chatbot-header {
            background: linear-gradient(45deg, var(--salon-primary), var(--salon-primary-dark));
            color: white;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .chatbot-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .chatbot-info {
            flex: 1;
        }
        
        .chatbot-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: background 0.3s ease;
        }
        
        .chatbot-close:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .chatbot-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 15px;
        }
        
        .chatbot-messages {
            flex: 1;
            overflow-y: auto;
            max-height: 300px;
            margin-bottom: 15px;
        }
        
        .chatbot-message {
            display: flex;
            margin-bottom: 15px;
            align-items: flex-start;
        }
        
        .chatbot-ai-message {
            justify-content: flex-start;
        }
        
        .chatbot-user-message {
            justify-content: flex-end;
        }
        
        .message-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        
        .chatbot-ai-message .message-avatar {
            background: var(--salon-primary);
            color: white;
            margin-right: 10px;
        }
        
        .chatbot-user-message .message-avatar {
            background: #007bff;
            color: white;
            margin-left: 10px;
        }
        
        .message-bubble {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 18px;
            max-width: 250px;
            word-wrap: break-word;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .chatbot-user-message .message-bubble {
            background: var(--salon-primary);
            color: white;
        }
        
        .chatbot-quick-questions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .quick-btn {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 20px;
            padding: 8px 12px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .quick-btn:hover {
            background: var(--salon-primary);
            color: white;
            border-color: var(--salon-primary);
        }
        
        .chatbot-input {
            border-top: 1px solid #e9ecef;
            padding-top: 15px;
        }
        
        .chatbot-input .form-control {
            border-radius: 20px;
            border: 1px solid #e9ecef;
            font-size: 0.9rem;
        }
        
        .chatbot-input .form-control:focus {
            border-color: var(--salon-primary);
            box-shadow: 0 0 0 0.2rem rgba(231, 84, 128, 0.25);
        }
        
        .chatbot-input .btn {
            border-radius: 50%;
            width: 40px;
            height: 40px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Typing indicator animation */
        .typing-dots {
            display: flex;
            gap: 4px;
        }
        
        .typing-dots span {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #999;
            animation: typing 1.4s infinite ease-in-out;
        }
        
        .typing-dots span:nth-child(1) {
            animation-delay: -0.32s;
        }
        
        .typing-dots span:nth-child(2) {
            animation-delay: -0.16s;
        }
        
        @keyframes typing {
            0%, 80%, 100% {
                transform: scale(0);
                opacity: 0.5;
            }
            40% {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .chatbot-container {
                bottom: 15px;
                right: 15px;
            }
            
            .chatbot-window {
                width: 320px;
                height: 450px;
            }
            
            .chatbot-toggle {
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Enhanced Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light navbar-landing fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#home">
                <i class="bi bi-flower1 me-2" style="color: var(--salon-primary);"></i>
                <span class="brand-text">Glowtime Salon</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#services">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#testimonials">Testimonials</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                    <li class="nav-item ms-2">
                        <a href="login.php" class="nav-link btn btn-sm px-3" style="background-color: var(--salon-primary); border-color: var(--salon-primary); color: white;">
                            Book Now
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Beautiful Hero Section -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <div class="hero-content">
                        <div class="floral-accent mb-4">
                            <i class="bi bi-flower2"></i>
                        </div>
                        <h1 class="hero-title">Blossom Into Your Most Beautiful Self</h1>
                        <p class="hero-subtitle">Experience luxury beauty services in a serene, feminine atmosphere. Where elegance meets expertise.</p>
                        <div class="d-flex flex-wrap gap-3 justify-content-center">
                            <a href="register.php" class="btn btn-lg px-5" style="background-color: var(--salon-primary); border-color: var(--salon-primary); color: white;">
                                Book Your Appointment
                            </a>
                            <a href="login.php" class="btn btn-lg px-5" style="background-color: rgba(255, 255, 255, 0.85); border-color: rgba(255, 255, 255, 0.5); color: var(--salon-primary); box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);">
                                Explore Services
                            </a>
                        </div>
                </div>
                    </div>
                </div>
            </div>
        <div class="floral-decoration floral-decoration-1">
            <i class="bi bi-flower3"></i>
        </div>
        <div class="floral-decoration floral-decoration-2">
            <i class="bi bi-flower1"></i>
        </div>
    </section>

    <!-- Beautiful Services Section -->
    <section id="services" class="py-5" style="background-color: var(--salon-light);">
        <div class="container py-5">
            <div class="text-center mb-5">
                <div class="d-flex align-items-center justify-content-center mb-3">
                    <i class="bi bi-flower2 me-3" style="color: var(--salon-primary);"></i>
                    <h2 class="display-4 fw-bold mb-0">Our Services</h2>
                    <i class="bi bi-flower2 ms-3" style="color: var(--salon-primary);"></i>
                </div>
                <p class="lead text-muted">Discover our comprehensive range of beauty services, each designed to enhance your natural beauty and boost your confidence.</p>
            </div>
            <div class="row g-5 justify-content-center">
                <!-- Hair Services -->
                <div class="col-md-6 col-lg-5">
                    <div class="card h-100 border-0 shadow-lg service-card-lg" style="border-radius: 24px;">
                        <div class="position-relative" style="overflow: hidden; border-top-left-radius: 24px; border-top-right-radius: 24px;">
                            <img src="hrcut.jpg" class="w-100" alt="Hair Services" style="height: 270px; object-fit: cover;">
                            <div class="position-absolute bottom-0 start-0 w-100 p-4" style="background: linear-gradient(0deg, rgba(233,30,99,0.85) 0%, rgba(233,30,99,0.15) 100%); border-bottom-left-radius: 24px; border-bottom-right-radius: 24px;">
                                <div class="d-flex align-items-center">
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle me-3" style="width:48px;height:48px;background:var(--salon-primary);">
                                        <i class="bi bi-scissors text-white fs-4"></i>
                                    </span>
                                    <span class="fs-4 fw-bold text-white">Hair Services</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-muted mb-4">Transform your look with our expert stylists who specialize in the latest trends and timeless classics.</p>
                            <ul class="list-unstyled mb-0" style="font-size:1.1rem;">
                                <li class="mb-2"><span style="color:var(--salon-primary);font-size:1.2em;">&#10003;</span> Haircuts & Styling</li>
                                <li class="mb-2"><span style="color:var(--salon-primary);font-size:1.2em;">&#10003;</span> Coloring & Highlights</li>
                                <li class="mb-2"><span style="color:var(--salon-primary);font-size:1.2em;">&#10003;</span> Treatments & Conditioning</li>
                                <li><span style="color:var(--salon-primary);font-size:1.2em;">&#10003;</span> Bridal & Special Events</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <!-- Manicure Services -->
                <div class="col-md-6 col-lg-5">
                    <div class="card h-100 border-0 shadow-lg service-card-lg" style="border-radius: 24px;">
                        <div class="position-relative" style="overflow: hidden; border-top-left-radius: 24px; border-top-right-radius: 24px;">
                            <img src="hrspa.jpg" class="w-100" alt="Manicure Services" style="height: 270px; object-fit: cover;">
                            <div class="position-absolute bottom-0 start-0 w-100 p-4" style="background: linear-gradient(0deg, rgba(233,30,99,0.85) 0%, rgba(233,30,99,0.15) 100%); border-bottom-left-radius: 24px; border-bottom-right-radius: 24px;">
                                <div class="d-flex align-items-center">
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle me-3" style="width:48px;height:48px;background:var(--salon-primary);">
                                        <i class="bi bi-hand-index text-white fs-4"></i>
                                    </span>
                                    <span class="fs-4 fw-bold text-white">Manicure</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-muted mb-4">Pamper your hands with our luxurious manicure services, from classic elegance to creative nail art.</p>
                            <ul class="list-unstyled mb-0" style="font-size:1.1rem;">
                                <li class="mb-2"><span style="color:var(--salon-primary);font-size:1.2em;">&#10003;</span> Classic Manicure</li>
                                <li class="mb-2"><span style="color:var(--salon-primary);font-size:1.2em;">&#10003;</span> Gel Manicure</li>
                                <li class="mb-2"><span style="color:var(--salon-primary);font-size:1.2em;">&#10003;</span> Nail Art & Design</li>
                                <li><span style="color:var(--salon-primary);font-size:1.2em;">&#10003;</span> Hand Spa Treatment</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row g-5 justify-content-center mt-4">
                <!-- Pedicure Services -->
                <div class="col-md-6 col-lg-5">
                    <div class="card h-100 border-0 shadow-lg service-card-lg" style="border-radius: 24px;">
                        <div class="position-relative" style="overflow: hidden; border-top-left-radius: 24px; border-top-right-radius: 24px;">
                            <img src="hrclr.jpg" class="w-100" alt="Pedicure Services" style="height: 270px; object-fit: cover;">
                            <div class="position-absolute bottom-0 start-0 w-100 p-4" style="background: linear-gradient(0deg, rgba(233,30,99,0.85) 0%, rgba(233,30,99,0.15) 100%); border-bottom-left-radius: 24px; border-bottom-right-radius: 24px;">
                                <div class="d-flex align-items-center">
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle me-3" style="width:48px;height:48px;background:var(--salon-primary);">
                                        <i class="bi bi-heart text-white fs-4"></i>
                                    </span>
                                    <span class="fs-4 fw-bold text-white">Pedicure</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-muted mb-4">Indulge in our rejuvenating pedicure treatments that leave your feet feeling refreshed and beautiful.</p>
                            <ul class="list-unstyled mb-0" style="font-size:1.1rem;">
                                <li class="mb-2"><span style="color:var(--salon-primary);font-size:1.2em;">&#10003;</span> Classic Pedicure</li>
                                <li class="mb-2"><span style="color:var(--salon-primary);font-size:1.2em;">&#10003;</span> Spa Pedicure</li>
                                <li class="mb-2"><span style="color:var(--salon-primary);font-size:1.2em;">&#10003;</span> Gel Pedicure</li>
                                <li><span style="color:var(--salon-primary);font-size:1.2em;">&#10003;</span> Foot Massage & Treatment</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <!-- Spa Services -->
                <div class="col-md-6 col-lg-5">
                    <div class="card h-100 border-0 shadow-lg service-card-lg" style="border-radius: 24px;">
                        <div class="position-relative" style="overflow: hidden; border-top-left-radius: 24px; border-top-right-radius: 24px;">
                            <img src="salonbnnr.jpg" class="w-100" alt="Spa Services" style="height: 270px; object-fit: cover;">
                            <div class="position-absolute bottom-0 start-0 w-100 p-4" style="background: linear-gradient(0deg, rgba(233,30,99,0.85) 0%, rgba(233,30,99,0.15) 100%); border-bottom-left-radius: 24px; border-bottom-right-radius: 24px;">
                                <div class="d-flex align-items-center">
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle me-3" style="width:48px;height:48px;background:var(--salon-primary);">
                                        <i class="bi bi-stars text-white fs-4"></i>
                                    </span>
                                    <span class="fs-4 fw-bold text-white">Spa Treatments</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-muted mb-4">Escape to tranquility with our comprehensive spa services designed to relax, rejuvenate, and restore.</p>
                            <ul class="list-unstyled mb-0" style="font-size:1.1rem;">
                                <li class="mb-2"><span style="color:var(--salon-primary);font-size:1.2em;">&#10003;</span> Facials & Skincare</li>
                                <li class="mb-2"><span style="color:var(--salon-primary);font-size:1.2em;">&#10003;</span> Body Treatments</li>
                                <li class="mb-2"><span style="color:var(--salon-primary);font-size:1.2em;">&#10003;</span> Massage Therapy</li>
                                <li><span style="color:var(--salon-primary);font-size:1.2em;">&#10003;</span> Aromatherapy</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Beautiful About Section -->
    <section id="about" class="py-5" style="background-color: var(--salon-light);">
        <div class="container py-5">
            <div class="row align-items-center g-5">
                <!-- Image and Years of Experience Card -->
                <div class="col-lg-6">
                    <div class="position-relative">
                        <img src="elegant-beauty-salon-interior-with-pink-flowers-an.jpg" alt="Salon interior" class="img-fluid rounded-4 shadow w-100">
                        <div class="position-absolute bottom-0 start-0 m-3">
                            <div class="text-white p-4 rounded-4 shadow" style="background-color: var(--salon-primary); min-width: 180px;">
                                <h2 class="fw-bold mb-1" style="font-size:2.5rem;">15+</h2>
                                <div style="font-size:1rem;">Years of Dedicated Service</div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- About Content -->
                <div class="col-lg-6">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-flower2 me-3 fs-3" style="color: var(--salon-primary);"></i>
                        <h2 class="fw-bold mb-0" style="font-size:2.5rem;">About Glowtime Salon</h2>
                    </div>
                    <p class="lead mb-3">
                        Welcome to Glowtime Salon! Our journey began in October 2009 when we transformed an old barbershop into the beauty haven you see today. What started as a humble side project has grown into a legacy of service and opportunity.
                    </p>
                    <p class="mb-3">
                        The owner purchased the land and established this salon not just as a business, but as a passion project—a stepping stone to create opportunities for others. Through the years, this venture has become a catalyst for change, helping countless individuals and families build better lives.
                    </p>
                    <p class="mb-3">
                        From 2009 onwards, what began as a simple dream flourished beyond imagination. We expanded to 6 branches across different locations, touching more lives and spreading our commitment to excellence. Even during the challenging times of the pandemic, our main branch remained stable and resilient, a testament to our dedication to serving our community.
                    </p>
                    <p class="fw-bold mb-4" style="color: var(--salon-primary);">
                        Today, Glowtime Salon stands strong—not just as a business, but as a symbol of perseverance, community, and the beauty of transformation.
                    </p>
                    <div class="row text-center mt-4">
                        <div class="col-4">
                            <h3 class="fw-bold mb-1" style="color: var(--salon-primary);">10,XXX+</h3>
                            <div class="text-muted" style="font-size:0.95rem;">Happy Clients</div>
                        </div>
                        <div class="col-4">
                            <h3 class="fw-bold mb-1" style="color: var(--salon-primary);">16</h3>
                            <div class="text-muted" style="font-size:0.95rem;">Years</div>
                        </div>
                        <div class="col-4">
                            <h3 class="fw-bold mb-1" style="color: var(--salon-primary);">10+</h3>
                            <div class="text-muted" style="font-size:0.95rem;">Services Offered</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Beautiful Client Love Section -->
    <section id="testimonials" class="py-5">
        <div class="container py-5">
            <div class="text-center mb-5">
                <div class="d-flex align-items-center justify-content-center mb-3">
                    <i class="bi bi-heart-fill me-3" style="color: var(--salon-primary);"></i>
                    <h2 class="display-4 fw-bold mb-0">Client Love</h2>
                    <i class="bi bi-heart-fill ms-3" style="color: var(--salon-primary);"></i>
                </div>
                <p class="lead text-muted">Don't just take our word for it. Hear what our beautiful clients have to say about their experiences.</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="testimonial-card card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <img src="hrcut.jpg" alt="Sarah Mitchell" class="img-fluid rounded-circle mb-3" style="width: 80px; height: 80px; object-fit: cover;">
                            <div class="stars mb-3">
                                <i class="bi bi-star-fill" style="color: var(--salon-primary);"></i>
                                <i class="bi bi-star-fill" style="color: var(--salon-primary);"></i>
                                <i class="bi bi-star-fill" style="color: var(--salon-primary);"></i>
                                <i class="bi bi-star-fill" style="color: var(--salon-primary);"></i>
                                <i class="bi bi-star-fill" style="color: var(--salon-primary);"></i>
                        </div>
                            <p class="mb-4">"Absolutely love my new hair! The stylists here are true artists. They listened to exactly what I wanted and delivered beyond my expectations. The salon atmosphere is so relaxing and beautiful."</p>
                            <h5 class="fw-bold mb-0">Sarah Mitchell</h5>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="testimonial-card card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <img src="hrspa.jpg" alt="Emily Rodriguez" class="img-fluid rounded-circle mb-3" style="width: 80px; height: 80px; object-fit: cover;">
                            <div class="stars mb-3">
                                <i class="bi bi-star-fill" style="color: var(--salon-primary);"></i>
                                <i class="bi bi-star-fill" style="color: var(--salon-primary);"></i>
                                <i class="bi bi-star-fill" style="color: var(--salon-primary);"></i>
                                <i class="bi bi-star-fill" style="color: var(--salon-primary);"></i>
                                <i class="bi bi-star-fill" style="color: var(--salon-primary);"></i>
                        </div>
                            <p class="mb-4">"The spa treatments here are heavenly! I left feeling completely rejuvenated. The attention to detail and the luxurious products they use make every visit special. This is my sanctuary."</p>
                            <h5 class="fw-bold mb-0">Emily Rodriguez</h5>
                            <small class="text-muted">Spa & Facial Treatment</small>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="testimonial-card card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <img src="hrclr.jpg" alt="Jessica Chen" class="img-fluid rounded-circle mb-3" style="width: 80px; height: 80px; object-fit: cover;">
                            <div class="stars mb-3">
                                <i class="bi bi-star-fill" style="color: var(--salon-primary);"></i>
                                <i class="bi bi-star-fill" style="color: var(--salon-primary);"></i>
                                <i class="bi bi-star-fill" style="color: var(--salon-primary);"></i>
                                <i class="bi bi-star-fill" style="color: var(--salon-primary);"></i>
                                <i class="bi bi-star-fill" style="color: var(--salon-primary);"></i>
                        </div>
                            <p class="mb-4">"Best nail salon experience ever! The nail technicians are incredibly skilled and creative. My gel manicure lasted for weeks and looked flawless. The pink decor makes me feel so pampered."</p>
                            <h5 class="fw-bold mb-0">Jessica Chen</h5>
                            <small class="text-muted">Manicure & Pedicure</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Beautiful Visit Us Section -->
    <section id="contact" class="py-5">
        <div class="container py-5">
            <div class="text-center mb-5">
                <div class="d-flex align-items-center justify-content-center mb-3">
                    <i class="bi bi-geo-alt-fill me-3" style="color: var(--salon-primary);"></i>
                    <h2 class="display-4 fw-bold mb-0">Visit Us</h2>
                </div>
                <p class="lead text-muted">We'd love to welcome you to our beautiful salon. Book your appointment today and experience the Glowtime Salon difference.</p>
            </div>

            <div class="row g-5">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h4 mb-4">Contact Information</h3>
                            
                            <div class="contact-item d-flex mb-4">
                                <div class="contact-icon me-3">
                                    <i class="bi bi-geo-alt-fill" style="color: var(--salon-primary);"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1">Address</h5>
                                    <p class="text-muted mb-0">310 M. L. Quezon Ave<br>Manila, Metro Manila<br>Philippines</p>
                                </div>
                            </div>

                            <div class="contact-item d-flex mb-4">
                                <div class="contact-icon me-3">
                                    <i class="bi bi-telephone-fill" style="color: var(--salon-primary);"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1">Phone</h5>
                                    <p class="text-muted mb-0">+63 912 345 6789</p>
                                </div>
                            </div>

                            <div class="contact-item d-flex mb-4">
                                <div class="contact-icon me-3">
                                    <i class="bi bi-clock-fill" style="color: var(--salon-primary);"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1">Hours</h5>
                                    <p class="text-muted mb-1">Monday - Friday: 9:00 AM - 8:00 PM</p>
                                    <p class="text-muted mb-1">Saturday: 9:00 AM - 6:00 PM</p>
                                    <p class="text-muted mb-0">Sunday: 10:00 AM - 5:00 PM</p>
                                </div>
                            </div>

                            <a href="register.php" class="btn w-100 mt-3" style="background-color: var(--salon-primary); border-color: var(--salon-primary); color: white;">Book Your Appointment</a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="h4 mb-4">Find Us Here</h3>
                            <div class="mb-3 rounded-4 overflow-hidden" style="box-shadow: var(--salon-shadow);">
                                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3862.819812498935!2d121.05953017510417!3d14.495031085978898!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397cf6a021eaffb%3A0xf97a26b188f66cf7!2sJulz%20Beauty%20Salon!5e0!3m2!1sen!2sph!4v1760246772137!5m2!1sen!2sph" width="100%" height="300" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                            </div>
                            <p class="text-muted mb-1 fw-bold">310 M. L. Quezon Ave, Manila, Metro Manila</p>
                            <p class="text-muted">Located in the heart of Manila, easily accessible by public transport with parking available.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Beautiful Footer -->
    <footer class="py-5" style="background-color: var(--salon-light);">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h5 class="mb-3">
                        <i class="bi bi-flower1 me-2" style="color: var(--salon-primary);"></i>
                        Glowtime Salon
                    </h5>
                    <p class="text-muted">Your destination for premium beauty services in a luxurious, feminine atmosphere.</p>
                    <div class="social-links mt-3">
                        <a href="#" class="me-3" style="color: var(--salon-primary);"><i class="bi bi-facebook fs-5"></i></a>
                        <a href="#" class="me-3" style="color: var(--salon-primary);"><i class="bi bi-instagram fs-5"></i></a>
                        <a href="#" class="me-3" style="color: var(--salon-primary);"><i class="bi bi-pinterest fs-5"></i></a>
                        <a href="#" style="color: var(--salon-primary);"><i class="bi bi-tiktok fs-5"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <h5 class="mb-3">Services</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><span class="text-muted">Hair Styling</span></li>
                        <li class="mb-2"><span class="text-muted">Manicure</span></li>
                        <li class="mb-2"><span class="text-muted">Pedicure</span></li>
                        <li class="mb-2"><span class="text-muted">Spa Treatments</span></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-6">
                    <h5 class="mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#home" class="text-muted text-decoration-none">Home</a></li>
                        <li class="mb-2"><a href="#services" class="text-muted text-decoration-none">Services</a></li>
                        <li class="mb-2"><a href="#about" class="text-muted text-decoration-none">About Us</a></li>
                        <li class="mb-2"><a href="#contact" class="text-muted text-decoration-none">Contact</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4" style="border-color: var(--salon-secondary);">
            <div class="text-center text-muted">
                <p class="mb-0">&copy; <?= date('Y') ?> Glowtime Salon. All rights reserved. Made with ❤️❤️</p>
            </div>
        </div>
    </footer>

    <!-- Chatbot Popup -->
    <div id="chatbot-popup" class="chatbot-container">
        <!-- Chatbot Toggle Button -->
        <button id="chatbot-toggle" class="chatbot-toggle">
            <i class="bi bi-chat-dots"></i>
            <span class="chatbot-badge">1</span>
        </button>
        
        <!-- Chatbot Window -->
        <div id="chatbot-window" class="chatbot-window">
            <div class="chatbot-header">
                <div class="chatbot-avatar">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="chatbot-info">
                    <h6 class="mb-0">AI Beauty Assistant</h6>
                    <small class="text-muted">Online now</small>
                </div>
                <button id="chatbot-close" class="chatbot-close">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            
            <div class="chatbot-body">
                <div class="chatbot-messages" id="chatbot-messages">
                    <!-- Welcome Message -->
                    <div class="chatbot-message chatbot-ai-message">
                        <div class="message-avatar">
                            <i class="bi bi-robot"></i>
                        </div>
                        <div class="message-content">
                            <div class="message-bubble">
                                Hello! 👋 I'm your AI beauty assistant. I can help you with:
                                <ul class="mt-2 mb-0">
                                    <li>Hair style and color recommendations</li>
                                    <li>Skincare advice</li>
                                    <li>Nail care tips</li>
                                    <li>Booking appointments</li>
                                </ul>
                                What would you like to know?
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Questions -->
                <div class="chatbot-quick-questions">
                    <button class="quick-btn" data-question="What hair style would suit my face shape?">
                        Hair Style Advice
                    </button>
                    <button class="quick-btn" data-question="What hair color would look good on me?">
                        Hair Color Tips
                    </button>
                    <button class="quick-btn" data-question="What skincare routine should I follow?">
                        Skincare Help
                    </button>
                    <button class="quick-btn" data-question="How do I book an appointment?">
                        Book Appointment
                    </button>
                </div>
                
                <!-- Chat Input -->
                <div class="chatbot-input">
                    <form id="chatbot-form">
                        <div class="input-group">
                            <input type="text" id="chatbot-message" class="form-control" placeholder="Ask me anything about beauty..." required>
                            <button type="submit" class="btn btn-salon">
                                <i class="bi bi-send"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Navbar background change on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar-landing');
            if (window.scrollY > 100) {
                navbar.style.background = 'rgba(255, 255, 255, 0.98)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.95)';
            }
        });

        // Add animation to feature cards when they come into view
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animation = 'fadeInUp 0.6s ease forwards';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.feature-card, .testimonial-card').forEach(card => {
            observer.observe(card);
        });

        // Chatbot functionality
        const chatbotToggle = document.getElementById('chatbot-toggle');
        const chatbotWindow = document.getElementById('chatbot-window');
        const chatbotClose = document.getElementById('chatbot-close');
        const chatbotForm = document.getElementById('chatbot-form');
        const chatbotMessage = document.getElementById('chatbot-message');
        const chatbotMessages = document.getElementById('chatbot-messages');
        const quickButtons = document.querySelectorAll('.quick-btn');

        // Toggle chatbot window
        chatbotToggle.addEventListener('click', function() {
            chatbotWindow.classList.toggle('show');
            if (chatbotWindow.classList.contains('show')) {
                chatbotToggle.style.display = 'none';
                // Ensure input field is visible when opening
                const messageInput = document.getElementById('chatbot-message');
                const inputForm = document.getElementById('chatbot-form');
                if (messageInput) messageInput.style.display = 'block';
                if (inputForm) inputForm.style.display = 'block';
                // Focus on input field
                setTimeout(() => messageInput.focus(), 100);
            }
        });

        // Close chatbot window
        chatbotClose.addEventListener('click', function() {
            chatbotWindow.classList.remove('show');
            chatbotToggle.style.display = 'block';
        });

        // Handle quick question buttons
        quickButtons.forEach(button => {
            button.addEventListener('click', function() {
                const question = this.getAttribute('data-question');
                sendMessage(question);
            });
        });

        // Handle form submission
        chatbotForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const message = chatbotMessage.value.trim();
            if (message) {
                sendMessage(message);
                chatbotMessage.value = '';
                // Ensure input field remains visible and functional
                chatbotMessage.style.display = 'block';
                chatbotForm.style.display = 'block';
            }
        });

        // Send message function (same POST method as client_ai.php)
        async function sendMessage(message) {
            // Add user message
            addMessage(message, 'user');
            
            // Hide quick questions after first interaction
            const quickQuestions = document.querySelector('.chatbot-quick-questions');
            if (quickQuestions) {
                quickQuestions.style.display = 'none';
            }
            
            // Show typing indicator
            showTypingIndicator();
            
            try {
                // POST to index.php itself (same method as client_ai.php)
                const formData = new FormData();
                formData.append('message', message);
                formData.append('chatbot_ajax', '1');
                
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
                
                const data = await response.json();
                
                // Hide typing indicator
                hideTypingIndicator();
                
                if (data.success) {
                    addMessage(data.message, 'ai');
                } else {
                    addMessage(data.message || 'Sorry, I encountered an error. Please try again.', 'ai');
                }
                
                // Ensure input field remains visible and focused
                const messageInput = document.getElementById('chatbot-message');
                if (messageInput) {
                    messageInput.focus();
                }
            } catch (error) {
                console.error('Error:', error);
                hideTypingIndicator();
                addMessage('Sorry, I encountered an error. Please try again.', 'ai');
                
                const messageInput = document.getElementById('chatbot-message');
                if (messageInput) {
                    messageInput.focus();
                }
            }
        }

        // Add message to chat
        function addMessage(message, sender) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `chatbot-message chatbot-${sender}-message`;
            
            const avatar = document.createElement('div');
            avatar.className = 'message-avatar';
            avatar.innerHTML = sender === 'ai' ? '<i class="bi bi-robot"></i>' : '<i class="bi bi-person"></i>';
            
            const content = document.createElement('div');
            content.className = 'message-content';
            
            const bubble = document.createElement('div');
            bubble.className = 'message-bubble';
            bubble.innerHTML = parseMarkdown(message);
            
            content.appendChild(bubble);
            
            if (sender === 'ai') {
                messageDiv.appendChild(avatar);
                messageDiv.appendChild(content);
            } else {
                messageDiv.appendChild(content);
                messageDiv.appendChild(avatar);
            }
            
            chatbotMessages.appendChild(messageDiv);
            chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
        }

        // Show typing indicator
        function showTypingIndicator() {
            const typingDiv = document.createElement('div');
            typingDiv.className = 'chatbot-message chatbot-ai-message typing-indicator';
            typingDiv.innerHTML = `
                <div class="message-avatar">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="message-content">
                    <div class="message-bubble">
                        <div class="typing-dots">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                </div>
            `;
            chatbotMessages.appendChild(typingDiv);
            chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
        }

        // Hide typing indicator
        function hideTypingIndicator() {
            const typingIndicator = document.querySelector('.typing-indicator');
            if (typingIndicator) {
                typingIndicator.remove();
            }
        }

        // Simple markdown parser
        function parseMarkdown(text) {
            // Remove debug prefixes
            text = text.replace(/^\[(Quick Question - Rule-based|Rule-based Response|OpenAI Response)\] /, '');
            
            // Convert markdown to HTML
            let html = text;
            
            // Headers
            html = html.replace(/^### (.*$)/gm, '<h3>$1</h3>');
            html = html.replace(/^## (.*$)/gm, '<h2>$1</h2>');
            html = html.replace(/^# (.*$)/gm, '<h1>$1</h1>');
            
            // Bold and italic
            html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
            
            // Lists
            html = html.replace(/^[\s]*[-*+] (.+)$/gm, '<li>$1</li>');
            html = html.replace(/(<li>.*<\/li>)/gs, '<ul>$1</ul>');
            
            // Links
            html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>');
            
            // Line breaks
            html = html.replace(/\n\n/g, '</p><p>');
            html = '<p>' + html + '</p>';
            
            // Clean up empty paragraphs
            html = html.replace(/<p><\/p>/g, '');
            html = html.replace(/<p>\s*<\/p>/g, '');
            
            return html;
        }

    </script>

    <style>
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</body>
</html>
