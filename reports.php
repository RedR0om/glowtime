<?php
require_once 'inc/bootstrap.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// --- Handle Date Filters ---
$filter = $_GET['filter'] ?? 'all';
$startDate = null;
$endDate = null;

if ($filter === 'week') {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d', strtotime('sunday this week'));
} elseif ($filter === 'month') {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
} elseif ($filter === 'custom' && !empty($_GET['start']) && !empty($_GET['end'])) {
    $startDate = $_GET['start'];
    $endDate = $_GET['end'];
}

// SQL condition
$dateCondition = '';
$params = [];
if ($startDate && $endDate) {
    $dateCondition = "WHERE DATE(start_at) BETWEEN :start AND :end";
    $params[':start'] = $startDate;
    $params[':end'] = $endDate;
}

// --- Stats ---
$stmt = pdo()->prepare("SELECT COUNT(*) FROM appointments $dateCondition");
$stmt->execute($params);
$totalBookings = $stmt->fetchColumn();

$stmt = pdo()->prepare("SELECT COUNT(*) FROM appointments WHERE booking_type='salon' " . ($dateCondition ? "AND DATE(start_at) BETWEEN :start AND :end" : ""));
$stmt->execute($params);
$totalSalon = $stmt->fetchColumn();

$stmt = pdo()->prepare("SELECT COUNT(*) FROM appointments WHERE booking_type='home' " . ($dateCondition ? "AND DATE(start_at) BETWEEN :start AND :end" : ""));
$stmt->execute($params);
$totalHome = $stmt->fetchColumn();

$stmt = pdo()->prepare("SELECT SUM(down_payment) FROM appointments WHERE booking_type='salon' AND payment_status='verified' " . ($dateCondition ? "AND DATE(start_at) BETWEEN :start AND :end" : ""));
$stmt->execute($params);
$revenueSalon = $stmt->fetchColumn() ?? 0;

$stmt = pdo()->prepare("SELECT SUM(down_payment) FROM appointments WHERE booking_type='home' AND payment_status='verified' " . ($dateCondition ? "AND DATE(start_at) BETWEEN :start AND :end" : ""));
$stmt->execute($params);
$revenueHome = $stmt->fetchColumn() ?? 0;

$stmt = pdo()->prepare("SELECT AVG(transport_fee) FROM appointments WHERE booking_type='home' " . ($dateCondition ? "AND DATE(start_at) BETWEEN :start AND :end" : ""));
$stmt->execute($params);
$avgTransport = $stmt->fetchColumn() ?? 0;

// --- Chart Data: Bookings per Day ---
$stmt = pdo()->prepare("SELECT DATE(start_at) as d, COUNT(*) as c 
                        FROM appointments 
                        " . ($dateCondition ? "WHERE DATE(start_at) BETWEEN :start AND :end" : "") . "
                        GROUP BY DATE(start_at) 
                        ORDER BY d");
$stmt->execute($params);
$bookingsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Chart Data: Revenue per Day ---
$stmt = pdo()->prepare("SELECT DATE(start_at) as d, SUM(down_payment) as r 
                        FROM appointments 
                        WHERE payment_status='verified' 
                        " . ($dateCondition ? "AND DATE(start_at) BETWEEN :start AND :end" : "") . "
                        GROUP BY DATE(start_at) 
                        ORDER BY d");
$stmt->execute($params);
$revenueData = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin - Reports</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { font-family:"Segoe UI",sans-serif; background:#faf5ff; margin:0; padding:20px; }
    h1 { text-align:center; color:#a21caf; margin-bottom:25px; }
    .filters { text-align:center; margin-bottom:20px; }
    .filters form { display:inline-block; background:white; padding:10px 15px; border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,0.1); }
    .filters label, .filters select, .filters input, .filters button { margin:5px; }
    .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:20px; margin-bottom:30px; }
    .card { background:white; padding:20px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1); text-align:center; }
    .card h2 { margin:0; font-size:18px; color:#6b21a8; }
    .card p { font-size:24px; font-weight:bold; margin-top:10px; }
    canvas { background:white; border-radius:12px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,0.1); margin-bottom:20px; }
  </style>
</head>
<body>
  <h1>📊 Reports & Analytics</h1>

  <!-- Filters -->
  <div class="filters">
    <form method="get">
      <label for="filter">Filter:</label>
      <select name="filter" id="filter" onchange="toggleCustom(this.value)">
        <option value="all" <?= $filter==='all'?'selected':'' ?>>All Time</option>
        <option value="week" <?= $filter==='week'?'selected':'' ?>>This Week</option>
        <option value="month" <?= $filter==='month'?'selected':'' ?>>This Month</option>
        <option value="custom" <?= $filter==='custom'?'selected':'' ?>>Custom</option>
      </select>

      <span id="customRange" style="display:<?= $filter==='custom'?'inline':'none' ?>;">
        <label>From:</label><input type="date" name="start" value="<?= htmlspecialchars($_GET['start'] ?? '') ?>">
        <label>To:</label><input type="date" name="end" value="<?= htmlspecialchars($_GET['end'] ?? '') ?>">
      </span>
      <button type="submit">Apply</button>
    </form>
  </div>

  <div class="grid">
    <div class="card">
      <h2>Total Bookings</h2>
      <p><?= $totalBookings ?></p>
    </div>
    <div class="card">
      <h2>Salon Bookings</h2>
      <p><?= $totalSalon ?></p>
    </div>
    <div class="card">
      <h2>Home Service Bookings</h2>
      <p><?= $totalHome ?></p>
    </div>
    <div class="card">
      <h2>Revenue (Salon)</h2>
      <p>₱<?= number_format($revenueSalon,2) ?></p>
    </div>
    <div class="card">
      <h2>Revenue (Home)</h2>
      <p>₱<?= number_format($revenueHome,2) ?></p>
    </div>
    <div class="card">
      <h2>Avg. Transport Fee (Home)</h2>
      <p>₱<?= number_format($avgTransport,2) ?></p>
    </div>
  </div>

  <!-- Charts -->
  <canvas id="bookingsChart"></canvas>
  <canvas id="revenueChart"></canvas>

  <script>
    function toggleCustom(val) {
      document.getElementById('customRange').style.display = (val === 'custom') ? 'inline' : 'none';
    }

    // Bookings per day chart
    const bookingsLabels = <?= json_encode(array_column($bookingsData, 'd')) ?>;
    const bookingsValues = <?= json_encode(array_column($bookingsData, 'c')) ?>;

    new Chart(document.getElementById('bookingsChart'), {
      type: 'line',
      data: {
        labels: bookingsLabels,
        datasets: [{
          label: 'Bookings',
          data: bookingsValues,
          borderColor: '#6366f1',
          backgroundColor: '#6366f1',
          tension: 0.3,
          fill: false
        }]
      },
      options: {
        plugins: { title: { display:true, text:'Bookings per Day' } },
        scales: { y: { beginAtZero:true } }
      }
    });

    // Revenue per day chart
    const revenueLabels = <?= json_encode(array_column($revenueData, 'd')) ?>;
    const revenueValues = <?= json_encode(array_column($revenueData, 'r')) ?>;

    new Chart(document.getElementById('revenueChart'), {
      type: 'bar',
      data: {
        labels: revenueLabels,
        datasets: [{
          label: 'Revenue (₱)',
          data: revenueValues,
          backgroundColor: '#10b981'
        }]
      },
      options: {
        plugins: { title: { display:true, text:'Revenue per Day' } },
        scales: { y: { beginAtZero:true } }
      }
    });
  </script>
</body>
</html>
