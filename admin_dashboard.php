<?php
require_once 'inc/bootstrap.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Store user name for display
$_SESSION['user_name'] = get_user_name($_SESSION['user_id']);

// Get dashboard statistics
try {
    // Total appointments
    $stmt = pdo()->prepare("SELECT COUNT(*) FROM appointments");
    $stmt->execute();
    $totalAppointments = $stmt->fetchColumn();

    // Pending appointments
    $stmt = pdo()->prepare("SELECT COUNT(*) FROM appointments WHERE status = 'pending'");
    $stmt->execute();
    $pendingAppointments = $stmt->fetchColumn();

    // Total services
    $stmt = pdo()->prepare("SELECT COUNT(*) FROM services");
    $stmt->execute();
    $totalServices = $stmt->fetchColumn();

    // Total clients
    $stmt = pdo()->prepare("SELECT COUNT(*) FROM users WHERE role = 'client'");
    $stmt->execute();
    $totalClients = $stmt->fetchColumn();

    // Recent appointments
    $stmt = pdo()->prepare("
        SELECT a.*, s.name AS service, u.name AS client
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN users u ON a.client_id = u.id
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $totalAppointments = $pendingAppointments = $totalServices = $totalClients = 0;
    $recentAppointments = [];
}

include 'inc/header_sidebar.php';
?>

<!-- Dashboard Header -->
<div class="d-flex justify-content-between align-items-center mb-4 page-header">
    <div>
        <h1 class="h2 text-salon mb-0">
            <i class="bi bi-speedometer2"></i> Admin Dashboard
        </h1>
        <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?>!</p>
    </div>
    <div>
        <span class="badge bg-success">Online</span>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card dashboard-card">
            <div class="card-icon">
                <i class="bi bi-calendar-check text-primary"></i>
            </div>
            <h5 class="card-title">Total Appointments</h5>
            <h2 class="text-primary mb-0"><?= $totalAppointments ?></h2>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card dashboard-card">
            <div class="card-icon">
                <i class="bi bi-clock text-warning"></i>
            </div>
            <h5 class="card-title">Pending Approvals</h5>
            <h2 class="text-warning mb-0"><?= $pendingAppointments ?></h2>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card dashboard-card">
            <div class="card-icon">
                <i class="bi bi-scissors text-salon"></i>
            </div>
            <h5 class="card-title">Total Services</h5>
            <h2 class="text-salon mb-0"><?= $totalServices ?></h2>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card dashboard-card">
            <div class="card-icon">
                <i class="bi bi-people text-success"></i>
            </div>
            <h5 class="card-title">Total Clients</h5>
            <h2 class="text-success mb-0"><?= $totalClients ?></h2>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-lightning"></i> Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="appointments.php" class="btn btn-salon w-100 py-3">
                            <i class="bi bi-calendar-check d-block fs-4 mb-2"></i>
                            Manage Appointments
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="admin_staff.php" class="btn btn-outline-salon w-100 py-3">
                            <i class="bi bi-person-badge d-block fs-4 mb-2"></i>
                            Manage Stylists
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="services.php" class="btn btn-outline-salon w-100 py-3">
                            <i class="bi bi-scissors d-block fs-4 mb-2"></i>
                            Manage Services
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="reports.php" class="btn btn-outline-salon w-100 py-3">
                            <i class="bi bi-bar-chart d-block fs-4 mb-2"></i>
                            View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Appointments -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <h5 class="mb-0 mb-2 mb-md-0">
                    <i class="bi bi-clock-history"></i> Recent Appointments
                </h5>
                <a href="appointments.php" class="btn btn-sm btn-outline-salon">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentAppointments)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                        No recent appointments found.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Client</th>
                                    <th>Service</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentAppointments as $appointment): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($appointment['client']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($appointment['booking_ref'] ?? '') ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($appointment['service']) ?></td>
                                        <td>
                                            <?php if ($appointment['start_at']): ?>
                                                <div><?= date('M d, Y', strtotime($appointment['start_at'])) ?></div>
                                                <small class="text-muted"><?= date('h:i A', strtotime($appointment['start_at'])) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Not scheduled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $status = $appointment['status'] ?? 'pending';
                                                $badgeClass = '';
                                                switch ($status) {
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
                                                        break;
                                                }
                                            ?>
                                            <span class="badge <?= $badgeClass ?> text-white">
                                                <?= ucfirst(htmlspecialchars($status)) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                                $paymentStatus = $appointment['payment_status'] ?? 'pending';
                                                $paymentBadgeClass = '';
                                                switch ($paymentStatus) {
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
                                                        break;
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

<style>
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
    
    .page-header .badge {
        font-size: 0.875rem !important;
        padding: 0.4rem 0.8rem !important;
    }
    
    /* Row - Stack columns on mobile with minimal side padding */
    .row {
        margin-left: 0 !important;
        margin-right: 0 !important;
        padding: 0 0.5rem !important;
    }
    
    /* Statistics Cards - Stack on mobile */
    .row .col-md-3 {
        width: 100% !important;
        max-width: 100% !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin-bottom: 1rem !important;
    }
    
    .dashboard-card {
        border-radius: 12px !important;
        padding: 1.25rem !important;
        box-shadow: var(--salon-shadow, 0 2px 8px rgba(0,0,0,0.1)) !important;
    }
    
    .dashboard-card .card-icon {
        margin-bottom: 0.75rem !important;
    }
    
    .dashboard-card .card-icon i {
        font-size: 2rem !important;
    }
    
    .dashboard-card .card-title {
        font-size: 0.9rem !important;
        margin-bottom: 0.5rem !important;
    }
    
    .dashboard-card h2 {
        font-size: 2rem !important;
    }
    
    /* Quick Actions Card */
    .card {
        border-radius: 12px !important;
        margin-bottom: 1rem !important;
    }
    
    .card-header {
        padding: 1rem !important;
        font-size: 1rem !important;
    }
    
    .card-body {
        padding: 1rem !important;
    }
    
    /* Quick Action Buttons - Stack on mobile */
    .card-body .row .col-md-3 {
        width: 100% !important;
        max-width: 100% !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin-bottom: 0.75rem !important;
    }
    
    .card-body .btn {
        font-size: 0.9rem !important;
        padding: 1rem !important;
    }
    
    .card-body .btn i {
        font-size: 1.75rem !important;
        margin-bottom: 0.5rem !important;
    }
    
    /* Recent Appointments Card */
    .card-header {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 0.75rem;
    }
    
    .card-header .btn {
        width: 100% !important;
        font-size: 0.875rem !important;
    }
    
    /* Table - Convert to cards on mobile */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .table {
        font-size: 0.85rem !important;
    }
    
    .table thead {
        display: none;
    }
    
    .table tbody tr {
        display: block;
        margin-bottom: 1rem;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 0.75rem;
        background: #fff;
    }
    
    .table tbody td {
        display: block;
        text-align: left !important;
        padding: 0.5rem 0 !important;
        border: none;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .table tbody td:last-child {
        border-bottom: none;
    }
    
    .table tbody td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #6c757d;
        display: block;
        margin-bottom: 0.25rem;
        font-size: 0.75rem;
        text-transform: uppercase;
    }
    
    .table tbody td:first-child::before {
        content: "Client";
    }
    
    .table tbody td:nth-child(2)::before {
        content: "Service";
    }
    
    .table tbody td:nth-child(3)::before {
        content: "Date & Time";
    }
    
    .table tbody td:nth-child(4)::before {
        content: "Status";
    }
    
    .table tbody td:nth-child(5)::before {
        content: "Payment";
    }
    
    .table tbody .badge {
        font-size: 0.75rem !important;
        padding: 0.35rem 0.65rem !important;
    }
    
    /* Empty State */
    .text-center.py-5 {
        padding: 2rem 0.5rem !important;
    }
    
    .text-center.py-5 i {
        font-size: 3rem !important;
    }
    
    .text-center.py-5 .text-muted {
        font-size: 0.9rem !important;
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
    
    /* Statistics Cards - 2 columns on tablet */
    .row .col-md-3 {
        width: 50% !important;
        max-width: 50% !important;
    }
    
    /* Quick Actions - 2 columns on tablet */
    .card-body .row .col-md-3 {
        width: 50% !important;
        max-width: 50% !important;
    }
}
</style>

<?php include 'inc/footer_sidebar.php'; ?>
