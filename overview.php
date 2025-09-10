<?php
require_once 'inc/bootstrap.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Stats
$totalClients = pdo()->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn();
$totalAppointments = pdo()->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$totalServices = pdo()->query("SELECT COUNT(*) FROM services")->fetchColumn();

// Appointment status breakdown
$statusData = pdo()->query("SELECT status, COUNT(*) FROM appointments GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Overview</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { font-family: "Segoe UI", sans-serif; background: #faf5ff; margin:0; padding:20px; }
    h1 { color:#ec4899; }
    .stats { display:flex; gap:20px; margin-bottom:20px; }
    .stat-box { flex:1; text-align:center; background:#fce7f3; border-radius:10px; padding:20px; box-shadow:0 4px 10px rgba(0,0,0,0.1); }
    .stat-box h2 { margin:0; font-size:2em; color:#a21caf; }
    .card { background:white; padding:20px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.1); margin-bottom:20px; }
    canvas { max-width:100%; }
  </style>
</head>
<body>
  <h1>🏠 Overview</h1>

  <div class="stats">
    <div class="stat-box">
      <h2><?= $totalClients ?></h2>
      <p>Clients</p>
    </div>
    <div class="stat-box">
      <h2><?= $totalAppointments ?></h2>
      <p>Appointments</p>
    </div>
    <div class="stat-box">
      <h2><?= $totalServices ?></h2>
      <p>Services</p>
    </div>
  </div>

  <div class="card">
    <h2>Appointments by Status</h2>
    <canvas id="statusChart"></canvas>
  </div>

  <script>
    const statusCtx = document.getElementById('statusChart');
    new Chart(statusCtx, {
      type: 'doughnut',
      data: {
        labels: <?= json_encode(array_keys($statusData)) ?>,
        datasets: [{
          data: <?= json_encode(array_values($statusData)) ?>,
          backgroundColor: ['#ec4899','#34d399','#f87171']
        }]
      }
    });
  </script>
</body>
</html>
