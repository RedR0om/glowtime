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

// Recent appointments
$recentAppointments = pdo()->query("
    SELECT a.start_at, a.status, u.name AS client_name, s.name AS service_name
    FROM appointments a
    JOIN users u ON a.client_id = u.id
    JOIN services s ON a.service_id = s.id
    ORDER BY a.start_at DESC
    LIMIT 5
")->fetchAll();

// Pending approvals
$pendingCount = pdo()->query("SELECT COUNT(*) FROM appointments WHERE status='pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <style>
    body { font-family: "Segoe UI", sans-serif; background: #faf5ff; margin:0; padding:20px; }
    h1 { color:#ec4899; }
    .welcome { margin-bottom:20px; font-size:1.2em; color:#6b21a8; }
    .stats { display:flex; gap:20px; margin-bottom:20px; }
    .stat-box { flex:1; text-align:center; background:#fce7f3; border-radius:10px; padding:20px; box-shadow:0 4px 10px rgba(0,0,0,0.1); }
    .stat-box h2 { margin:0; font-size:2.2em; color:#a21caf; }
    .card { background:white; padding:20px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.1); margin-bottom:20px; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding:10px; border-bottom:1px solid #ddd; text-align:left; }
    th { background:#fce7f3; color:#a21caf; }
    .pending { color:#d97706; font-weight:bold; }
    .confirmed { color:#059669; font-weight:bold; }
    .cancelled { color:#dc2626; font-weight:bold; }
  </style>
</head>
<body>
  <h1>🏠 Admin Dashboard</h1>
  <p class="welcome">Welcome back, <strong><?= $_SESSION['user_id'] ? "Administrator" : "" ?></strong>! Here’s an overview of your salon today.</p>

  <!-- Quick Stats -->
  <div class="stats">
    <div class="stat-box">
      <h2><?= $totalClients ?></h2>
      <p>Clients</p>
    </div>
    <div class="stat-box">
      <h2><?= $totalAppointments ?></h2>
      <p>Total Appointments</p>
    </div>
    <div class="stat-box">
      <h2><?= $totalServices ?></h2>
      <p>Services</p>
    </div>
  </div>

  <!-- Pending Approvals -->
  <div class="card">
    <h2>🔔 Pending Approvals</h2>
    <p>You have <strong><?= $pendingCount ?></strong> appointments waiting for confirmation.</p>
    <p><a href="appointments.php" target="_parent">Go to Appointments →</a></p>
  </div>

  <!-- Recent Appointments -->
  <div class="card">
    <h2>🗂️ Recent Appointments</h2>
    <?php if ($recentAppointments): ?>
      <table>
        <tr><th>Date</th><th>Client</th><th>Service</th><th>Status</th></tr>
        <?php foreach ($recentAppointments as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['start_at']) ?></td>
            <td><?= htmlspecialchars($r['client_name']) ?></td>
            <td><?= htmlspecialchars($r['service_name']) ?></td>
            <td class="<?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars(ucfirst($r['status'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?>
      <p>No recent appointments.</p>
    <?php endif; ?>
  </div>
</body>
</html>
