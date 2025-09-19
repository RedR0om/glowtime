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
