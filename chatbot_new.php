<?php
require_once 'inc/bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Store user name for display
$_SESSION['user_name'] = get_user_name($_SESSION['user_id']);

$response = '';
$error = '';

// Handle chat message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    
    if (!empty($message)) {
        $user_id = $_SESSION['user_id'];
        $response = generateChatResponse($message, $user_id);
    } else {
        $error = "Please enter a message.";
    }
}

function generateChatResponse($message, $user_id) {
    $message = strtolower($message);
    
    /* ---- Handle booking flow ---- */
    if (strpos($message, "haircut") !== false || strpos($message, "book") !== false) {
        // ✅ recommend time slots for haircut
        $service_id = 1; // assume Haircut has ID=1
        $slots = suggest_available_slots($service_id, null, 3, 5);

        if ($slots) {
            $response = "✅ Great choice! Here are some available times for haircut:\n\n";
            foreach ($slots as $s) {
                $date = date("M d", strtotime($s['start']));
                $time = date("H:i", strtotime($s['start']));
                $response .= "📅 $date at $time\n";
            }
            $response .= "\nWould you like me to help you book one of these slots? You can use our booking system for a complete appointment.";
        } else {
            $response = "❌ Sorry, no available slots in the next few days. Please try booking for a later date through our booking system.";
        }
    }
    
    /* ---- Handle style recommendations ---- */
    elseif (strpos($message, "suggest") !== false || strpos($message, "style") !== false || strpos($message, "recommend") !== false) {
        $rec = style_recommendation_for_user($user_id, false); // ✅ use rule-based recommendations

        if ($rec['source'] === 'openai') {
            $response = "✨ AI Style Suggestion:\n" . $rec['text'];
        } else {
            $response = "✨ Based on your preferences, here are some style tips:\n\n";
            foreach ($rec['items'] as $r) {
                $response .= "💡 " . $r . "\n";
            }
            $response .= "\nWould you like to book an appointment to try one of these styles?";
        }
    }
    
    /* ---- Handle service inquiries ---- */
    elseif (strpos($message, "service") !== false || strpos($message, "price") !== false) {
        try {
            $stmt = pdo()->prepare("SELECT name, price, duration_minutes FROM services ORDER BY name LIMIT 5");
            $stmt->execute();
            $services = $stmt->fetchAll();
            
            $response = "💇 Here are our popular services:\n\n";
            foreach ($services as $service) {
                $response .= "✂️ " . $service['name'] . " - ₱" . number_format($service['price'], 2) . " (" . $service['duration_minutes'] . " mins)\n";
            }
            $response .= "\nWould you like to book any of these services?";
        } catch (Exception $e) {
            $response = "I can help you with information about our salon services. Please visit our booking page to see all available services and prices.";
        }
    }
    
    /* ---- Handle greetings ---- */
    elseif (strpos($message, "hello") !== false || strpos($message, "hi") !== false || strpos($message, "help") !== false) {
        $response = "Hello! 👋 Welcome to Glowtime Salon chat support!\n\nI can help you with:\n";
        $response .= "📅 Booking appointments\n";
        $response .= "💇 Service information and prices\n";
        $response .= "✨ Style recommendations\n";
        $response .= "🕐 Available time slots\n\n";
        $response .= "What would you like to know today?";
    }
    
    /* ---- Handle appointment status ---- */
    elseif (strpos($message, "appointment") !== false || strpos($message, "booking") !== false) {
        try {
            $stmt = pdo()->prepare("SELECT COUNT(*) FROM appointments WHERE client_id = ? AND status IN ('pending', 'confirmed')");
            $stmt->execute([$user_id]);
            $activeBookings = $stmt->fetchColumn();
            
            if ($activeBookings > 0) {
                $response = "📋 You have $activeBookings active appointment(s). You can view details in your appointment history or dashboard.";
            } else {
                $response = "📅 You don't have any active appointments. Would you like to book a new appointment? I can help you find available time slots!";
            }
        } catch (Exception $e) {
            $response = "I can help you check your appointments. Please visit your dashboard or appointment history for detailed information.";
        }
    }
    
    /* ---- Default response ---- */
    else {
        $responses = [
            "I'm here to help! You can ask me about:\n• Booking appointments\n• Service prices\n• Available time slots\n• Style recommendations",
            "How can I assist you today? I can help with booking appointments, checking services, or providing beauty tips!",
            "Welcome to Glowtime chat support! Ask me about our services, booking process, or style advice.",
        ];
        $response = $responses[array_rand($responses)];
    }
    
    return $response;
}

include 'inc/header_sidebar.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 text-salon mb-0">
            <i class="bi bi-chat-dots"></i> Chat Support
        </h1>
        <p class="text-muted mb-0">Get instant help and support for your salon needs</p>
    </div>
    <div>
        <a href="<?= $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'client_dashboard.php' ?>" class="btn btn-outline-salon me-2">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
        <?php if ($_SESSION['role'] === 'client'): ?>
            <a href="client_book.php" class="btn btn-salon">
                <i class="bi bi-calendar-plus"></i> Book Service
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- Chat Interface -->
    <div class="col-lg-8">
        <div class="card chat-card">
            <div class="card-header">
                <div class="d-flex align-items-center">
                    <div class="chat-avatar me-3">
                        <i class="bi bi-headset"></i>
                    </div>
                    <div>
                        <h5 class="mb-0">Glowtime Support</h5>
                        <small class="text-muted">Online • Ready to help</small>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <!-- Chat Messages -->
                <div class="chat-container" id="chatContainer">
                    <!-- Welcome Message -->
                    <div class="chat-message support-message">
                        <div class="message-avatar">
                            <i class="bi bi-headset"></i>
                        </div>
                        <div class="message-content">
                            <div class="message-bubble">
                                <strong>Welcome to Glowtime Chat Support!</strong> 👋<br><br>
                                Hello <?= htmlspecialchars($_SESSION['user_name']) ?>! I'm here to help you with:
                                <ul class="mt-2 mb-0">
                                    <li>📅 Booking appointments</li>
                                    <li>💇 Service information and pricing</li>
                                    <li>✨ Style recommendations</li>
                                    <li>🕐 Available time slots</li>
                                    <li>❓ General questions</li>
                                </ul>
                                How can I assist you today?
                            </div>
                            <div class="message-time">
                                <?= date('h:i A') ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Display conversation -->
                    <?php if (isset($_POST['message']) && !empty($_POST['message'])): ?>
                        <!-- User Message -->
                        <div class="chat-message user-message">
                            <div class="message-content">
                                <div class="message-bubble">
                                    <?= nl2br(htmlspecialchars($_POST['message'])) ?>
                                </div>
                                <div class="message-time">
                                    <?= date('h:i A') ?>
                                </div>
                            </div>
                            <div class="message-avatar">
                                <i class="bi bi-person-circle"></i>
                            </div>
                        </div>
                        
                        <!-- Support Response -->
                        <?php if ($response): ?>
                            <div class="chat-message support-message">
                                <div class="message-avatar">
                                    <i class="bi bi-headset"></i>
                                </div>
                                <div class="message-content">
                                    <div class="message-bubble">
                                        <?= nl2br(htmlspecialchars($response)) ?>
                                    </div>
                                    <div class="message-time">
                                        <?= date('h:i A') ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Chat Input -->
                <div class="chat-input-container">
                    <form method="post" class="chat-form">
                        <div class="input-group">
                            <input type="text" class="form-control" name="message" placeholder="Type your message here..." required autocomplete="off">
                            <button type="submit" class="btn btn-salon">
                                <i class="bi bi-send"></i>
                            </button>
                        </div>
                    </form>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger mt-3 mx-3">
                        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Support Info & Quick Actions -->
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
                    <button class="btn btn-outline-salon btn-sm quick-question" data-question="What services do you offer?">
                        What services do you offer?
                    </button>
                    <button class="btn btn-outline-salon btn-sm quick-question" data-question="How do I book an appointment?">
                        How to book appointment?
                    </button>
                    <button class="btn btn-outline-salon btn-sm quick-question" data-question="What are your prices?">
                        Service prices
                    </button>
                    <button class="btn btn-outline-salon btn-sm quick-question" data-question="Do you offer home service?">
                        Home service available?
                    </button>
                    <button class="btn btn-outline-salon btn-sm quick-question" data-question="Can you suggest a hairstyle for me?">
                        Style recommendations
                    </button>
                    <button class="btn btn-outline-salon btn-sm quick-question" data-question="What are your available time slots?">
                        Available times
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Support Hours -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-clock"></i> Support Hours
                </h6>
            </div>
            <div class="card-body">
                <div class="support-hours">
                    <div class="hour-item">
                        <strong>Monday - Friday</strong>
                        <span>9:00 AM - 6:00 PM</span>
                    </div>
                    <div class="hour-item">
                        <strong>Saturday</strong>
                        <span>9:00 AM - 5:00 PM</span>
                    </div>
                    <div class="hour-item">
                        <strong>Sunday</strong>
                        <span>10:00 AM - 4:00 PM</span>
                    </div>
                </div>
                <div class="mt-3 text-center">
                    <span class="badge bg-success">
                        <i class="bi bi-circle-fill"></i> Online Now
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Contact Info -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-telephone"></i> Direct Contact
                </h6>
            </div>
            <div class="card-body">
                <div class="contact-item">
                    <i class="bi bi-telephone text-primary"></i>
                    <div>
                        <strong>Phone</strong>
                        <div class="text-muted">+63 912 345 6789</div>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="bi bi-envelope text-success"></i>
                    <div>
                        <strong>Email</strong>
                        <div class="text-muted">support@glowtime.com</div>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="bi bi-geo-alt text-danger"></i>
                    <div>
                        <strong>Location</strong>
                        <div class="text-muted">123 Beauty Street, Salon City</div>
                    </div>
                </div>
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

.chat-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(45deg, var(--salon-primary), var(--salon-primary-dark));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chat-container {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
    background: #f8f9fa;
    max-height: 450px;
}

.chat-input-container {
    padding: 1rem;
    background: white;
    border-top: 1px solid #dee2e6;
}

.chat-message {
    display: flex;
    margin-bottom: 1.5rem;
    align-items: flex-start;
}

.chat-message.support-message {
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

.support-message .message-avatar {
    background: linear-gradient(45deg, var(--salon-primary), var(--salon-primary-dark));
    color: white;
    margin-right: 0.75rem;
}

.user-message .message-avatar {
    background: #007bff;
    color: white;
    margin-left: 0.75rem;
}

.message-content {
    max-width: 70%;
}

.message-bubble {
    background: white;
    padding: 1rem;
    border-radius: 18px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    word-wrap: break-word;
    line-height: 1.5;
}

.support-message .message-bubble {
    border-bottom-left-radius: 4px;
    border: 1px solid #e9ecef;
}

.user-message .message-bubble {
    background: var(--salon-primary);
    color: white;
    border-bottom-right-radius: 4px;
}

.message-time {
    font-size: 0.75rem;
    color: #6c757d;
    margin-top: 0.25rem;
    text-align: inherit;
}

.user-message .message-time {
    text-align: right;
}

.support-hours .hour-item {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.support-hours .hour-item:last-child {
    border-bottom: none;
}

.contact-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.contact-item:last-child {
    border-bottom: none;
}

.contact-item i {
    margin-right: 0.75rem;
    width: 20px;
    text-align: center;
}

@media (max-width: 768px) {
    .message-content {
        max-width: 85%;
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
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

// Scroll to bottom when page loads and after new messages
document.addEventListener('DOMContentLoaded', function() {
    scrollToBottom();
    
    // Focus on input field
    const messageInput = document.querySelector('input[name="message"]');
    if (messageInput) {
        messageInput.focus();
    }
});

// Handle form submission with loading state
document.querySelector('.chat-form').addEventListener('submit', function() {
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i>';
    submitBtn.disabled = true;
    
    // Re-enable after a short delay (form will submit)
    setTimeout(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }, 1000);
});

// Add spinning animation for loading
const style = document.createElement('style');
style.textContent = `
    .spin {
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);
</script>

<?php include 'inc/footer_sidebar.php'; ?>
