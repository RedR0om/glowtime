<?php
require_once 'inc/bootstrap.php';
include 'inc/header_sidebar.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        try {
            // Check if email already exists
            $stmt = pdo()->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "An account with this email already exists.";
            } else {
                // Create account
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt = pdo()->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?,?,?,?, 'client')");
                $stmt->execute([$name, $email, $phone, $hashed_password]);
                
                $success = "Account created successfully! You can now sign in.";
                // Redirect after a short delay
                header("refresh:2;url=login.php");
            }
        } catch (PDOException $e) {
            $error = "Registration failed. Please try again.";
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card fade-in shadow-sm register-card">
            <div class="card-header text-center register-header">
                <h3 class="mb-0 text-white">
                    <i class="bi bi-person-plus"></i> Create Account
                </h3>
                <p class="mb-0 mt-2 text-white-50">Join Glowtime Salon today</p>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="post" id="registerForm">
                    <div class="mb-3">
                        <label for="name" class="form-label fw-bold">
                            <i class="bi bi-person text-salon"></i> Full Name *
                        </label>
                        <input type="text" class="form-control" id="name" name="name" required 
                               placeholder="Enter your full name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label fw-bold">
                            <i class="bi bi-envelope text-salon"></i> Email Address *
                        </label>
                        <input type="email" class="form-control" id="email" name="email" required 
                               placeholder="Enter your email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label fw-bold">
                            <i class="bi bi-telephone text-salon"></i> Phone Number
                            <span class="text-muted small">(Optional)</span>
                        </label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               placeholder="Enter your phone number" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label fw-bold">
                            <i class="bi bi-lock text-salon"></i> Password *
                        </label>
                        <input type="password" class="form-control" id="password" name="password" required 
                               placeholder="Create a password (min. 6 characters)" minlength="6">
                        <div class="form-text">
                            <i class="bi bi-info-circle"></i> Password must be at least 6 characters long
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label fw-bold">
                            <i class="bi bi-lock-fill text-salon"></i> Confirm Password *
                        </label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required 
                               placeholder="Confirm your password">
                        <div id="passwordMatch" class="form-text"></div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-salon btn-lg">
                            <i class="bi bi-person-plus"></i> Create Account
                        </button>
                    </div>
                </form>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <p class="mb-2 text-muted">Already have an account?</p>
                    <a href="login.php" class="btn btn-outline-salon">
                        <i class="bi bi-box-arrow-in-right"></i> Sign In
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password match validation
document.addEventListener('DOMContentLoaded', function() {
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const passwordMatch = document.getElementById('passwordMatch');
    
    function checkPasswordMatch() {
        if (confirmPassword.value === '') {
            passwordMatch.textContent = '';
            passwordMatch.className = 'form-text';
            return;
        }
        
        if (password.value === confirmPassword.value) {
            passwordMatch.innerHTML = '<i class="bi bi-check-circle text-success"></i> Passwords match';
            passwordMatch.className = 'form-text text-success';
        } else {
            passwordMatch.innerHTML = '<i class="bi bi-x-circle text-danger"></i> Passwords do not match';
            passwordMatch.className = 'form-text text-danger';
        }
    }
    
    password.addEventListener('input', checkPasswordMatch);
    confirmPassword.addEventListener('input', checkPasswordMatch);
    
    // Form validation
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        if (password.value !== confirmPassword.value) {
            e.preventDefault();
            alert('Passwords do not match. Please try again.');
            return false;
        }
        
        if (password.value.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters long.');
            return false;
        }
    });
});
</script>

<style>
/* Register Card Styling */
.register-card {
    border: none;
    border-radius: 16px;
    overflow: hidden;
    max-width: 500px;
    margin: 2rem auto;
}

.register-header {
    background: linear-gradient(135deg, var(--salon-primary, #e91e63) 0%, var(--salon-secondary, #f06292) 100%);
    padding: 2rem 1.5rem;
    border: none;
}

.register-card .card-body {
    padding: 2rem 1.5rem;
}

.register-card .form-label {
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.register-card .form-control {
    padding: 0.75rem 1rem;
    border-radius: 8px;
    border: 2px solid #e0e0e0;
    transition: all 0.3s ease;
}

.register-card .form-control:focus {
    border-color: var(--salon-primary, #e91e63);
    box-shadow: 0 0 0 0.2rem rgba(233, 30, 99, 0.15);
}

.register-card .form-text {
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

.register-card .btn-lg {
    padding: 0.75rem 2rem;
    font-size: 1rem;
    border-radius: 8px;
    font-weight: 600;
}

.register-card .btn-outline-salon {
    border-radius: 8px;
    padding: 0.5rem 1.5rem;
}

/* Mobile Responsive Styles */
@media (max-width: 768px) {
    /* Main content - Full width on mobile with minimal side padding */
    .main-content {
        width: 100% !important;
        max-width: 100% !important;
        margin-left: 0 !important;
        padding: 1rem 0.5rem !important;
        box-sizing: border-box;
    }
    
    /* Mobile header - Full width with minimal side padding */
    .mobile-header {
        margin: -1rem -0.5rem 1rem -0.5rem !important;
        padding: 1rem 0.5rem !important;
        gap: 0.5rem;
    }
    
    .container-fluid {
        width: 100% !important;
        max-width: 100% !important;
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
        margin: 0 !important;
    }
    
    /* Row - Stack columns on mobile with minimal side padding */
    .row {
        margin-left: 0 !important;
        margin-right: 0 !important;
        padding: 0 0.5rem !important;
    }
    
    .row .col-md-6,
    .row .col-lg-5 {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
    
    /* Register Card - Full width on mobile */
    .register-card {
        margin: 1rem 0 !important;
        border-radius: 12px !important;
        max-width: 100% !important;
    }
    
    .register-header {
        padding: 1.5rem 1rem !important;
    }
    
    .register-header h3 {
        font-size: 1.5rem !important;
    }
    
    .register-header p {
        font-size: 0.9rem !important;
    }
    
    .register-card .card-body {
        padding: 1.5rem 1rem !important;
    }
    
    .register-card .form-label {
        font-size: 0.9rem !important;
        margin-bottom: 0.4rem !important;
    }
    
    .register-card .form-control {
        font-size: 0.9rem !important;
        padding: 0.65rem 0.9rem !important;
    }
    
    .register-card .form-text {
        font-size: 0.8rem !important;
    }
    
    .register-card .btn-lg {
        font-size: 0.95rem !important;
        padding: 0.65rem 1.5rem !important;
    }
    
    .register-card .btn-outline-salon {
        font-size: 0.9rem !important;
        padding: 0.5rem 1.25rem !important;
        width: 100% !important;
    }
    
    .register-card hr {
        margin: 1.5rem 0 !important;
    }
    
    .register-card .text-center p {
        font-size: 0.9rem !important;
        margin-bottom: 1rem !important;
    }
    
    /* Alerts - Better spacing */
    .alert {
        font-size: 0.85rem !important;
        padding: 0.75rem 1rem !important;
        margin-bottom: 1rem !important;
    }
}

/* Tablet Responsive Styles */
@media (min-width: 769px) and (max-width: 992px) {
    .main-content {
        padding: 1rem 0.75rem !important;
    }
    
    .container-fluid {
        padding-left: 0.75rem !important;
        padding-right: 0.75rem !important;
    }
    
    .row {
        padding: 0 0.75rem !important;
    }
}
</style>

<?php include 'inc/footer_sidebar.php'; ?>
