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
        <div class="card fade-in">
            <div class="card-header text-center">
                <h3 class="mb-0">
                    <i class="bi bi-person-plus"></i> Create Account
                </h3>
                <p class="mb-0 mt-2 opacity-75">Join Glowtime Salon today</p>
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
                
                <form method="post">
                    <div class="mb-3">
                        <label for="name" class="form-label">
                            <i class="bi bi-person"></i> Full Name *
                        </label>
                        <input type="text" class="form-control" id="name" name="name" required 
                               placeholder="Enter your full name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">
                            <i class="bi bi-envelope"></i> Email Address *
                        </label>
                        <input type="email" class="form-control" id="email" name="email" required 
                               placeholder="Enter your email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">
                            <i class="bi bi-telephone"></i> Phone Number
                        </label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               placeholder="Enter your phone number" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock"></i> Password *
                        </label>
                        <input type="password" class="form-control" id="password" name="password" required 
                               placeholder="Create a password (min. 6 characters)">
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">
                            <i class="bi bi-lock-fill"></i> Confirm Password *
                        </label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required 
                               placeholder="Confirm your password">
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-salon btn-lg">
                            <i class="bi bi-person-plus"></i> Create Account
                        </button>
                    </div>
                </form>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <p class="mb-0">Already have an account?</p>
                    <a href="login.php" class="btn btn-outline-salon mt-2">
                        <i class="bi bi-box-arrow-in-right"></i> Sign In
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'inc/footer_sidebar.php'; ?>
