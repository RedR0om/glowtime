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
<div class="d-flex justify-content-between align-items-center mb-4 page-header">
    <div>
        <h1 class="h2 text-salon mb-0">
            <i class="bi bi-bar-chart"></i> Reports & Analytics
        </h1>
        <p class="text-muted mb-0">View detailed statistics and insights</p>
    </div>
    <div>
        <a href="admin_dashboard.php" class="btn btn-outline-salon d-none d-md-inline-block">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-funnel"></i> Filter Reports</h5>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="filter" class="form-label fw-bold">
                    <i class="bi bi-calendar-range"></i> Time Period
                </label>
                <select name="filter" id="filter" class="form-select" onchange="toggleCustom(this.value)">
                    <option value="all" <?= $filter==='all'?'selected':'' ?>>All Time</option>
                    <option value="week" <?= $filter==='week'?'selected':'' ?>>This Week</option>
                    <option value="month" <?= $filter==='month'?'selected':'' ?>>This Month</option>
                    <option value="custom" <?= $filter==='custom'?'selected':'' ?>>Custom Range</option>
                </select>
            </div>
            <div class="col-md-2" id="customStart" style="display:<?= $filter==='custom'?'block':'none' ?>;">
                <label class="form-label fw-bold">
                    <i class="bi bi-calendar-date"></i> From Date
                </label>
                <input type="date" name="start" class="form-control" value="<?= htmlspecialchars($_GET['start'] ?? '') ?>">
            </div>
            <div class="col-md-2" id="customEnd" style="display:<?= $filter==='custom'?'block':'none' ?>;">
                <label class="form-label fw-bold">
                    <i class="bi bi-calendar-date"></i> To Date
                </label>
                <input type="date" name="end" class="form-control" value="<?= htmlspecialchars($_GET['end'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-salon w-100">
                    <i class="bi bi-funnel"></i> Apply Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card stats-card h-100 shadow-sm">
            <div class="card-body text-center">
                <div class="stats-icon mb-2">
                    <i class="bi bi-calendar-check text-primary"></i>
                </div>
                <h5 class="card-title text-salon mb-2">Total Bookings</h5>
                <h2 class="text-primary mb-0"><?= $totalBookings ?></h2>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card stats-card h-100 shadow-sm">
            <div class="card-body text-center">
                <div class="stats-icon mb-2">
                    <i class="bi bi-building text-info"></i>
                </div>
                <h5 class="card-title text-salon mb-2">Salon Bookings</h5>
                <h2 class="text-info mb-0"><?= $totalSalon ?></h2>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card stats-card h-100 shadow-sm">
            <div class="card-body text-center">
                <div class="stats-icon mb-2">
                    <i class="bi bi-house text-success"></i>
                </div>
                <h5 class="card-title text-salon mb-2">Home Bookings</h5>
                <h2 class="text-success mb-0"><?= $totalHome ?></h2>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card stats-card h-100 shadow-sm">
            <div class="card-body text-center">
                <div class="stats-icon mb-2">
                    <i class="bi bi-cash-coin text-warning"></i>
                </div>
                <h5 class="card-title text-salon mb-2">Salon Revenue</h5>
                <h2 class="text-warning mb-0">₱<?= number_format($revenueSalon,2) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card stats-card h-100 shadow-sm">
            <div class="card-body text-center">
                <div class="stats-icon mb-2">
                    <i class="bi bi-currency-dollar text-danger"></i>
                </div>
                <h5 class="card-title text-salon mb-2">Home Revenue</h5>
                <h2 class="text-danger mb-0">₱<?= number_format($revenueHome,2) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card stats-card h-100 shadow-sm">
            <div class="card-body text-center">
                <div class="stats-icon mb-2">
                    <i class="bi bi-truck text-secondary"></i>
                </div>
                <h5 class="card-title text-salon mb-2">Avg Transport</h5>
                <h2 class="text-secondary mb-0">₱<?= number_format($avgTransport,2) ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0 text-salon">
                    <i class="bi bi-graph-up"></i> Bookings per Day
                </h5>
            </div>
            <div class="card-body">
                <canvas id="bookingsChart" height="300"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0 text-salon">
                    <i class="bi bi-graph-up-arrow"></i> Revenue per Day
                </h5>
            </div>
            <div class="card-body">
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
                borderColor: '#e91e63',
                backgroundColor: 'rgba(233, 30, 99, 0.1)',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#e91e63',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { 
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    padding: 12,
                    titleFont: { size: 14, weight: 'bold' },
                    bodyFont: { size: 13 }
                }
            },
            scales: { 
                y: { 
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                x: {
                    grid: { display: false }
                }
            }
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
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                borderColor: '#10b981',
                borderWidth: 2,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { 
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    padding: 12,
                    titleFont: { size: 14, weight: 'bold' },
                    bodyFont: { size: 13 },
                    callbacks: {
                        label: function(context) {
                            return '₱' + context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        }
                    }
                }
            },
            scales: { 
                y: { 
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
</script>

<style>
/* Stats Card Styling */
.stats-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: none;
    border-radius: 12px;
}

.stats-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
}

.stats-icon {
    font-size: 2.5rem;
    opacity: 0.8;
}

.stats-card .card-title {
    font-size: 0.9rem;
    font-weight: 600;
}

.stats-card h2 {
    font-size: 2rem;
    font-weight: 700;
}

/* Mobile Responsive Styles */
@media (max-width: 768px) {
    /* Main content - Full width on mobile with minimal side padding - Account for fixed header */
    .main-content {
        width: 100% !important;
        max-width: 100% !important;
        margin-left: 0 !important;
        padding: calc(60px + 1rem) 0.5rem 1rem 0.5rem !important; /* Account for fixed header (60px header + 1rem spacing) */
        box-sizing: border-box;
    }
    
    /* Mobile header - Full width with minimal side padding - FIXED POSITION */
    .mobile-header {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        width: 100% !important;
        z-index: 1050 !important;
        margin: 0 !important;
        padding: 0.75rem 0.5rem !important; /* Consistent padding */
        gap: 0.5rem;
        background: var(--user-card-bg, #fff) !important;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1) !important;
        min-height: 60px !important; /* Consistent height */
        max-height: 60px !important; /* Consistent height */
        display: flex !important;
        align-items: center !important;
    }
    
    /* Mobile header buttons - Consistent sizing */
    .mobile-header .btn {
        margin: 0 !important;
        padding: 0.5rem 0.75rem !important;
        min-width: 44px !important;
        min-height: 44px !important;
        max-height: 44px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 0.875rem !important;
    }
    
    .mobile-header .dropdown-toggle {
        padding: 0.5rem 0.75rem !important;
        min-width: 44px !important;
        min-height: 44px !important;
        max-height: 44px !important;
        font-size: 0.875rem !important;
    }
    
    .container-fluid {
        width: 100% !important;
        max-width: 100% !important;
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
        margin: 0 !important;
    }
    
    /* Page Header - Smaller on mobile with minimal side padding */
    .page-header {
        padding: 0 0.5rem !important;
        margin-bottom: 1.5rem !important;
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 1rem;
    }
    
    .page-header h1 {
        font-size: 1.5rem !important;
    }
    
    .page-header p {
        font-size: 0.875rem !important;
    }
    
    .page-header .btn {
        font-size: 0.875rem !important;
        padding: 0.5rem 1rem !important;
        width: 100% !important;
        justify-content: center;
        margin: 0.25rem 0 !important;
    }
    
    /* Row - Stack columns on mobile with minimal side padding */
    .row {
        margin-left: 0 !important;
        margin-right: 0 !important;
        padding: 0 0.5rem !important;
    }
    
    /* Cards - Compact */
    .card {
        border-radius: 8px !important;
        margin-bottom: 0.75rem !important;
    }
    
    .card-header {
        padding: 0.75rem !important;
        font-size: 0.9rem !important;
    }
    
    .card-header h5 {
        font-size: 0.95rem !important;
        margin-bottom: 0 !important;
    }
    
    .card-body {
        padding: 0.75rem !important;
    }
    
    /* Filter Form - Stack on mobile */
    .card-body .row .col-md-3,
    .card-body .row .col-md-2 {
        width: 100% !important;
        max-width: 100% !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin-bottom: 0.75rem !important;
    }
    
    .card-body .form-label {
        font-size: 0.85rem !important;
        margin-bottom: 0.35rem !important;
    }
    
    .card-body .form-control,
    .card-body .form-select {
        font-size: 0.85rem !important;
        padding: 0.4rem 0.65rem !important;
    }
    
    .card-body .btn {
        font-size: 0.8rem !important;
        padding: 0.45rem 0.75rem !important;
    }
    
    /* Stats Cards - 2 columns on mobile */
    .row .col-lg-2,
    .row .col-md-4,
    .row .col-sm-6 {
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
        margin-bottom: 0.75rem !important;
    }
    
    .stats-card {
        border-radius: 8px !important;
    }
    
    .stats-icon {
        font-size: 2rem !important;
    }
    
    .stats-card .card-title {
        font-size: 0.8rem !important;
        margin-bottom: 0.5rem !important;
    }
    
    .stats-card h2 {
        font-size: 1.5rem !important;
    }
    
    /* Charts - Full width on mobile */
    .row .col-lg-6 {
        width: 100% !important;
        max-width: 100% !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin-bottom: 1rem !important;
    }
    
    .card canvas {
        max-height: 250px !important;
    }
}

/* Tablet Responsive Styles */
@media (min-width: 769px) and (max-width: 992px) {
    .main-content {
        padding: 1rem 0.75rem !important;
    }
    
    .container-fluid {
        padding-left: 0.75rem !important;
        padding-right: 0.75rem !important;
    }
    
    .page-header {
        padding: 0 0.75rem !important;
    }
    
    .row {
        padding: 0 0.75rem !important;
    }
    
    /* Stats Cards - 3 columns on tablet */
    .row .col-md-4 {
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
    }
}
</style>

<?php include 'inc/footer_sidebar.php'; ?>
