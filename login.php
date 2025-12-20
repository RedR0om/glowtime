<?php
require_once 'inc/bootstrap.php';
include 'inc/header_sidebar.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = pdo()->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // ✅ Found user, now check password
        if (password_verify($password, $user['password'])) {
            // ✅ Success
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: client_dashboard.php");
            }
            exit;
        } else {
            $error = "Wrong password for this account.";
        }
    } else {
        $error = "No account found with this email.";
    }
}
?>

<style>
/* Mobile-optimized Login Page Styles */
@media (max-width: 768px) {
    /* Full width container with no side padding on mobile - Account for fixed header */
    .login-container {
        padding: calc(60px + 1rem) 0 0.5rem 0 !important; /* Account for fixed header (60px) */
        margin: 0;
        min-height: calc(100vh - 120px);
    }
    
    /* Make card full width and bigger on mobile - remove card styling */
    .login-card {
        margin: 0;
        border-radius: 0;
        box-shadow: none;
        border: none;
        background: transparent;
        width: 100%;
    }
    
    /* Remove card header background, make it cleaner */
    .login-card .card-header {
        background: transparent !important;
        border: none !important;
        padding: 2rem 1rem 1.5rem 1rem !important;
        text-align: center;
    }
    
    .login-card .card-header h3 {
        font-size: 1.75rem;
        margin-bottom: 0.5rem;
        color: var(--salon-primary, #e91e63);
        font-weight: 700;
    }
    
    .login-card .card-header p {
        font-size: 1rem;
        color: #666;
    }
    
    /* Full width card body with more padding */
    .login-card .card-body {
        padding: 1.5rem 1rem !important;
        background: white;
        border-radius: 0;
        box-shadow: none;
    }
    
    /* Larger form inputs for better touch experience */
    .login-card .form-control {
        font-size: 16px; /* Prevents zoom on iOS */
        padding: 1rem 1.25rem;
        border-radius: 12px;
        min-height: 52px; /* Better touch target */
        border: 2px solid #e0e0e0;
    }
    
    .login-card .form-control:focus {
        border-color: var(--salon-primary, #e91e63);
        box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
    }
    
    .login-card .form-label {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.75rem;
        color: #333;
    }
    
    /* Larger button for mobile */
    .login-card .btn-lg {
        padding: 1.125rem 1.5rem;
        font-size: 1.125rem;
        min-height: 56px;
        border-radius: 12px;
        font-weight: 600;
        width: 100%;
    }
    
    /* Better spacing between form elements */
    .login-card .mb-3 {
        margin-bottom: 1.5rem !important;
    }
    
    .login-card .mb-4 {
        margin-bottom: 2rem !important;
    }
    
    /* Demo credentials card - make it part of the flow */
    .demo-credentials-card {
        margin-top: 0 !important;
        border-radius: 0;
        border: none;
        box-shadow: none;
        background: white;
        border-top: 1px solid #f0f0f0;
    }
    
    .demo-credentials-card .card-body {
        padding: 1.5rem 1rem !important;
    }
    
    .demo-credentials-card h6 {
        font-size: 1rem;
        margin-bottom: 1.25rem;
        font-weight: 600;
    }
    
    .demo-credentials-card .row {
        margin: 0;
    }
    
    .demo-credentials-card .col-6 {
        padding: 0.75rem;
        border-right: 1px solid #f0f0f0;
    }
    
    .demo-credentials-card .col-6:last-child {
        border-right: none;
    }
    
    .demo-credentials-card strong {
        font-size: 0.9rem;
        display: block;
        margin-bottom: 0.5rem;
        color: #333;
    }
    
    .demo-credentials-card small {
        font-size: 0.85rem;
        display: block;
        line-height: 1.6;
        color: #666;
    }
    
    /* Better spacing for register link */
    .login-card .text-center {
        padding-top: 1rem;
    }
    
    .login-card .btn-outline-salon {
        padding: 0.875rem 1.5rem;
        font-size: 1rem;
        min-height: 48px;
        width: 100%;
        margin-top: 0.5rem;
    }
    
    /* Reduce hr margin on mobile */
    .login-card hr {
        margin: 1.5rem 0 !important;
    }
    
    /* Remove row/col constraints on mobile */
    .login-container .row {
        margin: 0;
    }
    
    .login-container .col-12 {
        padding: 0;
    }
}

/* Tablet adjustments */
@media (min-width: 769px) and (max-width: 991px) {
    .login-container {
        padding: 2rem 1rem;
    }
    
    .login-card {
        max-width: 500px;
        margin: 0 auto;
    }
}

/* Desktop - keep original card style */
@media (min-width: 992px) {
    .login-card {
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        border-radius: 20px;
    }
    
    .login-card .card-header {
        background: var(--salon-gradient-secondary, linear-gradient(45deg, #e91e63, #c2185b)) !important;
        color: white;
        border-radius: 20px 20px 0 0 !important;
    }
}

/* Ensure proper spacing on all devices */
.login-container {
    min-height: calc(100vh - 200px);
    display: flex;
    align-items: center;
    padding: 2rem 1rem;
}

@media (max-width: 768px) {
    .login-container {
        align-items: flex-start;
        padding-top: 1rem;
    }
}
</style>

<div class="login-container">
    <div class="row justify-content-center w-100">
        <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
            <div class="card fade-in login-card">
            <div class="card-header text-center">
                <h3 class="mb-0">
                    <i class="bi bi-box-arrow-in-right"></i> Welcome Back
                </h3>
                <p class="mb-0 mt-2 opacity-75">Sign in to your account</p>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="mb-3">
                        <label for="email" class="form-label">
                            <i class="bi bi-envelope"></i> Email Address
                        </label>
                        <input type="email" class="form-control" id="email" name="email" required 
                               placeholder="Enter your email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock"></i> Password
                        </label>
                        <input type="password" class="form-control" id="password" name="password" required 
                               placeholder="Enter your password">
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-salon btn-lg">
                            <i class="bi bi-box-arrow-in-right"></i> Sign In
                        </button>
                    </div>
                </form>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <p class="mb-0">Don't have an account?</p>
                    <a href="register.php" class="btn btn-outline-salon mt-2">
                        <i class="bi bi-person-plus"></i> Create Account
                    </a>
                </div>
                </div>
            </div>
        </div>
        
        <!-- Demo Credentials -->
        <div class="card mt-3 fade-in demo-credentials-card">
            <div class="card-body text-center">
                <h6 class="card-title text-salon">
                    <i class="bi bi-info-circle"></i> Demo Credentials
                </h6>
                <div class="row g-2">
                    <div class="col-6">
                        <strong>Admin:</strong>
                        <small class="text-muted d-block">admin@glowtime.com</small>
                        <small class="text-muted d-block">admin123</small>
                    </div>
                    <div class="col-6">
                        <strong>Client:</strong>
                        <small class="text-muted d-block">john@example.com</small>
                        <small class="text-muted d-block">admin123</small>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'inc/footer_sidebar.php'; ?>
