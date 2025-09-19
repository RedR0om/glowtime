<?php
require_once 'inc/bootstrap.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Store user name for display
$_SESSION['user_name'] = get_user_name($_SESSION['user_id']);

// --- Initialize
$appointments = [];
$success = '';
$error = '';

// Simple HTML-safe getter
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/**
 * Simple email sender (silent on failure)
 */
function sendEmail($to, $subject, $message) {
    $headers = "From: Glowtime Salon <noreply@glowtime.com>\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    @mail($to, $subject, $message, $headers);
}

// --- Handle POST actions (Verify / Reject) using PRG to avoid double-submits
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $action = $_POST['action'] ?? '';

    if ($id <= 0 || !in_array($action, ['verify', 'reject'], true)) {
        header('Location: appointments.php?error=' . urlencode('Invalid request.'));
        exit;
    }

    try {
        if ($action === 'verify') {
            $stmt = pdo()->prepare("UPDATE appointments SET payment_status = 'verified', status = 'confirmed' WHERE id = ?");
            $stmt->execute([$id]);

            // fetch client info for email
            $stmt2 = pdo()->prepare("SELECT u.email, u.name, a.booking_ref FROM appointments a JOIN users u ON a.client_id = u.id WHERE a.id = ?");
            $stmt2->execute([$id]);
            $row = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                sendEmail(
                    $row['email'],
                    "Your Booking Confirmed - Glowtime",
                    "<p>Hello <strong>" . h($row['name']) . "</strong>,</p>
                     <p>Your booking (<strong>" . h($row['booking_ref']) . "</strong>) has been <b style='color:green'>confirmed</b>.</p>
                     <p>✨ Thank you for choosing Glowtime Salon!</p>"
                );
            }

            header('Location: appointments.php?success=' . urlencode('Payment verified and booking confirmed.'));
            exit;
        }

        if ($action === 'reject') {
            $stmt = pdo()->prepare("UPDATE appointments SET payment_status = 'rejected', status = 'cancelled' WHERE id = ?");
            $stmt->execute([$id]);

            $stmt2 = pdo()->prepare("SELECT u.email, u.name, a.booking_ref FROM appointments a JOIN users u ON a.client_id = u.id WHERE a.id = ?");
            $stmt2->execute([$id]);
            $row = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                sendEmail(
                    $row['email'],
                    "Booking Cancelled - Glowtime",
                    "<p>Hello <strong>" . h($row['name']) . "</strong>,</p>
                     <p>Your booking (<strong>" . h($row['booking_ref']) . "</strong>) has been <b style='color:red'>rejected</b> due to invalid payment proof.</p>
                     <p>⚠️ If you believe this was a mistake, please contact support.</p>"
                );
            }

            header('Location: appointments.php?success=' . urlencode('Payment rejected and booking cancelled.'));
            exit;
        }
    } catch (PDOException $e) {
        header('Location: appointments.php?error=' . urlencode('Database error: ' . $e->getMessage()));
        exit;
    }
}

// Get flash messages from GET (after redirect)
if (!empty($_GET['success'])) {
    $success = h($_GET['success']);
}
if (!empty($_GET['error'])) {
    $error = h($_GET['error']);
}

// --- Fetch appointments (safe)
try {
    $stmt = pdo()->prepare("
        SELECT a.*, s.name AS service, u.name AS client
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN users u ON a.client_id = u.id
        ORDER BY a.start_at DESC
    ");
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $appointments = [];
    $error = 'Database error: ' . $e->getMessage();
}

// --- Helper for status badge class
function statusBadgeClass($status) {
    return match($status) {
        'pending'   => 'pending',
        'verified'  => 'verified',
        'rejected'  => 'rejected',
        'confirmed' => 'confirmed',
        'cancelled' => 'cancelled',
        default     => 'pending'
    };
}
?>
<?php include 'inc/header_sidebar.php'; ?>
<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 text-salon mb-0">
            <i class="bi bi-calendar-check"></i> Manage Appointments
        </h1>
        <p class="text-muted mb-0">Review and manage customer appointments</p>
    </div>
    <div>
        <a href="admin_dashboard.php" class="btn btn-outline-salon">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<!-- Alerts -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?= h($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?= h($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Appointments Table -->
<div class="card table-salon">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-list-ul"></i> Appointments List
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Booking Ref / Client</th>
                        <th>Service / Style</th>
                        <th>Booking Info</th>
                        <th>Schedule</th>
                        <th>Payment</th>
                        <th class="text-center">Payment Status</th>
                        <th class="text-center">Appointment</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($appointments)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                            No appointments found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($appointments as $a): ?>
                        <tr>
                            <!-- Booking Ref + Client -->
                            <td>
                                <div class="fw-bold text-salon"><?= h($a['booking_ref'] ?? '-') ?></div>
                                <small class="text-muted"><?= h($a['client'] ?? '-') ?></small>
                            </td>

                            <!-- Service + Style -->
                            <td>
                                <div><?= h($a['service'] ?? '-') ?></div>
                                <?php if (!empty($a['style'])): ?>
                                    <small class="text-muted"><i class="bi bi-scissors"></i> <?= h($a['style']) ?></small>
                                <?php endif; ?>
                            </td>

                            <!-- Booking Info -->
                            <td>
                                <div class="fw-bold">
                                    <?php if (($a['booking_type'] ?? 'salon') === 'home'): ?>
                                        <i class="bi bi-house text-salon"></i> Home Service
                                    <?php else: ?>
                                        <i class="bi bi-building text-salon"></i> Salon Visit
                                    <?php endif; ?>
                                </div>
                                <?php if (($a['booking_type'] ?? '') === 'home' && !empty($a['location_address'])): ?>
                                    <small class="text-muted">
                                        <i class="bi bi-geo-alt"></i> <?= h($a['location_address']) ?>
                                    </small>
                                <?php endif; ?>
                            </td>

                            <!-- Schedule -->
                            <td>
                                <?php
                                    $start = !empty($a['start_at']) ? date("M d, Y", strtotime($a['start_at'])) : '-';
                                    $startTime = !empty($a['start_at']) ? date("h:i A", strtotime($a['start_at'])) : '-';
                                    $endTime = !empty($a['end_at']) ? date("h:i A", strtotime($a['end_at'])) : '-';
                                ?>
                                <div class="fw-bold"><?= h($start) ?></div>
                                <small class="text-muted">
                                    <i class="bi bi-clock"></i> <?= h($startTime) ?> - <?= h($endTime) ?>
                                </small>
                            </td>

                            <!-- Payment summary -->
                            <td>
                                <div class="fw-bold text-success">₱<?= number_format((float)($a['down_payment'] ?? 0), 2) ?></div>
                                <?php if (($a['booking_type'] ?? '') === 'home' && ($a['transport_fee'] ?? 0) > 0): ?>
                                    <small class="text-muted">+₱<?= number_format((float)($a['transport_fee'] ?? 0), 2) ?> transport</small>
                                <?php endif; ?>
                                <div class="mt-1">
                                    <?php if (!empty($a['payment_proof'])): ?>
                                        <a href="<?= h($a['payment_proof']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-image"></i> View Proof
                                        </a>
                                    <?php else: ?>
                                        <small class="text-muted">No proof uploaded</small>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Payment status -->
                            <td class="text-center">
                                <?php 
                                    $paymentStatus = $a['payment_status'] ?? 'pending';
                                    $badgeClass = match($paymentStatus) {
                                        'verified' => 'bg-success',
                                        'rejected' => 'bg-danger',
                                        'pending' => 'bg-warning',
                                        default => 'bg-secondary'
                                    };
                                ?>
                                <span class="badge <?= $badgeClass ?> text-white">
                                    <?= ucfirst(h($paymentStatus)) ?>
                                </span>
                            </td>

                            <!-- Appointment status -->
                            <td class="text-center">
                                <?php 
                                    $appointmentStatus = $a['status'] ?? 'pending';
                                    $statusBadgeClass = match($appointmentStatus) {
                                        'confirmed' => 'bg-success',
                                        'cancelled' => 'bg-danger',
                                        'completed' => 'bg-info',
                                        'pending' => 'bg-warning',
                                        default => 'bg-secondary'
                                    };
                                ?>
                                <span class="badge <?= $statusBadgeClass ?> text-white">
                                    <?= ucfirst(h($appointmentStatus)) ?>
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="text-center">
                                <?php if (($a['payment_status'] ?? '') === 'pending'): ?>
                                    <div class="btn-group" role="group">
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Verify this payment?')">
                                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                            <input type="hidden" name="action" value="verify">
                                            <button type="submit" class="btn btn-sm btn-success" title="Verify Payment">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                        </form>
                                        
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Reject this payment? This will cancel the appointment.')">
                                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Reject Payment">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'inc/footer_sidebar.php'; ?>
