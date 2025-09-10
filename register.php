<?php
require_once 'inc/bootstrap.php';
include 'inc/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $stmt = pdo()->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?,?,?,?, 'client')");
    $stmt->execute([$name,$email,$phone,$password]);
    header("Location: login.php");
    exit;
}
?>
<div class="card">
  <h2>Register</h2>
  <form method="post">
    <label>Name:<input type="text" name="name" required></label>
    <label>Email:<input type="email" name="email" required></label>
    <label>Phone:<input type="text" name="phone"></label>
    <label>Password:<input type="password" name="password" required></label>
    <button type="submit">Register</button>
  </form>
  <p>Already have an account? <a href="login.php">Login here</a></p>
</div>
<?php include 'inc/footer.php'; ?>
