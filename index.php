<?php
session_start();

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
    
    <!-- Landing Page Specific Styles -->
    <style>
        .hero-section {
            background: linear-gradient(135deg, var(--salon-primary), var(--salon-primary-dark));
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('salonbnnr.jpg') center/cover no-repeat;
            opacity: 0.1;
            z-index: 1;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero-title {
            font-size: 4rem;
            font-weight: bold;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .hero-subtitle {
            font-size: 1.5rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            box-shadow: var(--salon-shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--salon-shadow-hover);
        }
        
        .feature-icon {
            font-size: 3rem;
            color: var(--salon-primary);
            margin-bottom: 1rem;
        }
        
        .cta-section {
            background: var(--salon-light);
            padding: 5rem 0;
        }
        
        .testimonial-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: var(--salon-shadow);
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .testimonial-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            background: var(--salon-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }
        
        .floating-elements {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
        }
        
        .floating-elements .element {
            position: absolute;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }
        
        .floating-elements .element:nth-child(1) {
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .floating-elements .element:nth-child(2) {
            top: 60%;
            right: 10%;
            animation-delay: 2s;
        }
        
        .floating-elements .element:nth-child(3) {
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .navbar-landing {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
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
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.2rem;
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
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light navbar-landing fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold text-salon" href="#">
                <i class="bi bi-flower1"></i> Glowtime Salon
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
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
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                    <li class="nav-item ms-2">
                        <a href="login.php" class="btn btn-outline-salon btn-sm">Login</a>
                    </li>
                    <li class="nav-item ms-2">
                        <a href="register.php" class="btn btn-salon btn-sm">Get Started</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="floating-elements">
            <i class="bi bi-scissors element" style="font-size: 4rem;"></i>
            <i class="bi bi-flower1 element" style="font-size: 3rem;"></i>
            <i class="bi bi-heart element" style="font-size: 3.5rem;"></i>
        </div>
        
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content">
                    <h1 class="hero-title">
                        Welcome to<br>
                        <span style="color: #fff;">Glowtime</span>
                    </h1>
                    <p class="hero-subtitle">
                        Your Beauty, Our Passion.<br>
                        Experience professional salon services with our modern booking system.
                    </p>
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="register.php" class="btn btn-glow btn-lg">
                            <i class="bi bi-calendar-plus"></i> Book Appointment
                        </a>
                        <a href="login.php" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-box-arrow-in-right"></i> Client Login
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <div class="hero-image">
                        <i class="bi bi-flower1" style="font-size: 15rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-4 fw-bold text-salon">Our Services</h2>
                <p class="lead text-muted">Professional salon services tailored to your needs</p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <i class="bi bi-scissors feature-icon"></i>
                        <h4>Hair Styling</h4>
                        <p class="text-muted">Professional cuts, styling, and treatments for all hair types</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <i class="bi bi-palette feature-icon"></i>
                        <h4>Hair Coloring</h4>
                        <p class="text-muted">Expert color services including highlights, balayage, and full color</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <i class="bi bi-droplet feature-icon"></i>
                        <h4>Hair Spa</h4>
                        <p class="text-muted">Relaxing treatments to nourish and revitalize your hair</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <i class="bi bi-hand-index feature-icon"></i>
                        <h4>Nail Care</h4>
                        <p class="text-muted">Manicures, pedicures, and nail art by skilled professionals</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-4 fw-bold text-salon">Why Choose Glowtime?</h2>
                <p class="lead text-muted">Modern salon management with exceptional service</p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <i class="bi bi-calendar-check feature-icon"></i>
                        <h4>Easy Booking</h4>
                        <p class="text-muted">Book appointments online 24/7 with our user-friendly system</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <i class="bi bi-house feature-icon"></i>
                        <h4>Home Service</h4>
                        <p class="text-muted">Enjoy salon services in the comfort of your own home</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <i class="bi bi-robot feature-icon"></i>
                        <h4>AI Assistant</h4>
                        <p class="text-muted">Get personalized style recommendations from our AI assistant</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section id="about" class="cta-section">
        <div class="container text-center">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h2 class="display-4 fw-bold text-salon mb-4">Ready to Glow?</h2>
                    <p class="lead text-muted mb-4">
                        Join thousands of satisfied customers who trust Glowtime for their beauty needs. 
                        Book your appointment today and experience the difference.
                    </p>
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <a href="register.php" class="btn btn-glow btn-lg">
                            <i class="bi bi-person-plus"></i> Create Account
                        </a>
                        <a href="login.php" class="btn btn-outline-salon btn-lg">
                            <i class="bi bi-box-arrow-in-right"></i> Sign In
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-4 fw-bold text-salon">What Our Clients Say</h2>
                <p class="lead text-muted">Real experiences from real customers</p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="testimonial-card">
                        <div class="testimonial-avatar">
                            <i class="bi bi-person"></i>
                        </div>
                        <h5>Maria Santos</h5>
                        <p class="text-muted">"Amazing service! The online booking system is so convenient and the stylists are incredibly skilled."</p>
                        <div class="text-warning">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="testimonial-card">
                        <div class="testimonial-avatar">
                            <i class="bi bi-person"></i>
                        </div>
                        <h5>Jennifer Cruz</h5>
                        <p class="text-muted">"Love the home service option! Professional quality in the comfort of my own home."</p>
                        <div class="text-warning">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="testimonial-card">
                        <div class="testimonial-avatar">
                            <i class="bi bi-person"></i>
                        </div>
                        <h5>Anna Rodriguez</h5>
                        <p class="text-muted">"The AI recommendations helped me find the perfect hairstyle. Glowtime is the future of beauty!"</p>
                        <div class="text-warning">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact" class="bg-dark text-light py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h5><i class="bi bi-flower1"></i> Glowtime Salon</h5>
                    <p class="text-muted">Your beauty, our passion. Experience professional salon services with modern convenience.</p>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-light"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="text-light"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="text-light"><i class="bi bi-twitter"></i></a>
                    </div>
                </div>
                <div class="col-lg-2">
                    <h6>Quick Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="#home" class="text-muted">Home</a></li>
                        <li><a href="#services" class="text-muted">Services</a></li>
                        <li><a href="#about" class="text-muted">About</a></li>
                        <li><a href="login.php" class="text-muted">Login</a></li>
                    </ul>
                </div>
                <div class="col-lg-3">
                    <h6>Services</h6>
                    <ul class="list-unstyled">
                        <li><span class="text-muted">Hair Styling</span></li>
                        <li><span class="text-muted">Hair Coloring</span></li>
                        <li><span class="text-muted">Hair Spa</span></li>
                        <li><span class="text-muted">Nail Care</span></li>
                    </ul>
                </div>
                <div class="col-lg-3">
                    <h6>Contact Info</h6>
                    <ul class="list-unstyled">
                        <li class="text-muted"><i class="bi bi-geo-alt"></i> 123 Beauty Street, Salon City</li>
                        <li class="text-muted"><i class="bi bi-telephone"></i> +63 912 345 6789</li>
                        <li class="text-muted"><i class="bi bi-envelope"></i> info@glowtime.com</li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p class="mb-0 text-muted">&copy; <?= date('Y') ?> Glowtime Salon Management System. All rights reserved.</p>
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
            }
        });

        // Send message function
        function sendMessage(message) {
            // Add user message
            addMessage(message, 'user');
            
            // Show typing indicator
            showTypingIndicator();
            
            // Simulate AI response (you can replace this with actual API call)
            setTimeout(() => {
                hideTypingIndicator();
                const response = getAIResponse(message);
                addMessage(response, 'ai');
            }, 1000);
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

        // Get AI response (simplified version)
        function getAIResponse(message) {
            const responses = {
                'hair': [
                    "## Hair Style Consultation\n\nFor the perfect hairstyle, I'd recommend consulting with one of our **professional stylists** who can assess:\n\n- Your face shape\n- Hair texture and type\n- Lifestyle preferences\n\nWe offer **complimentary consultations** to help you find the ideal look!"
                ],
                'color': [
                    "## Hair Color Consultation\n\nOur **color specialists** can help you find the perfect shade! We offer complimentary color consultations where we'll analyze:\n\n- Your skin tone and undertones\n- Eye color and natural features\n- Natural hair color and texture\n\nWe'll recommend the most **flattering options** for your unique features!"
                ],
                'skin': [
                    "## Personalized Skincare Consultation\n\nFor personalized skincare advice, I recommend booking a consultation with our **skincare specialist**. They can:\n\n- Analyze your skin type and concerns\n- Create a **customized routine** just for you\n- Recommend professional treatments\n\nBook your consultation today for **healthy, glowing skin**!"
                ],
                'nail': [
                    "## Beautiful Nail Care\n\nFor **healthy, beautiful nails**, I recommend:\n\n- Regular manicures and pedicures\n- **Nail strengthening treatments**\n- Gel polish options for long-lasting results\n\nOur nail specialists can help you achieve the perfect look!"
                ],
                'book': [
                    "I'd be happy to help you book an appointment! You can use our online booking system to choose your preferred service, date, and time. Would you like me to guide you through the process?"
                ]
            };

            const messageLower = message.toLowerCase();
            
            if (messageLower.includes('hair') && (messageLower.includes('style') || messageLower.includes('suit'))) {
                return responses.hair[0];
            } else if (messageLower.includes('hair') && messageLower.includes('color')) {
                return responses.color[0];
            } else if (messageLower.includes('skin')) {
                return responses.skin[0];
            } else if (messageLower.includes('nail')) {
                return responses.nail[0];
            } else if (messageLower.includes('book')) {
                return responses.book[0];
            } else {
                return "That's a great question! I'd be happy to help you with personalized beauty advice. For the best recommendations, I suggest booking a consultation with one of our professional stylists who can assess your specific needs.";
            }
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
