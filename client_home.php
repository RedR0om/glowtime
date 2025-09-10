<?php
require_once 'inc/bootstrap.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: login.php');
    exit;
}

$clientId = $_SESSION['user_id'];
$clientName = pdo()->prepare("SELECT name FROM users WHERE id=?");
$clientName->execute([$clientId]);
$clientName = $clientName->fetchColumn();

// Stats
$totalBookings = pdo()->prepare("SELECT COUNT(*) FROM appointments WHERE client_id=?");
$totalBookings->execute([$clientId]);
$totalBookings = $totalBookings->fetchColumn();

$upcoming = pdo()->prepare("SELECT COUNT(*) FROM appointments WHERE client_id=? AND start_at > NOW() AND status='confirmed'");
$upcoming->execute([$clientId]);
$upcoming = $upcoming->fetchColumn();

$completed = pdo()->prepare("SELECT COUNT(*) FROM appointments WHERE client_id=? AND end_at < NOW() AND status='confirmed'");
$completed->execute([$clientId]);
$completed = $completed->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Welcome to Glowtime</title>
  <style>
    body { font-family: "Segoe UI", sans-serif; background: #faf5ff; margin:0; padding:0; }
    h1, h2 { color:#ec4899; }
    .hero {
      background: url('salonbnnr.jpg') no-repeat center/cover;
      height: 300px;
      display:flex; align-items:center; justify-content:center;
      color:white; text-shadow:0 2px 5px rgba(0,0,0,0.5);
      font-size:2em; font-weight:bold;
    }
    .section { padding:40px 20px; }
    .gallery { display:grid; grid-template-columns: repeat(auto-fit, minmax(250px,1fr)); gap:20px; }
    .card {
      background:white; padding:20px; border-radius:12px;
      box-shadow:0 4px 10px rgba(0,0,0,0.1);
      transition: transform 0.3s;
    }
    .card:hover { transform: scale(1.05); }
    .card img { width:100%; border-radius:10px; height:180px; object-fit:cover; }
    .stats { display:flex; gap:20px; margin-top:30px; }
    .stat-box { flex:1; text-align:center; background:#fce7f3; border-radius:10px; padding:20px; box-shadow:0 4px 10px rgba(0,0,0,0.1); }
    .stat-box h2 { margin:0; font-size:2em; color:#a21caf; }
    .footer { background:#fce7f3; padding:20px; text-align:center; color:#6b21a8; margin-top:40px; }
  </style>
</head>
<body>
  <!-- Hero Section -->
  <div class="hero">
    🌸 Welcome, <?= htmlspecialchars($clientName) ?>, to Glowtime Salon!
  </div>

  <!-- Services Showcase -->
  <div class="section">
    <h2>💇 Our Services</h2>
    <div class="gallery">
      <div class="card">
        <img src="hrcut.jpg" alt="Haircut">
        <h3>Haircut</h3>
        <p>Fresh and stylish haircuts tailored to your look. ₱250</p>
      </div>
      <div class="card">
        <img src="hrclr.jpg" alt="Hair Color">
        <h3>Hair Color</h3>
        <p>Vibrant colors to match your style. ₱1800</p>
      </div>
      <div class="card">
        <img src="hrspa.jpg" alt="Hair Spa">
        <h3>Hair Spa</h3>
        <p>Relax and rejuvenate with a nourishing spa. ₱1200</p>
      </div>
    </div>
  </div>

  <!-- Promotions -->
  <div class="section">
    <h2>🌟 Promotions</h2>
    <div class="card">
      <p>✨ Book a Haircut + Hair Spa together and get <strong>10% OFF</strong> this month!</p>
    </div>
  </div>

  <!-- Client Activity -->
  <div class="section">
    <h2>📊 My Activity</h2>
    <div class="stats">
      <div class="stat-box">
        <h2><?= $totalBookings ?></h2>
        <p>Total Bookings</p>
      </div>
      <div class="stat-box">
        <h2><?= $upcoming ?></h2>
        <p>Upcoming</p>
      </div>
      <div class="stat-box">
        <h2><?= $completed ?></h2>
        <p>Completed</p>
      </div>
    </div>
  </div>

  <div class="footer">
    🌸 Glowtime Salon – Beauty, Style & Care 🌸
  </div>
</body>
</html>
