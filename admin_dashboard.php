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
<div class="d-flex justify-content-between align-items-center mb-4">
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
                    <div class="col-md-3 mb-3">
                        <a href="admin_services.php" class="btn btn-outline-salon w-100 py-3">
                            <i class="bi bi-gear d-block fs-4 mb-2"></i>
                            Settings
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
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
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
                                                $badgeClass = match($status) {
                                                    'confirmed' => 'bg-success',
                                                    'cancelled' => 'bg-danger',
                                                    'completed' => 'bg-info',
                                                    'pending' => 'bg-warning',
                                                    default => 'bg-secondary'
                                                };
                                            ?>
                                            <span class="badge <?= $badgeClass ?> text-white">
                                                <?= ucfirst(htmlspecialchars($status)) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                                $paymentStatus = $appointment['payment_status'] ?? 'pending';
                                                $paymentBadgeClass = match($paymentStatus) {
                                                    'verified' => 'bg-success',
                                                    'rejected' => 'bg-danger',
                                                    'pending' => 'bg-warning',
                                                    default => 'bg-secondary'
                                                };
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
