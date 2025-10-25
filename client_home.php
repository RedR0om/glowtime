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
  <!-- Dynamic Color System -->
  <link href="css/dynamic-color-system.css" rel="stylesheet">
  <script src="js/dynamic-color-picker.js" defer></script>
  
  <style>
    body { 
      font-family: 'Inter', 'Segoe UI', sans-serif; 
      background: var(--user-bg); 
      margin:0; padding:0; 
      color: var(--user-text);
      line-height: 1.6;
    }
    h1, h2 { 
      color: var(--user-primary); 
      font-weight: 700;
      letter-spacing: -0.025em;
    }
    .hero {
      background: var(--user-primary), url('salonbnnr.jpg') no-repeat center/cover;
      height: 350px;
      display:flex; align-items:center; justify-content:center;
      color:white; text-shadow:0 2px 5px rgba(0,0,0,0.5);
      font-size:2.5em; font-weight:700;
      letter-spacing: -0.025em;
    }
    .section { padding:60px 20px; }
    .gallery { 
      display:grid; 
      grid-template-columns: repeat(auto-fit, minmax(280px,1fr)); 
      gap:30px; 
    }
    .card {
      background: var(--user-card-bg); 
      padding: 30px; 
      border-radius: 20px;
      box-shadow: var(--user-shadow);
      transition: all 0.3s ease;
      border: 1px solid rgba(0, 0, 0, 0.1);
      position: relative;
      overflow: hidden;
    }
    .card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--user-primary);
    }
    .card:hover { 
      transform: translateY(-6px); 
      box-shadow: var(--user-shadow-xl);
    }
    .card img { 
      width:100%; 
      border-radius:16px; 
      height:200px; 
      object-fit:cover; 
      transition: transform 0.3s ease;
    }
    .card:hover img {
      transform: scale(1.05);
    }
    .stats { 
      display:flex; 
      gap:30px; 
      margin-top:40px; 
    }
    .stat-box { 
      flex:1; 
      text-align:center; 
      background: var(--user-secondary); 
      border-radius:20px; 
      padding:30px; 
      box-shadow: var(--user-shadow-lg);
      color: white;
      transition: all 0.3s ease;
    }
    .stat-box:hover {
      transform: translateY(-5px);
      box-shadow: var(--user-shadow-xl);
    }
    .stat-box h2 { 
      margin:0; 
      font-size:2.5em; 
      color:white; 
      font-weight: 700;
    }
    .footer { 
      background: var(--user-primary); 
      padding:40px 20px; 
      text-align:center; 
      color: white; 
      margin-top:60px; 
      font-weight: 600;
    }
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
