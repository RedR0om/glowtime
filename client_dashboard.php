<?php
require_once 'inc/bootstrap.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: login.php');
    exit;
}

// Store user name for display
$_SESSION['user_name'] = get_user_name($_SESSION['user_id']);

// Get client statistics
try {
    // Total appointments
    $stmt = pdo()->prepare("SELECT COUNT(*) FROM appointments WHERE client_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $totalAppointments = $stmt->fetchColumn();

    // Upcoming appointments
    $stmt = pdo()->prepare("SELECT COUNT(*) FROM appointments WHERE client_id = ? AND start_at > NOW() AND status != 'cancelled'");
    $stmt->execute([$_SESSION['user_id']]);
    $upcomingAppointments = $stmt->fetchColumn();

    // Pending appointments
    $stmt = pdo()->prepare("SELECT COUNT(*) FROM appointments WHERE client_id = ? AND status = 'pending'");
    $stmt->execute([$_SESSION['user_id']]);
    $pendingAppointments = $stmt->fetchColumn();

    // Get recent appointments
    $stmt = pdo()->prepare("
        SELECT a.*, s.name AS service
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        WHERE a.client_id = ?
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recentAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recommended service
    $recommendedServiceId = recommend_service_for_user($_SESSION['user_id']);
    $recommendedService = null;
    if ($recommendedServiceId) {
        $stmt = pdo()->prepare("SELECT * FROM services WHERE id = ?");
        $stmt->execute([$recommendedServiceId]);
        $recommendedService = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get style recommendations
    $styleRecommendations = style_recommendation_for_user($_SESSION['user_id']);

} catch (PDOException $e) {
    $totalAppointments = $upcomingAppointments = $pendingAppointments = 0;
    $recentAppointments = [];
    $recommendedService = null;
    $styleRecommendations = ['source' => 'rules', 'items' => ['Try a new hairstyle today!']];
}

include 'inc/header_sidebar.php';
?>

<style>
/* Mobile-optimized Dashboard Styles */
@media (max-width: 768px) {
    /* Ensure full width and proper scaling */
    html {
        -webkit-text-size-adjust: 100%;
        -moz-text-size-adjust: 100%;
        -ms-text-size-adjust: 100%;
        text-size-adjust: 100%;
        width: 100%;
        overflow-x: hidden;
    }
    
    body {
        font-size: 16px;
        width: 100%;
        overflow-x: hidden;
        margin: 0;
        padding: 0;
    }
    
    /* Main content - Full width on mobile with no side padding */
    .main-content {
        width: 100% !important;
        max-width: 100% !important;
        margin-left: 0 !important;
        padding: 1rem 0 !important;
        box-sizing: border-box;
    }
    
    /* Mobile header - Full width with no side margins */
    .mobile-header {
        margin: -1rem 0 1rem 0 !important;
        padding: 1rem 1rem !important;
        gap: 0.5rem;
    }
    
    /* Mobile header buttons - Proper spacing */
    .mobile-header .btn {
        margin: 0;
        padding: 0.5rem 0.75rem;
        min-width: 44px;
        min-height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .mobile-header .dropdown {
        margin: 0;
    }
    
    .mobile-header > div {
        margin: 0;
    }
    
    .container-fluid {
        width: 100% !important;
        max-width: 100% !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin: 0 !important;
    }
    
    /* Cards - Full width with internal padding only */
    .card {
        border-radius: 0 !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
    
    /* Dashboard header - Add side padding */
    .dashboard-header {
        padding: 0 1rem !important;
    }
    
    /* Statistics cards row - Add side padding */
    .row.mb-4:first-of-type {
        padding: 0 1rem !important;
    }
    
    /* Quick Actions & Recommendations row - Add side padding */
    .row.mb-4:nth-of-type(2) {
        padding: 0 1rem !important;
    }
    
    /* Style Recommendations row - Add side padding */
    .row.mb-4:nth-of-type(3) {
        padding: 0 1rem !important;
    }
    
    /* Recent Appointments row - Add side padding */
    .row:last-of-type {
        padding: 0 1rem !important;
    }
    
    /* Dashboard Header - Stack on mobile */
    .dashboard-header {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 1rem;
        width: 100%;
    }
    
    .dashboard-header h1 {
        font-size: 1.5rem !important;
        margin-bottom: 0.25rem;
        width: 100%;
    }
    
    .dashboard-header p {
        font-size: 0.9rem;
    }
    
    .dashboard-header .btn {
        width: 100%;
        padding: 0.875rem 1.25rem;
        font-size: 1rem;
        min-height: 48px;
    }
    
    /* Statistics Cards - Full width on mobile */
    .row {
        margin-left: 0 !important;
        margin-right: 0 !important;
        width: 100%;
    }
    
    .row > [class*="col-"] {
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
    }
    
    .dashboard-card {
        margin-bottom: 1rem !important;
        padding: 1.5rem 1rem !important;
        width: 100%;
        box-sizing: border-box;
    }
    
    .dashboard-card .card-icon {
        font-size: 2.5rem !important;
        margin-bottom: 0.75rem;
    }
    
    .dashboard-card h5 {
        font-size: 0.85rem !important;
        margin-bottom: 0.5rem;
    }
    
    .dashboard-card h2 {
        font-size: 2rem !important;
    }
    
    /* Quick Actions - Stack on mobile */
    .quick-actions-card .row {
        margin: 0;
    }
    
    .quick-actions-card .col-md-6 {
        margin-bottom: 1rem;
        padding: 0 0.5rem;
        width: 100%;
        flex: 0 0 100%;
        max-width: 100%;
    }
    
    .quick-actions-card .btn {
        padding: 1.25rem 1rem !important;
        font-size: 0.95rem;
        min-height: 80px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 100%;
    }
    
    .quick-actions-card .btn i {
        font-size: 2rem !important;
        margin-bottom: 0.5rem;
    }
    
    /* Recommended Service Card - Full width on mobile */
    .recommended-card {
        margin-top: 1.5rem;
        width: 100%;
    }
    
    .col-md-4,
    .col-md-8 {
        width: 100% !important;
        flex: 0 0 100% !important;
        max-width: 100% !important;
    }
    
    /* Style Tips - Stack on mobile */
    .style-tips-card .col-md-4 {
        margin-bottom: 1rem;
        width: 100% !important;
        flex: 0 0 100% !important;
        max-width: 100% !important;
    }
    
    /* Recent Appointments Table - Make it mobile-friendly */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        width: 100%;
    }
    
    .table {
        font-size: 0.875rem;
        min-width: 600px;
        width: 100%;
    }
    
    .table th,
    .table td {
        padding: 0.75rem 0.5rem;
        white-space: nowrap;
    }
    
    .table th {
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    /* Card headers - Better mobile spacing */
    .card-header {
        padding: 1rem 1rem !important;
    }
    
    .card-header h5,
    .card-header h6 {
        font-size: 1rem !important;
    }
    
    /* Card body padding */
    .card-body {
        padding: 1rem !important;
    }
    
    /* Cards - Full width */
    .card {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
    }
    
    /* Badges - Slightly smaller on mobile */
    .badge {
        font-size: 0.75rem;
        padding: 0.35rem 0.65rem;
    }
    
    /* Buttons in cards */
    .card .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        min-height: 40px;
    }
    
    /* Remove margins on mobile */
    .mb-4 {
        margin-bottom: 1.5rem !important;
    }
    
    /* Better spacing for empty state */
    .text-center.py-5 {
        padding: 2rem 1rem !important;
    }
}

/* Prevent zoom on input focus (iOS) */
@media (max-width: 768px) {
    input[type="text"],
    input[type="email"],
    input[type="password"],
    input[type="number"],
    input[type="tel"],
    input[type="date"],
    input[type="time"],
    select,
    textarea {
        font-size: 16px !important; /* Prevents iOS zoom */
    }
}

/* Tablet adjustments */
@media (min-width: 769px) and (max-width: 991px) {
    .dashboard-card {
        padding: 1.75rem 1.25rem;
    }
    
    .quick-actions-card .btn {
        padding: 1rem 0.75rem;
        font-size: 0.9rem;
    }
}
</style>

<!-- Dashboard Header -->
<div class="d-flex justify-content-between align-items-center mb-4 dashboard-header">
    <div>
        <h1 class="h2 text-salon mb-0">
            <i class="bi bi-house"></i> My Dashboard
        </h1>
        <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Client') ?>!</p>
    </div>
    <div>
        <a href="client_book.php" class="btn btn-salon">
            <i class="bi bi-calendar-plus"></i> Book Appointment
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card dashboard-card">
            <div class="card-icon">
                <i class="bi bi-calendar-check text-primary"></i>
            </div>
            <h5 class="card-title">Total Appointments</h5>
            <h2 class="text-primary mb-0"><?= $totalAppointments ?></h2>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card dashboard-card">
            <div class="card-icon">
                <i class="bi bi-calendar-event text-success"></i>
            </div>
            <h5 class="card-title">Upcoming</h5>
            <h2 class="text-success mb-0"><?= $upcomingAppointments ?></h2>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card dashboard-card">
            <div class="card-icon">
                <i class="bi bi-clock text-warning"></i>
            </div>
            <h5 class="card-title">Pending</h5>
            <h2 class="text-warning mb-0"><?= $pendingAppointments ?></h2>
        </div>
    </div>
</div>

<!-- Quick Actions & Recommendations -->
<div class="row mb-4">
    <!-- Quick Actions -->
    <div class="col-md-8">
        <div class="card quick-actions-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-lightning"></i> Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <a href="client_book.php" class="btn btn-salon w-100 py-3">
                            <i class="bi bi-calendar-plus d-block fs-4 mb-2"></i>
                            Book New Appointment
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="client_history.php" class="btn btn-outline-salon w-100 py-3">
                            <i class="bi bi-clock-history d-block fs-4 mb-2"></i>
                            View History
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="client_ai.php" class="btn btn-outline-salon w-100 py-3">
                            <i class="bi bi-robot d-block fs-4 mb-2"></i>
                            AI Assistant
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recommended Service -->
    <div class="col-md-4">
        <div class="card recommended-card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-star"></i> Recommended for You
                </h6>
            </div>
            <div class="card-body text-center">
                <?php if ($recommendedService): ?>
                    <div class="mb-3">
                        <i class="bi bi-scissors fs-1 text-salon"></i>
                    </div>
                    <h6 class="card-title"><?= htmlspecialchars($recommendedService['name']) ?></h6>
                    <p class="text-muted small mb-3">
                        ₱<?= number_format($recommendedService['price'], 2) ?> • 
                        <?= $recommendedService['duration_minutes'] ?> mins
                    </p>
                    <a href="client_book.php?service=<?= $recommendedService['id'] ?>" class="btn btn-sm btn-salon">
                        Book Now
                    </a>
                <?php else: ?>
                    <div class="text-muted">
                        <i class="bi bi-star fs-1 d-block mb-2"></i>
                        Book your first appointment to get personalized recommendations!
                    </div>
                    <a href="client_book.php" class="btn btn-sm btn-salon">
                        Explore Services
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Style Recommendations -->
<?php if (!empty($styleRecommendations['items'])): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card style-tips-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-lightbulb"></i> Style Tips for You
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach (array_slice($styleRecommendations['items'], 0, 3) as $tip): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body text-center">
                                    <i class="bi bi-magic fs-3 text-salon mb-2"></i>
                                    <p class="card-text small"><?= htmlspecialchars($tip) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent Appointments -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-clock-history"></i> Recent Appointments
                </h5>
                <a href="client_history.php" class="btn btn-sm btn-outline-salon">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentAppointments)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                        <p>No appointments yet.</p>
                        <a href="client_book.php" class="btn btn-salon">
                            <i class="bi bi-calendar-plus"></i> Book Your First Appointment
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Service</th>
                                    <th>Date & Time</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentAppointments as $appointment): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($appointment['service']) ?></div>
                                            <?php if (!empty($appointment['style'])): ?>
                                                <small class="text-muted"><?= htmlspecialchars($appointment['style']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($appointment['start_at']): ?>
                                                <div><?= date('M d, Y', strtotime($appointment['start_at'])) ?></div>
                                                <small class="text-muted"><?= date('h:i A', strtotime($appointment['start_at'])) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Not scheduled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (($appointment['booking_type'] ?? 'salon') === 'home'): ?>
                                                <span class="badge bg-info"><i class="bi bi-house"></i> Home</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary"><i class="bi bi-building"></i> Salon</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $status = $appointment['status'] ?? 'pending';
                                                switch($status) {
                                                    case 'confirmed':
                                                        $badgeClass = 'bg-success';
                                                        break;
                                                    case 'cancelled':
                                                        $badgeClass = 'bg-danger';
                                                        break;
                                                    case 'completed':
                                                        $badgeClass = 'bg-info';
                                                        break;
                                                    case 'pending':
                                                        $badgeClass = 'bg-warning';
                                                        break;
                                                    default:
                                                        $badgeClass = 'bg-secondary';
                                                }
                                            ?>
                                            <span class="badge <?= $badgeClass ?> text-white">
                                                <?= ucfirst(htmlspecialchars($status)) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                                $paymentStatus = $appointment['payment_status'] ?? 'pending';
                                                switch($paymentStatus) {
                                                    case 'verified':
                                                        $paymentBadgeClass = 'bg-success';
                                                        break;
                                                    case 'rejected':
                                                        $paymentBadgeClass = 'bg-danger';
                                                        break;
                                                    case 'pending':
                                                        $paymentBadgeClass = 'bg-warning';
                                                        break;
                                                    default:
                                                        $paymentBadgeClass = 'bg-secondary';
                                                }
                                            ?>
                                            <span class="badge <?= $paymentBadgeClass ?> text-white">
                                                <?= ucfirst(htmlspecialchars($paymentStatus)) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'inc/footer_sidebar.php'; ?>
