<?php
require_once 'inc/bootstrap.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: login.php');
    exit;
}

// Store user name for display
$_SESSION['user_name'] = get_user_name($_SESSION['user_id']);

$response = '';
$error = '';

// Handle AI request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    
    if (!empty($message)) {
        // Get user's booking history for context
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
        
        // Create context for AI
        $context = "User booking history: ";
        if ($history) {
            $historyItems = [];
            foreach ($history as $h) {
                $historyItems[] = $h['service_name'] . ($h['style'] ? " ({$h['style']})" : '');
            }
            $context .= implode(', ', $historyItems);
        } else {
            $context .= "No previous bookings";
        }
        
        // Simple rule-based responses (you can integrate with OpenAI API here)
        $response = generateAIResponse($message, $context);
    } else {
        $error = "Please enter a message.";
    }
}

function generateAIResponse($message, $context) {
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

include 'inc/header_sidebar.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 text-salon mb-0">
            <i class="bi bi-robot"></i> AI Beauty Assistant
        </h1>
        <p class="text-muted mb-0">Get personalized beauty recommendations and advice</p>
    </div>
    <div>
        <a href="client_dashboard.php" class="btn btn-outline-salon me-2">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
        <a href="client_book.php" class="btn btn-salon">
            <i class="bi bi-calendar-plus"></i> Book Service
        </a>
    </div>
</div>

<div class="row">
    <!-- Chat Interface -->
    <div class="col-lg-8">
        <div class="card chat-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-chat-dots"></i> Chat with AI Assistant
                </h5>
            </div>
            <div class="card-body">
                <!-- Chat Messages -->
                <div class="chat-container mb-4" id="chatContainer">
                    <!-- Welcome Message -->
                    <div class="chat-message ai-message">
                        <div class="message-avatar">
                            <i class="bi bi-robot"></i>
                        </div>
                        <div class="message-content">
                            <div class="message-bubble">
                                Hello <?= htmlspecialchars($_SESSION['user_name']) ?>! 👋 I'm your AI beauty assistant. I can help you with:
                                <ul class="mt-2 mb-0">
                                    <li>Hair style and color recommendations</li>
                                    <li>Skincare advice</li>
                                    <li>Nail care tips</li>
                                    <li>Booking appointments</li>
                                </ul>
                                What would you like to know today?
                            </div>
                        </div>
                    </div>
                    
                    <!-- Display conversation -->
                    <?php if (isset($_POST['message']) && !empty($_POST['message'])): ?>
                        <!-- User Message -->
                        <div class="chat-message user-message">
                            <div class="message-content">
                                <div class="message-bubble">
                                    <?= htmlspecialchars($_POST['message']) ?>
                                </div>
                            </div>
                            <div class="message-avatar">
                                <i class="bi bi-person-circle"></i>
                            </div>
                        </div>
                        
                        <!-- AI Response -->
                        <?php if ($response): ?>
                            <div class="chat-message ai-message">
                                <div class="message-avatar">
                                    <i class="bi bi-robot"></i>
                                </div>
                                <div class="message-content">
                                    <div class="message-bubble">
                                        <?= htmlspecialchars($response) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Chat Input -->
                <form method="post" class="chat-form">
                    <div class="input-group">
                        <input type="text" class="form-control" name="message" placeholder="Ask me anything about beauty and salon services..." required>
                        <button type="submit" class="btn btn-salon">
                            <i class="bi bi-send"></i> Send
                        </button>
                    </div>
                </form>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger mt-3">
                        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions & Tips -->
    <div class="col-lg-4">
        <!-- Quick Questions -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-lightning"></i> Quick Questions
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-salon btn-sm quick-question" data-question="What hair style would suit my face shape?">
                        What hair style suits me?
                    </button>
                    <button class="btn btn-outline-salon btn-sm quick-question" data-question="What hair color would look good on me?">
                        Recommend hair color
                    </button>
                    <button class="btn btn-outline-salon btn-sm quick-question" data-question="What skincare routine should I follow?">
                        Skincare advice
                    </button>
                    <button class="btn btn-outline-salon btn-sm quick-question" data-question="How do I maintain healthy nails?">
                        Nail care tips
                    </button>
                    <button class="btn btn-outline-salon btn-sm quick-question" data-question="How do I book an appointment?">
                        How to book appointment?
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Beauty Tips -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-lightbulb"></i> Daily Beauty Tips
                </h6>
            </div>
            <div class="card-body">
                <div class="tip-item mb-3">
                    <div class="tip-icon">
                        <i class="bi bi-droplet text-info"></i>
                    </div>
                    <div class="tip-content">
                        <strong>Hydration</strong>
                        <p class="small text-muted mb-0">Drink at least 8 glasses of water daily for healthy, glowing skin.</p>
                    </div>
                </div>
                
                <div class="tip-item mb-3">
                    <div class="tip-icon">
                        <i class="bi bi-sun text-warning"></i>
                    </div>
                    <div class="tip-content">
                        <strong>Sun Protection</strong>
                        <p class="small text-muted mb-0">Always use SPF 30+ sunscreen, even on cloudy days.</p>
                    </div>
                </div>
                
                <div class="tip-item mb-3">
                    <div class="tip-icon">
                        <i class="bi bi-moon text-primary"></i>
                    </div>
                    <div class="tip-content">
                        <strong>Beauty Sleep</strong>
                        <p class="small text-muted mb-0">Get 7-8 hours of sleep for natural skin regeneration.</p>
                    </div>
                </div>
                
                <div class="tip-item">
                    <div class="tip-icon">
                        <i class="bi bi-heart text-danger"></i>
                    </div>
                    <div class="tip-content">
                        <strong>Self Care</strong>
                        <p class="small text-muted mb-0">Take time for yourself - book a relaxing spa treatment!</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Services Reminder -->
        <div class="card">
            <div class="card-body text-center">
                <i class="bi bi-calendar-heart fs-1 text-salon mb-3"></i>
                <h6>Ready for a makeover?</h6>
                <p class="text-muted small">Book your next appointment and let our professionals bring your vision to life!</p>
                <a href="client_book.php" class="btn btn-salon btn-sm">
                    <i class="bi bi-calendar-plus"></i> Book Now
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.chat-card {
    height: 600px;
    display: flex;
    flex-direction: column;
}

.chat-container {
    flex: 1;
    overflow-y: auto;
    max-height: 450px;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.chat-message {
    display: flex;
    margin-bottom: 1rem;
    align-items: flex-start;
}

.chat-message.ai-message {
    justify-content: flex-start;
}

.chat-message.user-message {
    justify-content: flex-end;
}

.message-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.ai-message .message-avatar {
    background: linear-gradient(45deg, var(--salon-primary), var(--salon-primary-dark));
    color: white;
    margin-right: 0.75rem;
}

.user-message .message-avatar {
    background: #007bff;
    color: white;
    margin-left: 0.75rem;
}

.message-bubble {
    background: white;
    padding: 0.75rem 1rem;
    border-radius: 18px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    max-width: 300px;
    word-wrap: break-word;
}

.ai-message .message-bubble {
    border-bottom-left-radius: 4px;
}

.user-message .message-bubble {
    background: var(--salon-primary);
    color: white;
    border-bottom-right-radius: 4px;
}

.tip-item {
    display: flex;
    align-items: flex-start;
}

.tip-icon {
    width: 30px;
    flex-shrink: 0;
    text-align: center;
    margin-right: 0.75rem;
    margin-top: 0.25rem;
}

.tip-content {
    flex: 1;
}

.chat-form {
    margin-top: auto;
}

@media (max-width: 768px) {
    .message-bubble {
        max-width: 250px;
    }
    
    .chat-card {
        height: 500px;
    }
    
    .chat-container {
        max-height: 350px;
    }
}
</style>

<script>
// Quick question buttons
document.querySelectorAll('.quick-question').forEach(button => {
    button.addEventListener('click', function() {
        const question = this.getAttribute('data-question');
        document.querySelector('input[name="message"]').value = question;
        document.querySelector('.chat-form').submit();
    });
});

// Auto-scroll chat container to bottom
function scrollToBottom() {
    const container = document.getElementById('chatContainer');
    container.scrollTop = container.scrollHeight;
}

// Scroll to bottom when page loads
document.addEventListener('DOMContentLoaded', scrollToBottom);
</script>

<?php include 'inc/footer_sidebar.php'; ?>