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

// Get flash messages from session (for redirects from booking)
if (isset($_SESSION['booking_success'])) {
    $success = $_SESSION['booking_success'];
    unset($_SESSION['booking_success']); // Clear after displaying
}

$bookingQr = null;
if (isset($_SESSION['booking_qr'])) {
    $bookingQr = $_SESSION['booking_qr'];
    unset($_SESSION['booking_qr']);
}

$bookingQrError = null;
if (isset($_SESSION['booking_qr_error'])) {
    $bookingQrError = $_SESSION['booking_qr_error'];
    unset($_SESSION['booking_qr_error']);
}

// Get flash messages from URL
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
<div class="d-flex justify-content-between align-items-center mb-4 page-header">
    <div>
        <h1 class="h2 text-salon mb-0">
            <i class="bi bi-clock-history"></i> My Appointments
        </h1>
        <p class="text-muted mb-0">View and manage your appointment history</p>
    </div>
    <div>
        <a href="client_dashboard.php" class="btn btn-outline-salon me-2 d-none d-md-inline-block">
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

<?php if ($bookingQr): ?>
    <div class="card mb-4">
        <div class="card-body text-center">
            <h5 class="text-salon mb-3">
                <i class="bi bi-qr-code"></i> Your Booking QR Code
            </h5>
            <div class="d-flex justify-content-center mb-3">
                <img src="<?= htmlspecialchars($bookingQr['image_url']) ?>" alt="Booking QR Code" class="img-fluid" style="max-width: 240px;">
            </div>
            <p class="text-muted mb-3">
                Screenshot or download this QR code and show it to the stylist to verify your booking easily.
            </p>
        </div>
    </div>
<?php endif; ?>

<?php if ($bookingQrError): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($bookingQrError) ?>
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
                        $statusClass = '';
                        switch ($a['status']) {
                            case 'confirmed':
                                $statusClass = 'bg-success';
                                break;
                            case 'pending':
                                $statusClass = 'bg-warning';
                                break;
                            case 'cancelled':
                                $statusClass = 'bg-danger';
                                break;
                            case 'completed':
                                $statusClass = 'bg-info';
                                break;
                            default:
                                $statusClass = 'bg-secondary';
                                break;
                        }
                        
                        // Payment status badge
                        $paymentStatus = $a['payment_status'] ?? 'pending';
                        $paymentClass = '';
                        switch ($paymentStatus) {
                            case 'verified':
                                $paymentClass = 'bg-success';
                                break;
                            case 'rejected':
                                $paymentClass = 'bg-danger';
                                break;
                            case 'pending':
                                $paymentClass = 'bg-warning';
                                break;
                            default:
                                $paymentClass = 'bg-secondary';
                                break;
                        }
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

                                    <?php if (!empty($a['qr_code_url'])): ?>
                                        <div class="mb-2">
                                            <button type="button"
                                                    class="btn btn-salon btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#qrModal<?= (int)$a['id'] ?>">
                                                <i class="bi bi-qr-code"></i> View QR Code
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                    
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

                    <?php if (!empty($a['qr_code_url'])): ?>
                        <div class="modal fade" id="qrModal<?= (int)$a['id'] ?>" tabindex="-1" aria-labelledby="qrModalLabel<?= (int)$a['id'] ?>" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="qrModalLabel<?= (int)$a['id'] ?>">
                                            <i class="bi bi-qr-code"></i> Booking QR Code
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body text-center">
                                        <div class="mb-3">
                                            <img src="<?= htmlspecialchars($a['qr_code_url']) ?>" alt="QR Code for <?= htmlspecialchars($a['booking_ref'] ?? 'appointment') ?>" class="img-fluid" style="max-width: 280px;">
                                        </div>
                                        <p class="text-muted mb-3">
                                            Screenshot or download this QR code and show it to the stylist to verify your booking easily.
                                        </p>
                                    </div>
                                    <div class="modal-footer d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                            Close
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
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

/* Mobile Responsive Styles */
@media (max-width: 768px) {
    .timeline-container::before {
        display: none;
    }
    
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
    }
    
    /* Row - Stack columns on mobile with minimal side padding */
    .row {
        margin-left: 0 !important;
        margin-right: 0 !important;
        padding: 0 0.5rem !important;
    }
    
    /* Alerts - Better spacing */
    .alert {
        margin: 0 0.5rem 1rem 0.5rem !important;
        font-size: 0.9rem;
        padding: 0.75rem 1rem !important;
    }
    
    /* Appointment Cards - Full width on mobile */
    .appointment-card {
        margin-bottom: 1rem !important;
        border-radius: 12px !important;
    }
    
    .appointment-card .card-body {
        padding: 1rem !important;
    }
    
    /* Appointment Icon - Smaller on mobile */
    .appointment-icon {
        width: 50px !important;
        height: 50px !important;
        margin-right: 0.75rem !important;
    }
    
    .appointment-icon i {
        font-size: 1.25rem !important;
    }
    
    /* Card Title - Smaller on mobile */
    .appointment-card .card-title {
        font-size: 1.1rem !important;
        margin-bottom: 0.5rem !important;
    }
    
    /* Appointment Details - Stack on mobile */
    .appointment-card .row {
        flex-direction: column !important;
    }
    
    .appointment-card .col-md-8,
    .appointment-card .col-md-4 {
        width: 100% !important;
        max-width: 100% !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
    
    .appointment-card .col-md-4 {
        margin-top: 1rem !important;
        text-align: left !important;
    }
    
    /* Badges - Smaller on mobile */
    .appointment-card .badge {
        font-size: 0.75rem !important;
        padding: 0.35rem 0.65rem !important;
        margin-right: 0.5rem !important;
        margin-bottom: 0.5rem !important;
        display: inline-block !important;
    }
    
    /* Buttons - Full width on mobile */
    .appointment-card .btn {
        width: 100% !important;
        margin-bottom: 0.5rem !important;
        font-size: 0.875rem !important;
        padding: 0.5rem 1rem !important;
    }
    
    /* QR Code Card - Better spacing */
    .card.mb-4 {
        margin: 0 0.5rem 1rem 0.5rem !important;
        border-radius: 12px !important;
    }
    
    .card.mb-4 .card-body {
        padding: 1rem !important;
    }
    
    .card.mb-4 img {
        max-width: 200px !important;
    }
    
    /* Empty State - Better spacing */
    .text-center.py-5 {
        padding: 2rem 0.5rem !important;
    }
    
    .text-center.py-5 .display-1 {
        font-size: 3rem !important;
    }
    
    .text-center.py-5 h4 {
        font-size: 1.25rem !important;
    }
    
    .text-center.py-5 p {
        font-size: 0.9rem !important;
    }
    
    .text-center.py-5 .btn-lg {
        font-size: 1rem !important;
        padding: 0.75rem 1.5rem !important;
        width: 100% !important;
        max-width: 300px !important;
    }
    
    /* Modal - Better on mobile */
    .modal-dialog {
        margin: 0.5rem !important;
    }
    
    .modal-content {
        border-radius: 12px !important;
    }
    
    .modal-body img {
        max-width: 200px !important;
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
    
    .alert {
        margin: 0 0.75rem 1rem 0.75rem !important;
    }
    
    .card.mb-4 {
        margin: 0 0.75rem 1rem 0.75rem !important;
    }
}
</style>

<?php include 'inc/footer_sidebar.php'; ?>
