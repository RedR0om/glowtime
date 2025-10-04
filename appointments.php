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

// --- Handle POST actions (Verify / Reject / Create) using PRG to avoid double-submits
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $action = $_POST['action'] ?? '';

    // Handle Create Appointment
    if ($action === 'create') {
        $client_id = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
        $service_id = isset($_POST['service_id']) ? (int) $_POST['service_id'] : 0;
        $date = $_POST['date'] ?? '';
        $time = $_POST['time'] ?? '';
        $booking_type = $_POST['booking_type'] ?? 'salon';
        $location_address = ($booking_type === 'home') ? trim($_POST['location_address'] ?? '') : null;
        $style = trim($_POST['style'] ?? '');

        if ($client_id && $service_id && $date && $time) {
            try {
                $start_at = date("Y-m-d H:i:s", strtotime("$date $time"));
                
                // Get service details
                $stmt = pdo()->prepare("SELECT * FROM services WHERE id=?");
                $stmt->execute([$service_id]);
                $service = $stmt->fetch();

                if ($service) {
                    $end_at = date("Y-m-d H:i:s", strtotime("+{$service['duration_minutes']} minutes", strtotime($start_at)));

                    // Check for conflicts
                    $check = pdo()->prepare("SELECT COUNT(*) FROM appointments 
                        WHERE status IN ('pending','confirmed')
                        AND (
                            (start_at < ? AND end_at > ?) 
                            OR (start_at < ? AND end_at > ?) 
                            OR (start_at >= ? AND end_at <= ?)
                        )");
                    $check->execute([$end_at, $start_at, $start_at, $end_at, $start_at, $end_at]);
                    $conflict = $check->fetchColumn();

                    if ($conflict > 0) {
                        header('Location: appointments.php?error=' . urlencode('Time slot conflict. Please choose another time.'));
                        exit;
                    }

                    // Calculate transport fee for home service
                    $transportFee = 0;
                    if ($booking_type === "home" && $location_address) {
                        $transportFee = (stripos($location_address, 'Pateros') !== false) ? 100.00 : 200.00;
                    }

                    // Calculate down payment
                    $down_payment = round(($service['price'] * 0.3) + $transportFee, 2);

                    // Generate booking ref
                    $bookingRef = "ADMIN-" . date("Ymd") . "-" . rand(100, 999);

                    // Insert appointment (auto-verified and confirmed for admin)
                    $stmt = pdo()->prepare("INSERT INTO appointments 
                        (booking_ref, client_id, service_id, booking_type, location_address, style, start_at, end_at, down_payment, transport_fee, payment_proof, payment_status, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 'verified', 'confirmed')");
                    $stmt->execute([
                        $bookingRef,
                        $client_id,
                        $service_id,
                        $booking_type,
                        $location_address,
                        $style,
                        $start_at,
                        $end_at,
                        $down_payment,
                        $transportFee
                    ]);

                    // Send confirmation email to client
                    $stmt2 = pdo()->prepare("SELECT email, name FROM users WHERE id = ?");
                    $stmt2->execute([$client_id]);
                    $client = $stmt2->fetch(PDO::FETCH_ASSOC);
                    if ($client) {
                        sendEmail(
                            $client['email'],
                            "Appointment Confirmed - Glowtime Salon",
                            "<p>Hello <strong>" . h($client['name']) . "</strong>,</p>
                             <p>An appointment has been scheduled for you:</p>
                             <ul>
                                <li><strong>Service:</strong> " . h($service['name']) . "</li>
                                <li><strong>Date:</strong> " . date("M d, Y", strtotime($start_at)) . "</li>
                                <li><strong>Time:</strong> " . date("h:i A", strtotime($start_at)) . "</li>
                                <li><strong>Booking Reference:</strong> " . h($bookingRef) . "</li>
                             </ul>
                             <p>✨ We look forward to serving you!</p>"
                        );
                    }

                    header('Location: appointments.php?success=' . urlencode('Appointment created and confirmed successfully.'));
                    exit;
                } else {
                    header('Location: appointments.php?error=' . urlencode('Invalid service selected.'));
                    exit;
                }
            } catch (PDOException $e) {
                header('Location: appointments.php?error=' . urlencode('Database error: ' . $e->getMessage()));
                exit;
            }
        } else {
            header('Location: appointments.php?error=' . urlencode('Please complete all required fields.'));
            exit;
        }
    }

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
        <button type="button" class="btn btn-salon me-2" data-bs-toggle="modal" data-bs-target="#createAppointmentModal">
            <i class="bi bi-plus-circle"></i> Create Appointment
        </button>
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
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick="showPaymentProof('<?= h($a['payment_proof']) ?>')" title="Preview Payment Proof">
                                            <i class="bi bi-eye"></i> View Proof
                                        </button>
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

<!-- Payment Proof Preview Modal -->
<div class="modal fade" id="paymentProofModal" tabindex="-1" aria-labelledby="paymentProofModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentProofModalLabel">
                    <i class="bi bi-image"></i> Payment Proof
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="proofImage" src="" alt="Payment Proof" class="img-fluid rounded" style="max-height: 500px;">
                <div class="mt-3">
                    <a id="downloadProof" href="" download class="btn btn-outline-primary">
                        <i class="bi bi-download"></i> Download
                    </a>
                    <a id="viewOriginal" href="" target="_blank" class="btn btn-outline-secondary">
                        <i class="bi bi-box-arrow-up-right"></i> View Original
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showPaymentProof(imageUrl) {
    document.getElementById('proofImage').src = imageUrl;
    document.getElementById('downloadProof').href = imageUrl;
    document.getElementById('viewOriginal').href = imageUrl;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('paymentProofModal'));
    modal.show();
}
</script>

<!-- Create Appointment Modal -->
<div class="modal fade" id="createAppointmentModal" tabindex="-1" aria-labelledby="createAppointmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createAppointmentModalLabel">
                    <i class="bi bi-plus-circle"></i> Create New Appointment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" id="createAppointmentForm">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="row">
                        <!-- Client Selection -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="client_id" class="form-label fw-bold">
                                    <i class="bi bi-person"></i> Select Client *
                                </label>
                                <select class="form-select" name="client_id" id="client_id" required>
                                    <option value="">-- Choose a Client --</option>
                                    <?php
                                    $clients = pdo()->query("SELECT id, name, email FROM users WHERE role = 'client' ORDER BY name")->fetchAll();
                                    foreach ($clients as $client):
                                    ?>
                                        <option value="<?= $client['id'] ?>">
                                            <?= htmlspecialchars($client['name']) ?> (<?= htmlspecialchars($client['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Service Selection -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="service_id" class="form-label fw-bold">
                                    <i class="bi bi-scissors"></i> Select Service *
                                </label>
                                <select class="form-select" name="service_id" id="service_id" required>
                                    <option value="">-- Choose a Service --</option>
                                    <?php
                                    $services = pdo()->query("SELECT id, name, price, duration_minutes FROM services ORDER BY name")->fetchAll();
                                    foreach ($services as $service):
                                    ?>
                                        <option value="<?= $service['id'] ?>">
                                            <?= htmlspecialchars($service['name']) ?> - ₱<?= number_format($service['price'],2) ?> (<?= $service['duration_minutes'] ?> mins)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Date -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date" class="form-label fw-bold">
                                    <i class="bi bi-calendar-date"></i> Appointment Date *
                                </label>
                                <input type="date" class="form-control" name="date" id="date" required min="<?= date('Y-m-d') ?>">
                            </div>
                        </div>

                        <!-- Time -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="time" class="form-label fw-bold">
                                    <i class="bi bi-clock"></i> Appointment Time *
                                </label>
                                <input type="time" class="form-control" name="time" id="time" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Booking Type -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-geo-alt"></i> Booking Type *
                                </label>
                                <div class="d-grid gap-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="booking_type" id="booking_type_salon" value="salon" checked>
                                        <label class="form-check-label" for="booking_type_salon">
                                            <i class="bi bi-building"></i> Salon Visit
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="booking_type" id="booking_type_home" value="home" onchange="toggleLocationField()">
                                        <label class="form-check-label" for="booking_type_home">
                                            <i class="bi bi-house"></i> Home Service
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Style -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="style" class="form-label fw-bold">
                                    <i class="bi bi-palette"></i> Preferred Style <span class="text-muted">(Optional)</span>
                                </label>
                                <input type="text" class="form-control" name="style" id="style" placeholder="e.g., Bob Cut, Balayage, Long Layers...">
                            </div>
                        </div>
                    </div>

                    <!-- Location Address (hidden by default) -->
                    <div id="locationField" class="mb-3" style="display: none;">
                        <label for="location_address" class="form-label fw-bold">
                            <i class="bi bi-map"></i> Home Address *
                        </label>
                        <textarea class="form-control" name="location_address" id="location_address" rows="3" placeholder="Enter client's home address..."></textarea>
                        <div class="form-text">
                            <i class="bi bi-info-circle"></i> 
                            Transport fee: ₱100 (Pateros area) | ₱200 (Other areas)
                        </div>
                    </div>

                    <!-- Admin Notice -->
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Admin Booking:</strong> This appointment will be automatically verified and confirmed. An email notification will be sent to the client.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-salon">
                        <i class="bi bi-check-circle"></i> Create Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleLocationField() {
    const homeService = document.getElementById('booking_type_home').checked;
    const locationField = document.getElementById('locationField');
    const locationInput = document.getElementById('location_address');
    
    if (homeService) {
        locationField.style.display = 'block';
        locationInput.required = true;
    } else {
        locationField.style.display = 'none';
        locationInput.required = false;
        locationInput.value = '';
    }
}

// Form validation
document.getElementById('createAppointmentForm').addEventListener('submit', function(e) {
    const clientId = document.getElementById('client_id').value;
    const serviceId = document.getElementById('service_id').value;
    const date = document.getElementById('date').value;
    const time = document.getElementById('time').value;
    
    if (!clientId || !serviceId || !date || !time) {
        e.preventDefault();
        alert('Please fill in all required fields.');
        return;
    }
    
    // Confirm creation
    if (!confirm('Create this appointment? It will be automatically confirmed.')) {
        e.preventDefault();
    }
});
</script>

<?php include 'inc/footer_sidebar.php'; ?>
