<?php
require_once 'inc/bootstrap.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: login.php');
    exit;
}

// Store user name for display
$_SESSION['user_name'] = get_user_name($_SESSION['user_id']);

$clientId = $_SESSION['user_id'];
$success = $error = "";

// Handle cancellation
if (isset($_GET['cancel'])) {
    $id = (int)$_GET['cancel'];
    $stmt = pdo()->prepare("UPDATE appointments SET status='cancelled' WHERE id=? AND client_id=? AND status='pending'");
    $result = $stmt->execute([$id, $clientId]);
    if ($result) {
        $success = "Appointment cancelled successfully.";
    } else {
        $error = "Failed to cancel appointment.";
    }
    header("Location: client_history.php" . ($success ? "?success=" . urlencode($success) : "?error=" . urlencode($error)));
    exit;
}

// Get flash messages
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// Fetch appointments with more details
$stmt = pdo()->prepare("
    SELECT a.*, s.name AS service_name, s.price, s.duration_minutes
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    WHERE a.client_id=?
    ORDER BY a.start_at DESC
");
$stmt->execute([$clientId]);
$appointments = $stmt->fetchAll();

include 'inc/header_sidebar.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 text-salon mb-0">
            <i class="bi bi-clock-history"></i> My Appointments
        </h1>
        <p class="text-muted mb-0">View and manage your appointment history</p>
    </div>
    <div>
        <a href="client_dashboard.php" class="btn btn-outline-salon me-2">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
        <a href="client_book.php" class="btn btn-salon">
            <i class="bi bi-calendar-plus"></i> Book New
        </a>
    </div>
</div>

<!-- Alerts -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Appointments Timeline -->
<div class="row">
    <div class="col-12">
        <?php if ($appointments): ?>
            <div class="timeline-container">
                <?php foreach ($appointments as $a): ?>
                    <?php
                        $dateFormatted = date("M d, Y", strtotime($a['start_at']));
                        $timeFormatted = date("h:i A", strtotime($a['start_at']));
                        $isSoon = (strtotime($a['start_at']) - time()) <= 86400 && $a['status'] === 'confirmed';
                        $isPast = strtotime($a['start_at']) < time();
                        
                        // Status badge classes
                        $statusClass = match($a['status']) {
                            'confirmed' => 'bg-success',
                            'pending' => 'bg-warning',
                            'cancelled' => 'bg-danger',
                            'completed' => 'bg-info',
                            default => 'bg-secondary'
                        };
                        
                        // Payment status badge
                        $paymentClass = match($a['payment_status'] ?? 'pending') {
                            'verified' => 'bg-success',
                            'rejected' => 'bg-danger',
                            'pending' => 'bg-warning',
                            default => 'bg-secondary'
                        };
                    ?>
                    <div class="card mb-4 appointment-card">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="appointment-icon me-3">
                                            <i class="bi bi-scissors fs-4 text-salon"></i>
                                        </div>
                                        <div>
                                            <h5 class="card-title mb-1 text-salon"><?= htmlspecialchars($a['service_name']) ?></h5>
                                            <div class="text-muted">
                                                <i class="bi bi-calendar"></i> <?= $dateFormatted ?> at <?= $timeFormatted ?>
                                                <?php if ($isSoon && !$isPast): ?>
                                                    <span class="badge bg-warning text-dark ms-2">
                                                        <i class="bi bi-clock"></i> Coming Soon!
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="appointment-details">
                                        <?php if (!empty($a['style'])): ?>
                                            <div class="mb-1">
                                                <i class="bi bi-palette text-muted"></i>
                                                <small class="text-muted">Style: <?= htmlspecialchars($a['style']) ?></small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="mb-1">
                                            <i class="bi bi-geo-alt text-muted"></i>
                                            <small class="text-muted">
                                                <?= ($a['booking_type'] ?? 'salon') === 'home' ? 'Home Service' : 'Salon Visit' ?>
                                            </small>
                                        </div>
                                        
                                        <?php if (!empty($a['booking_ref'])): ?>
                                            <div class="mb-1">
                                                <i class="bi bi-tag text-muted"></i>
                                                <small class="text-muted">Ref: <?= htmlspecialchars($a['booking_ref']) ?></small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="mb-2">
                                            <i class="bi bi-cash text-muted"></i>
                                            <small class="text-muted">
                                                Down Payment: ₱<?= number_format($a['down_payment'] ?? 0, 2) ?>
                                                <?php if (($a['transport_fee'] ?? 0) > 0): ?>
                                                    + ₱<?= number_format($a['transport_fee'], 2) ?> transport
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 text-md-end">
                                    <div class="mb-2">
                                        <span class="badge <?= $statusClass ?> text-white">
                                            <?= ucfirst($a['status']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <span class="badge <?= $paymentClass ?> text-white">
                                            Payment: <?= ucfirst($a['payment_status'] ?? 'pending') ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($a['status'] === 'pending' && !$isPast): ?>
                                        <div class="mt-2">
                                            <a href="?cancel=<?= $a['id'] ?>" 
                                               class="btn btn-outline-danger btn-sm"
                                               onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <div class="mb-4">
                    <i class="bi bi-calendar-x display-1 text-muted"></i>
                </div>
                <h4 class="text-muted">No Appointments Yet</h4>
                <p class="text-muted mb-4">You haven't booked any appointments. Start your beauty journey today!</p>
                <a href="client_book.php" class="btn btn-salon btn-lg">
                    <i class="bi bi-calendar-plus"></i> Book Your First Appointment
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.appointment-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: none;
    box-shadow: var(--salon-shadow);
}

.appointment-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--salon-shadow-hover);
}

.appointment-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--salon-light);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--salon-primary);
}

.timeline-container {
    position: relative;
}

.timeline-container::before {
    content: '';
    position: absolute;
    left: 30px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--salon-primary);
    opacity: 0.3;
}

@media (max-width: 768px) {
    .timeline-container::before {
        display: none;
    }
}
</style>

<?php include 'inc/footer_sidebar.php'; ?>
