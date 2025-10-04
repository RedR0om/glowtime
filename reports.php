<?php
require_once 'inc/bootstrap.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Store user name for display
$_SESSION['user_name'] = get_user_name($_SESSION['user_id']);

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

include 'inc/header_sidebar.php';
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="h3 mb-0 text-salon">
                <i class="bi bi-bar-chart me-2"></i>Reports & Analytics
            </h1>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="filter" class="form-label">Time Period:</label>
                        <select name="filter" id="filter" class="form-select" onchange="toggleCustom(this.value)">
                            <option value="all" <?= $filter==='all'?'selected':'' ?>>All Time</option>
                            <option value="week" <?= $filter==='week'?'selected':'' ?>>This Week</option>
                            <option value="month" <?= $filter==='month'?'selected':'' ?>>This Month</option>
                            <option value="custom" <?= $filter==='custom'?'selected':'' ?>>Custom Range</option>
                        </select>
                    </div>
                    <div class="col-md-2" id="customStart" style="display:<?= $filter==='custom'?'block':'none' ?>;">
                        <label class="form-label">From:</label>
                        <input type="date" name="start" class="form-control" value="<?= htmlspecialchars($_GET['start'] ?? '') ?>">
                    </div>
                    <div class="col-md-2" id="customEnd" style="display:<?= $filter==='custom'?'block':'none' ?>;">
                        <label class="form-label">To:</label>
                        <input type="date" name="end" class="form-control" value="<?= htmlspecialchars($_GET['end'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-salon">
                            <i class="bi bi-funnel"></i> Apply Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h5 class="card-title text-salon">Total Bookings</h5>
                <h2 class="text-primary"><?= $totalBookings ?></h2>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h5 class="card-title text-salon">Salon Bookings</h5>
                <h2 class="text-info"><?= $totalSalon ?></h2>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h5 class="card-title text-salon">Home Bookings</h5>
                <h2 class="text-success"><?= $totalHome ?></h2>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h5 class="card-title text-salon">Salon Revenue</h5>
                <h2 class="text-warning">₱<?= number_format($revenueSalon,2) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h5 class="card-title text-salon">Home Revenue</h5>
                <h2 class="text-danger">₱<?= number_format($revenueHome,2) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h5 class="card-title text-salon">Avg Transport</h5>
                <h2 class="text-secondary">₱<?= number_format($avgTransport,2) ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title text-salon">Bookings per Day</h5>
                <canvas id="bookingsChart" height="300"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title text-salon">Revenue per Day</h5>
                <canvas id="revenueChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    function toggleCustom(val) {
        const customStart = document.getElementById('customStart');
        const customEnd = document.getElementById('customEnd');
        if (val === 'custom') {
            customStart.style.display = 'block';
            customEnd.style.display = 'block';
        } else {
            customStart.style.display = 'none';
            customEnd.style.display = 'none';
        }
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

<?php include 'inc/footer_sidebar.php'; ?>
