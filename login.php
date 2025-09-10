<?php
require_once 'inc/bootstrap.php';
include 'inc/header.php';

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
            $error = "❌ Wrong password for this account.";
        }
    } else {
        $error = "❌ No account found with this email.";
    }
}
?>

<div class="card">
  <h2>Login</h2>
  <?php if ($error): ?>
    <p style="color:red;"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>
  <form method="post">
    <label>Email:<input type="email" name="email" required></label>
    <label>Password:<input type="password" name="password" required></label>
    <button type="submit">Login</button>
  </form>
  <p>Don’t have an account? <a href="register.php">Register here</a></p>
</div>

<?php include 'inc/footer.php'; ?>
