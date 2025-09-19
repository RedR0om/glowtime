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

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card fade-in">
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
        
        <!-- Demo Credentials -->
        <div class="card mt-3 fade-in">
            <div class="card-body text-center">
                <h6 class="card-title text-salon">
                    <i class="bi bi-info-circle"></i> Demo Credentials
                </h6>
                <div class="row">
                    <div class="col-6">
                        <strong>Admin:</strong><br>
                        <small class="text-muted">admin@glowtime.com</small><br>
                        <small class="text-muted">admin123</small>
                    </div>
                    <div class="col-6">
                        <strong>Client:</strong><br>
                        <small class="text-muted">john@example.com</small><br>
                        <small class="text-muted">admin123</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'inc/footer_sidebar.php'; ?>
