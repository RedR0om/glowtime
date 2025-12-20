<?php
require_once 'inc/bootstrap.php';
require_once 'staff.php';

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

// Email function is now in inc/bootstrap.php

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
        $assigned_staff_id = $_POST['assigned_staff_id'] ?? null;

        if ($client_id && $service_id && $date && $time && $assigned_staff_id) {
            try {
                $start_at = date("Y-m-d H:i:s", strtotime("$date $time"));
                
                // Get service details
                $stmt = pdo()->prepare("SELECT * FROM services WHERE id=?");
                $stmt->execute([$service_id]);
                $service = $stmt->fetch();

                if ($service) {
                    $end_at = date("Y-m-d H:i:s", strtotime("+{$service['duration_minutes']} minutes", strtotime($start_at)));

                    // Check for conflicts - same stylist at overlapping time
                    $check = pdo()->prepare("SELECT COUNT(*) FROM appointments 
                        WHERE assigned_staff_id = ?
                        AND status IN ('pending','confirmed')
                        AND (
                            (start_at < ? AND end_at > ?) 
                            OR (start_at < ? AND end_at > ?) 
                            OR (start_at >= ? AND end_at <= ?)
                        )");
                    $check->execute([$assigned_staff_id, $end_at, $start_at, $start_at, $end_at, $start_at, $end_at]);
                    $conflict = $check->fetchColumn();

                    if ($conflict > 0) {
                        header('Location: appointments.php?error=' . urlencode('This stylist is already booked for this time slot. Please choose another time or stylist.'));
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
                        (booking_ref, client_id, service_id, booking_type, location_address, style, start_at, end_at, down_payment, transport_fee, payment_proof, payment_status, status, assigned_staff_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 'verified', 'confirmed', ?)");
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
                        $transportFee,
                        $assigned_staff_id
                    ]);

                    // Send confirmation email to client
                    $stmt2 = pdo()->prepare("SELECT email, name FROM users WHERE id = ?");
                    $stmt2->execute([$client_id]);
                    $client = $stmt2->fetch(PDO::FETCH_ASSOC);
                    if ($client) {
                        $emailResult = sendEmail(
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
                        if (!$emailResult['success']) {
                            error_log("Failed to send appointment confirmation email: " . $emailResult['error']);
                        }
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

    if ($id <= 0 || !in_array($action, ['verify', 'reject', 'update_stylist'], true)) {
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
                $emailResult = sendEmail(
                    $row['email'],
                    "Your Booking Confirmed - Glowtime",
                    "<p>Hello <strong>" . h($row['name']) . "</strong>,</p>
                     <p>Your booking (<strong>" . h($row['booking_ref']) . "</strong>) has been <b style='color:green'>confirmed</b>.</p>
                     <p>✨ Thank you for choosing Glowtime Salon!</p>"
                );
                if (!$emailResult['success']) {
                    error_log("Failed to send booking confirmation email: " . $emailResult['error']);
                }
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
                $emailResult = sendEmail(
                    $row['email'],
                    "Booking Cancelled - Glowtime",
                    "<p>Hello <strong>" . h($row['name']) . "</strong>,</p>
                     <p>Your booking (<strong>" . h($row['booking_ref']) . "</strong>) has been <b style='color:red'>rejected</b> due to invalid payment proof.</p>
                     <p>⚠️ If you believe this was a mistake, please contact support.</p>"
                );
                if (!$emailResult['success']) {
                    error_log("Failed to send booking rejection email: " . $emailResult['error']);
                }
            }

            header('Location: appointments.php?success=' . urlencode('Payment rejected and booking cancelled.'));
            exit;
        }

        if ($action === 'update_stylist') {
            $new_staff_id = $_POST['new_staff_id'] ?? null;
            
            if (empty($new_staff_id)) {
                header('Location: appointments.php?error=' . urlencode('Please select a stylist.'));
                exit;
            }

            // Get appointment details for conflict check
            $stmt = pdo()->prepare("SELECT start_at, end_at, assigned_staff_id FROM appointments WHERE id = ?");
            $stmt->execute([$id]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$appointment) {
                header('Location: appointments.php?error=' . urlencode('Appointment not found.'));
                exit;
            }

            // Check for conflicts with the new stylist (excluding current appointment)
            $check = pdo()->prepare("SELECT COUNT(*) FROM appointments 
                WHERE assigned_staff_id = ?
                AND id != ?
                AND status IN ('pending','confirmed')
                AND (
                    (start_at < ? AND end_at > ?) 
                    OR (start_at < ? AND end_at > ?) 
                    OR (start_at >= ? AND end_at <= ?)
                )");
            $check->execute([
                $new_staff_id, 
                $id,
                $appointment['end_at'], 
                $appointment['start_at'], 
                $appointment['start_at'], 
                $appointment['end_at'], 
                $appointment['start_at'], 
                $appointment['end_at']
            ]);
            $conflict = $check->fetchColumn();

            if ($conflict > 0) {
                header('Location: appointments.php?error=' . urlencode('The selected stylist is already booked for this time slot. Please choose another stylist.'));
                exit;
            }

            // Get old stylist name
            $oldStylistName = 'Not Assigned';
            if ($appointment['assigned_staff_id']) {
                $stmtOld = pdo()->prepare("SELECT staff_name FROM staff WHERE staff_id = ?");
                $stmtOld->execute([$appointment['assigned_staff_id']]);
                $oldStylist = $stmtOld->fetch(PDO::FETCH_ASSOC);
                if ($oldStylist) {
                    $oldStylistName = $oldStylist['staff_name'];
                }
            }

            // Get new stylist name
            $newStylistName = 'Not Assigned';
            $stmtNew = pdo()->prepare("SELECT staff_name FROM staff WHERE staff_id = ?");
            $stmtNew->execute([$new_staff_id]);
            $newStylist = $stmtNew->fetch(PDO::FETCH_ASSOC);
            if ($newStylist) {
                $newStylistName = $newStylist['staff_name'];
            }

            // Update the stylist
            $stmt = pdo()->prepare("UPDATE appointments SET assigned_staff_id = ? WHERE id = ?");
            $stmt->execute([$new_staff_id, $id]);

            // Fetch client info for email
            $stmt2 = pdo()->prepare("SELECT u.email, u.name, a.booking_ref, a.start_at, s.name AS service_name 
                FROM appointments a 
                JOIN users u ON a.client_id = u.id 
                JOIN services s ON a.service_id = s.id
                WHERE a.id = ?");
            $stmt2->execute([$id]);
            $row = $stmt2->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $emailResult = sendEmail(
                    $row['email'],
                    "Stylist Changed - " . h($row['booking_ref']) . " | Glowtime Salon",
                    "<p>Hello <strong>" . h($row['name']) . "</strong>,</p>
                     <p>We wanted to inform you that there has been a change to your appointment.</p>
                     <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #e91e63;'>
                        <p><strong>Booking Reference:</strong> " . h($row['booking_ref']) . "</p>
                        <p><strong>Service:</strong> " . h($row['service_name']) . "</p>
                        <p><strong>Date & Time:</strong> " . date("F d, Y h:i A", strtotime($row['start_at'])) . "</p>
                        <p><strong>Previous Stylist:</strong> " . h($oldStylistName) . "</p>
                        <p><strong>New Stylist:</strong> <strong style='color: #e91e63;'>" . h($newStylistName) . "</strong></p>
                     </div>
                     <p>Your appointment details remain the same, only the assigned stylist has been updated.</p>
                     <p>If you have any questions or concerns, please don't hesitate to contact us.</p>
                     <p>✨ We look forward to serving you!</p>
                     <p><strong>Glowtime Salon Team</strong></p>"
                );
                if (!$emailResult['success']) {
                    error_log("Failed to send stylist change email: " . $emailResult['error']);
                }
            }

            header('Location: appointments.php?success=' . urlencode('Stylist updated successfully. Client has been notified.'));
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

// --- Get filter parameters
$filter_status = $_GET['filter_status'] ?? '';
$filter_payment_status = $_GET['filter_payment_status'] ?? '';
$filter_booking_type = $_GET['filter_booking_type'] ?? '';
$filter_date_from = $_GET['filter_date_from'] ?? '';
$filter_date_to = $_GET['filter_date_to'] ?? '';
$search_query = $_GET['search'] ?? '';
$fromQr = isset($_GET['from_qr']) && $_GET['from_qr'] === '1';

// --- Fetch appointments with filters (safe)
try {
    $whereConditions = [];
    $params = [];
    
    // Status filter
    if (!empty($filter_status) && in_array($filter_status, ['pending', 'confirmed', 'cancelled', 'completed'])) {
        $whereConditions[] = "a.status = ?";
        $params[] = $filter_status;
    }
    
    // Payment status filter
    if (!empty($filter_payment_status) && in_array($filter_payment_status, ['pending', 'verified', 'rejected'])) {
        $whereConditions[] = "a.payment_status = ?";
        $params[] = $filter_payment_status;
    }
    
    // Booking type filter
    if (!empty($filter_booking_type) && in_array($filter_booking_type, ['salon', 'home'])) {
        $whereConditions[] = "a.booking_type = ?";
        $params[] = $filter_booking_type;
    }
    
    // Date range filters
    if (!empty($filter_date_from)) {
        $whereConditions[] = "DATE(a.start_at) >= ?";
        $params[] = $filter_date_from;
    }
    
    if (!empty($filter_date_to)) {
        $whereConditions[] = "DATE(a.start_at) <= ?";
        $params[] = $filter_date_to;
    }
    
    // Search filter
    if (!empty($search_query)) {
        $whereConditions[] = "(u.name LIKE ? OR a.booking_ref LIKE ? OR s.name LIKE ?)";
        $searchTerm = "%{$search_query}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Build the query - include stylist name (with collation handling)
    $sql = "
        SELECT a.*, s.name AS service, u.name AS client, st.staff_name AS stylist_name
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN users u ON a.client_id = u.id
        LEFT JOIN staff st ON a.assigned_staff_id COLLATE utf8mb4_unicode_ci = st.staff_id COLLATE utf8mb4_unicode_ci
    ";
    
    if (!empty($whereConditions)) {
        $sql .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    $sql .= " ORDER BY a.start_at DESC";
    
    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $appointments = [];
    $error = 'Database error: ' . $e->getMessage();
}

// --- Helper for status badge class
function statusBadgeClass($status) {
    switch($status) {
        case 'pending':
            return 'pending';
        case 'verified':
            return 'verified';
        case 'rejected':
            return 'rejected';
        case 'confirmed':
            return 'confirmed';
        case 'cancelled':
            return 'cancelled';
        default:
            return 'pending';
    }
}
?>
<?php include 'inc/header_sidebar.php'; ?>
<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4 page-header">
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
        <a href="admin_dashboard.php" class="btn btn-outline-salon d-none d-md-inline-block">
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

<!-- Filters Section -->
<?php if (!$fromQr): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-funnel"></i> Filter Appointments
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <!-- Search -->
            <div class="col-md-3">
                <label for="search" class="form-label fw-bold">
                    <i class="bi bi-search"></i> Search
                </label>
                <input type="text" class="form-control" name="search" id="search" 
                       placeholder="Client, Booking Ref, or Service..." 
                       value="<?= h($search_query) ?>">
            </div>

            <!-- Status Filter -->
            <div class="col-md-2">
                <label for="filter_status" class="form-label fw-bold">
                    <i class="bi bi-calendar-check"></i> Status
                </label>
                <select class="form-select" name="filter_status" id="filter_status">
                    <option value="">All Status</option>
                    <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="confirmed" <?= $filter_status === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>

            <!-- Payment Status Filter -->
            <div class="col-md-2">
                <label for="filter_payment_status" class="form-label fw-bold">
                    <i class="bi bi-credit-card"></i> Payment
                </label>
                <select class="form-select" name="filter_payment_status" id="filter_payment_status">
                    <option value="">All Payment</option>
                    <option value="pending" <?= $filter_payment_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="verified" <?= $filter_payment_status === 'verified' ? 'selected' : '' ?>>Verified</option>
                    <option value="rejected" <?= $filter_payment_status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>

            <!-- Booking Type Filter -->
            <div class="col-md-2">
                <label for="filter_booking_type" class="form-label fw-bold">
                    <i class="bi bi-geo-alt"></i> Type
                </label>
                <select class="form-select" name="filter_booking_type" id="filter_booking_type">
                    <option value="">All Types</option>
                    <option value="salon" <?= $filter_booking_type === 'salon' ? 'selected' : '' ?>>Salon Visit</option>
                    <option value="home" <?= $filter_booking_type === 'home' ? 'selected' : '' ?>>Home Service</option>
                </select>
            </div>

            <!-- Date From -->
            <div class="col-md-2">
                <label for="filter_date_from" class="form-label fw-bold">
                    <i class="bi bi-calendar-date"></i> From Date
                </label>
                <input type="date" class="form-control" name="filter_date_from" id="filter_date_from" 
                       value="<?= h($filter_date_from) ?>">
            </div>

            <!-- Date To -->
            <div class="col-md-2">
                <label for="filter_date_to" class="form-label fw-bold">
                    <i class="bi bi-calendar-date"></i> To Date
                </label>
                <input type="date" class="form-control" name="filter_date_to" id="filter_date_to" 
                       value="<?= h($filter_date_to) ?>">
            </div>

            <!-- Filter Buttons -->
            <div class="col-12">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-salon">
                        <i class="bi bi-funnel"></i> Apply Filters
                    </button>
                    <a href="appointments.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i> Clear All
                    </a>
                    <?php if (!empty($filter_status) || !empty($filter_payment_status) || !empty($filter_booking_type) || !empty($filter_date_from) || !empty($filter_date_to) || !empty($search_query)): ?>
                        <span class="badge bg-info align-self-center">
                            <i class="bi bi-info-circle"></i> 
                            <?php 
                                $activeFilters = array_filter([
                                    $filter_status ? "Status: " . ucfirst($filter_status) : null,
                                    $filter_payment_status ? "Payment: " . ucfirst($filter_payment_status) : null,
                                    $filter_booking_type ? "Type: " . ucfirst($filter_booking_type) : null,
                                    $filter_date_from ? "From: " . date('M d, Y', strtotime($filter_date_from)) : null,
                                    $filter_date_to ? "To: " . date('M d, Y', strtotime($filter_date_to)) : null,
                                    $search_query ? "Search: " . $search_query : null
                                ]);
                                echo count($activeFilters) . " filter(s) active";
                            ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </form>
        
        <!-- Quick Filter Buttons -->
        <div class="mt-3 pt-3 border-top">
            <div class="d-flex flex-wrap gap-2">
                <span class="text-muted me-2"><i class="bi bi-lightning"></i> Quick Filters:</span>
                <a href="appointments.php?filter_status=pending" class="btn btn-sm btn-outline-warning quick-filter-btn">
                    <i class="bi bi-clock"></i> Pending
                </a>
                <a href="appointments.php?filter_payment_status=verified" class="btn btn-sm btn-outline-success quick-filter-btn">
                    <i class="bi bi-check-circle"></i> Verified Payments
                </a>
                <a href="appointments.php?filter_booking_type=home" class="btn btn-sm btn-outline-info quick-filter-btn">
                    <i class="bi bi-house"></i> Home Services
                </a>
                <a href="appointments.php?filter_booking_type=salon" class="btn btn-sm btn-outline-primary quick-filter-btn">
                    <i class="bi bi-building"></i> Salon Visits
                </a>
                <a href="appointments.php?filter_date_from=<?= date('Y-m-d') ?>&filter_date_to=<?= date('Y-m-d', strtotime('+7 days')) ?>" class="btn btn-sm btn-outline-secondary quick-filter-btn">
                    <i class="bi bi-calendar-week"></i> This Week
                </a>
                <a href="appointments.php?filter_date_from=<?= date('Y-m-01') ?>&filter_date_to=<?= date('Y-m-t') ?>" class="btn btn-sm btn-outline-dark quick-filter-btn">
                    <i class="bi bi-calendar-month"></i> This Month
                </a>
            </div>
        </div>
    </div>
    </div>
</div>
<?php endif; ?>

<!-- Appointments List -->
<div class="card table-salon">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-list-ul"></i> Appointments List
        </h5>
        <div class="text-muted">
            <i class="bi bi-info-circle"></i> 
            Showing <?= count($appointments) ?> appointment(s)
            <?php if (!empty($filter_status) || !empty($filter_payment_status) || !empty($filter_booking_type) || !empty($filter_date_from) || !empty($filter_date_to) || !empty($search_query)): ?>
                <span class="text-salon">(filtered)</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($appointments)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                No appointments found.
            </div>
        <?php else: ?>
            <!-- Desktop Table View -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Booking Ref / Client</th>
                            <th>Service / Style</th>
                            <th>Stylist</th>
                            <th>Booking Info</th>
                            <th>Schedule</th>
                            <th>Payment</th>
                            <th class="text-center">Payment Status</th>
                            <th class="text-center">Appointment</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
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

                                <!-- Stylist -->
                                <td>
                                    <div class="fw-bold">
                                        <i class="bi bi-person-badge text-salon"></i> 
                                        <?= h($a['stylist_name'] ?? $a['assigned_staff_id'] ?? 'Not Assigned') ?>
                                    </div>
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
                                        switch($paymentStatus) {
                                            case 'verified':
                                                $badgeClass = 'bg-success';
                                                break;
                                            case 'rejected':
                                                $badgeClass = 'bg-danger';
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
                                        <?= ucfirst(h($paymentStatus)) ?>
                                    </span>
                                </td>

                                <!-- Appointment status -->
                                <td class="text-center">
                                    <?php 
                                        $appointmentStatus = $a['status'] ?? 'pending';
                                        switch($appointmentStatus) {
                                            case 'confirmed':
                                                $statusBadgeClass = 'bg-success';
                                                break;
                                            case 'cancelled':
                                                $statusBadgeClass = 'bg-danger';
                                                break;
                                            case 'completed':
                                                $statusBadgeClass = 'bg-info';
                                                break;
                                            case 'pending':
                                                $statusBadgeClass = 'bg-warning';
                                                break;
                                            default:
                                                $statusBadgeClass = 'bg-secondary';
                                                break;
                                        }
                                    ?>
                                    <span class="badge <?= $statusBadgeClass ?> text-white">
                                        <?= ucfirst(h($appointmentStatus)) ?>
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="text-center">
                                    <div class="d-flex gap-2 justify-content-center" role="group">
                                        <?php if (($a['payment_status'] ?? '') === 'pending'): ?>
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
                                        <?php endif; ?>
                                        
                                        <button type="button" class="btn btn-sm btn-info edit-stylist-btn" 
                                                data-appointment-id="<?= (int)$a['id'] ?>"
                                                data-booking-ref="<?= h($a['booking_ref'] ?? '') ?>"
                                                data-current-stylist="<?= h($a['stylist_name'] ?? 'Not Assigned') ?>"
                                                data-current-staff-id="<?= h($a['assigned_staff_id'] ?? '') ?>"
                                                data-appointment-date="<?= !empty($a['start_at']) ? date('Y-m-d', strtotime($a['start_at'])) : '' ?>"
                                                title="Edit Stylist">
                                            <i class="bi bi-person-gear"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Mobile Card View -->
            <div class="d-md-none appointments-cards">
                <?php foreach ($appointments as $a): ?>
                    <?php
                        $start = !empty($a['start_at']) ? date("M d, Y", strtotime($a['start_at'])) : '-';
                        $startTime = !empty($a['start_at']) ? date("h:i A", strtotime($a['start_at'])) : '-';
                        $endTime = !empty($a['end_at']) ? date("h:i A", strtotime($a['end_at'])) : '-';
                        
                        $paymentStatus = $a['payment_status'] ?? 'pending';
                        switch($paymentStatus) {
                            case 'verified':
                                $badgeClass = 'bg-success';
                                break;
                            case 'rejected':
                                $badgeClass = 'bg-danger';
                                break;
                            case 'pending':
                                $badgeClass = 'bg-warning';
                                break;
                            default:
                                $badgeClass = 'bg-secondary';
                                break;
                        }
                        
                        $appointmentStatus = $a['status'] ?? 'pending';
                        switch($appointmentStatus) {
                            case 'confirmed':
                                $statusBadgeClass = 'bg-success';
                                break;
                            case 'cancelled':
                                $statusBadgeClass = 'bg-danger';
                                break;
                            case 'completed':
                                $statusBadgeClass = 'bg-info';
                                break;
                            case 'pending':
                                $statusBadgeClass = 'bg-warning';
                                break;
                            default:
                                $statusBadgeClass = 'bg-secondary';
                                break;
                        }
                    ?>
                    <div class="appointment-card-mobile">
                        <div class="card-header-mobile">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <div>
                                    <h6 class="mb-0 text-salon fw-bold">
                                        <i class="bi bi-tag"></i> <?= h($a['booking_ref'] ?? '-') ?>
                                    </h6>
                                    <small class="text-muted">
                                        <i class="bi bi-person"></i> <?= h($a['client'] ?? '-') ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <span class="badge <?= $statusBadgeClass ?> text-white mb-1 d-block">
                                        <?= ucfirst(h($appointmentStatus)) ?>
                                    </span>
                                    <span class="badge <?= $badgeClass ?> text-white">
                                        <?= ucfirst(h($paymentStatus)) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-body-mobile">
                            <!-- Service & Style -->
                            <div class="info-row">
                                <div class="info-label">
                                    <i class="bi bi-scissors text-salon"></i> Service
                                </div>
                                <div class="info-value">
                                    <strong><?= h($a['service'] ?? '-') ?></strong>
                                    <?php if (!empty($a['style'])): ?>
                                        <br><small class="text-muted">Style: <?= h($a['style']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Stylist -->
                            <div class="info-row">
                                <div class="info-label">
                                    <i class="bi bi-person-badge text-salon"></i> Stylist
                                </div>
                                <div class="info-value">
                                    <?= h($a['stylist_name'] ?? $a['assigned_staff_id'] ?? 'Not Assigned') ?>
                                </div>
                            </div>
                            
                            <!-- Schedule -->
                            <div class="info-row">
                                <div class="info-label">
                                    <i class="bi bi-calendar-event text-salon"></i> Schedule
                                </div>
                                <div class="info-value">
                                    <strong><?= h($start) ?></strong><br>
                                    <small class="text-muted">
                                        <i class="bi bi-clock"></i> <?= h($startTime) ?> - <?= h($endTime) ?>
                                    </small>
                                </div>
                            </div>
                            
                            <!-- Booking Type -->
                            <div class="info-row">
                                <div class="info-label">
                                    <i class="bi bi-geo-alt text-salon"></i> Type
                                </div>
                                <div class="info-value">
                                    <?php if (($a['booking_type'] ?? 'salon') === 'home'): ?>
                                        <i class="bi bi-house"></i> Home Service
                                        <?php if (!empty($a['location_address'])): ?>
                                            <br><small class="text-muted"><?= h($a['location_address']) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <i class="bi bi-building"></i> Salon Visit
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Payment -->
                            <div class="info-row">
                                <div class="info-label">
                                    <i class="bi bi-cash-coin text-salon"></i> Payment
                                </div>
                                <div class="info-value">
                                    <strong class="text-success">₱<?= number_format((float)($a['down_payment'] ?? 0), 2) ?></strong>
                                    <?php if (($a['booking_type'] ?? '') === 'home' && ($a['transport_fee'] ?? 0) > 0): ?>
                                        <br><small class="text-muted">+₱<?= number_format((float)($a['transport_fee'] ?? 0), 2) ?> transport</small>
                                    <?php endif; ?>
                                    <?php if (!empty($a['payment_proof'])): ?>
                                        <br>
                                        <button type="button" class="btn btn-sm btn-outline-info mt-1" onclick="showPaymentProof('<?= h($a['payment_proof']) ?>')">
                                            <i class="bi bi-eye"></i> View Proof
                                        </button>
                                    <?php else: ?>
                                        <br><small class="text-muted">No proof uploaded</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <div class="actions-mobile mt-2 pt-2 border-top">
                                <div class="d-flex gap-1 flex-wrap">
                                    <?php if (($a['payment_status'] ?? '') === 'pending'): ?>
                                        <form method="post" style="flex: 1; min-width: 100px;" onsubmit="return confirm('Verify this payment?')">
                                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                            <input type="hidden" name="action" value="verify">
                                            <button type="submit" class="btn btn-success w-100 btn-sm">
                                                <i class="bi bi-check-circle"></i> Verify
                                            </button>
                                        </form>
                                        
                                        <form method="post" style="flex: 1; min-width: 100px;" onsubmit="return confirm('Reject this payment? This will cancel the appointment.')">
                                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-danger w-100 btn-sm">
                                                <i class="bi bi-x-circle"></i> Reject
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="btn btn-info btn-sm edit-stylist-btn" 
                                            style="flex: 1; min-width: 100px;"
                                            data-appointment-id="<?= (int)$a['id'] ?>"
                                            data-booking-ref="<?= h($a['booking_ref'] ?? '') ?>"
                                            data-current-stylist="<?= h($a['stylist_name'] ?? 'Not Assigned') ?>"
                                            data-current-staff-id="<?= h($a['assigned_staff_id'] ?? '') ?>"
                                            data-appointment-date="<?= !empty($a['start_at']) ? date('Y-m-d', strtotime($a['start_at'])) : '' ?>">
                                        <i class="bi bi-person-gear"></i> Edit
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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

                    <!-- Stylist Selection -->
                    <div class="mb-3">
                        <label for="admin_assigned_staff_id" class="form-label fw-bold">
                            <i class="bi bi-person-badge"></i> Assign Stylist *
                        </label>
                        <select class="form-select" name="assigned_staff_id" id="admin_assigned_staff_id" required>
                            <option value="">-- Choose a Stylist --</option>
                        </select>
                        <div class="form-text">
                            <i class="bi bi-info-circle"></i> 
                            Only stylists available on the selected date will be shown
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

// Load available stylists for admin form
function loadAdminStylists() {
    const date = document.getElementById('date').value;
    if (!date) return;
    
    fetch(`get_available_stylists.php?date=${date}`)
        .then(res => res.json())
        .then(data => {
            const stylistSelect = document.getElementById('admin_assigned_staff_id');
            stylistSelect.innerHTML = '<option value="">-- Choose a Stylist --</option>';
            
            if (data.error) {
                console.error('API Error:', data.error);
                stylistSelect.innerHTML += '<option value="" disabled>Error loading stylists</option>';
                return;
            }
            
            const stylists = Array.isArray(data) ? data : (data.data || []);
            
            if (stylists.length === 0) {
                stylistSelect.innerHTML += '<option value="" disabled>No stylists available on this date</option>';
            } else {
                stylists.forEach(stylist => {
                    const option = document.createElement('option');
                    option.value = stylist.staff_id;
                    option.textContent = stylist.staff_name;
                    stylistSelect.appendChild(option);
                });
            }
        })
        .catch(err => {
            console.error('Error loading stylists:', err);
        });
}

// Add event listener to date field
document.getElementById('date').addEventListener('change', loadAdminStylists);

// Form validation
document.getElementById('createAppointmentForm').addEventListener('submit', function(e) {
    const clientId = document.getElementById('client_id').value;
    const serviceId = document.getElementById('service_id').value;
    const date = document.getElementById('date').value;
    const time = document.getElementById('time').value;
    const stylistId = document.getElementById('admin_assigned_staff_id').value;
    
    if (!clientId || !serviceId || !date || !time || !stylistId) {
        e.preventDefault();
        alert('Please fill in all required fields including stylist selection.');
        return;
    }
    
    // Confirm creation
    if (!confirm('Create this appointment? It will be automatically confirmed.')) {
        e.preventDefault();
    }
});

// Filter form enhancements
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit filters when dropdowns change (optional)
    const filterSelects = document.querySelectorAll('select[name^="filter_"]');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            // Optional: Auto-submit on change
            // document.querySelector('form').submit();
        });
    });
    
    // Date validation
    const dateFrom = document.getElementById('filter_date_from');
    const dateTo = document.getElementById('filter_date_to');
    
    if (dateFrom && dateTo) {
        dateFrom.addEventListener('change', function() {
            if (this.value && dateTo.value && this.value > dateTo.value) {
                dateTo.value = this.value;
            }
        });
        
        dateTo.addEventListener('change', function() {
            if (this.value && dateFrom.value && this.value < dateFrom.value) {
                dateFrom.value = this.value;
            }
        });
    }
    
    // Quick filter buttons are working via href links, no need for additional JS
    
    // Attach event listeners to edit stylist buttons
    document.querySelectorAll('.edit-stylist-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const appointmentId = this.getAttribute('data-appointment-id');
            const bookingRef = this.getAttribute('data-booking-ref');
            const currentStylist = this.getAttribute('data-current-stylist');
            const currentStaffId = this.getAttribute('data-current-staff-id');
            const appointmentDate = this.getAttribute('data-appointment-date');
            openEditStylistModal(appointmentId, bookingRef, currentStylist, currentStaffId, appointmentDate);
        });
    });
});

// Edit Stylist Modal Functions
function openEditStylistModal(appointmentId, bookingRef, currentStylist, currentStaffId, appointmentDate) {
    document.getElementById('editStylistAppointmentId').value = appointmentId;
    document.getElementById('editStylistBookingRef').textContent = bookingRef;
    document.getElementById('editStylistCurrentStylist').textContent = currentStylist;
    document.getElementById('editStylistAppointmentDate').value = appointmentDate;
    
    // Load available stylists for the appointment date
    if (appointmentDate) {
        loadEditStylistOptions(appointmentDate, currentStaffId);
    } else {
        document.getElementById('editStylistSelect').innerHTML = '<option value="">Error: No date available</option>';
    }
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('editStylistModal'));
    modal.show();
}

function loadEditStylistOptions(date, excludeStaffId) {
    fetch(`get_available_stylists.php?date=${date}`)
        .then(res => res.json())
        .then(data => {
            const stylistSelect = document.getElementById('editStylistSelect');
            stylistSelect.innerHTML = '<option value="">-- Choose a Stylist --</option>';
            
            if (data.error) {
                console.error('API Error:', data.error);
                stylistSelect.innerHTML += '<option value="" disabled>Error loading stylists</option>';
                return;
            }
            
            const stylists = Array.isArray(data) ? data : (data.data || []);
            
            if (stylists.length === 0) {
                stylistSelect.innerHTML += '<option value="" disabled>No stylists available on this date</option>';
            } else {
                stylists.forEach(stylist => {
                    const option = document.createElement('option');
                    option.value = stylist.staff_id;
                    option.textContent = stylist.staff_name;
                    // Pre-select current stylist if available
                    if (stylist.staff_id === excludeStaffId) {
                        option.selected = true;
                    }
                    stylistSelect.appendChild(option);
                });
            }
        })
        .catch(err => {
            console.error('Error loading stylists:', err);
            document.getElementById('editStylistSelect').innerHTML = '<option value="">Error loading stylists</option>';
        });
}
</script>

<!-- Edit Stylist Modal -->
<div class="modal fade" id="editStylistModal" tabindex="-1" aria-labelledby="editStylistModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editStylistModalLabel">
                    <i class="bi bi-person-badge"></i> Edit Stylist Assignment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" id="editStylistForm" onsubmit="return confirm('Update the stylist for this appointment? The client will be notified via email.')">
                <input type="hidden" name="action" value="update_stylist">
                <input type="hidden" name="id" id="editStylistAppointmentId">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Note:</strong> Changing the stylist will send an email notification to the client.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Booking Reference:</label>
                        <div class="form-control-plaintext" id="editStylistBookingRef">-</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Current Stylist:</label>
                        <div class="form-control-plaintext" id="editStylistCurrentStylist">-</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editStylistSelect" class="form-label fw-bold">
                            <i class="bi bi-person-badge"></i> Select New Stylist *
                        </label>
                        <select class="form-select" name="new_staff_id" id="editStylistSelect" required>
                            <option value="">-- Loading stylists... --</option>
                        </select>
                        <div class="form-text">
                            <i class="bi bi-info-circle"></i> 
                            Only stylists available on the appointment date are shown. The system will check for scheduling conflicts.
                        </div>
                    </div>
                    
                    <input type="hidden" id="editStylistAppointmentDate">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-salon">
                        <i class="bi bi-check-circle"></i> Update Stylist
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Ensure sidebar toggle works */
#sidebarToggle {
    z-index: 1000;
    position: relative;
}

#sidebar.show {
    transform: translateX(0) !important;
    visibility: visible !important;
    opacity: 1 !important;
}

#sidebarOverlay.show {
    display: block !important;
    opacity: 1 !important;
    visibility: visible !important;
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
    
    /* Alerts - Better spacing */
    .alert {
        margin: 0 0.5rem 1rem 0.5rem !important;
        font-size: 0.9rem;
        padding: 0.75rem 1rem !important;
    }
    
    /* Filter Card - Compact on mobile */
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
    
    /* Filter Form - Stack on mobile with compact spacing */
    .card-body .row {
        --bs-gutter-y: 0.75rem;
    }
    
    .card-body .row .col-md-3,
    .card-body .row .col-md-2 {
        width: 100% !important;
        max-width: 100% !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin-bottom: 0.5rem !important;
    }
    
    .card-body .form-label {
        font-size: 0.85rem !important;
        margin-bottom: 0.35rem !important;
        font-weight: 600;
    }
    
    .card-body .form-label i {
        font-size: 0.8rem;
    }
    
    .card-body .form-control,
    .card-body .form-select {
        font-size: 0.85rem !important;
        padding: 0.4rem 0.65rem !important;
    }
    
    /* Filter Buttons - Compact */
    .card-body .d-flex.gap-2 {
        flex-direction: column !important;
        gap: 0.4rem !important;
    }
    
    .card-body .btn {
        width: 100% !important;
        font-size: 0.8rem !important;
        padding: 0.45rem 0.75rem !important;
    }
    
    .card-body .btn i {
        font-size: 0.85rem;
    }
    
    .card-body .badge {
        font-size: 0.7rem !important;
        padding: 0.3rem 0.6rem !important;
        display: block !important;
        text-align: center !important;
        margin-top: 0.4rem !important;
    }
    
    /* Quick Filter Buttons - Compact */
    .card-body .mt-3.pt-3 {
        margin-top: 0.75rem !important;
        padding-top: 0.75rem !important;
    }
    
    .card-body .d-flex.flex-wrap {
        flex-direction: row !important;
        gap: 0.4rem !important;
    }
    
    .card-body .d-flex.flex-wrap .text-muted {
        width: 100%;
        font-size: 0.8rem;
        margin-bottom: 0.4rem;
    }
    
    .card-body .quick-filter-btn {
        flex: 1 1 auto;
        min-width: calc(50% - 0.2rem);
        font-size: 0.75rem !important;
        padding: 0.35rem 0.5rem !important;
        margin-bottom: 0.4rem !important;
    }
    
    .card-body .quick-filter-btn i {
        font-size: 0.75rem;
    }
    
    /* Appointments Table Card */
    .table-salon .card-header {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 0.75rem;
    }
    
    .table-salon .card-header .text-muted {
        font-size: 0.85rem !important;
    }
    
    /* Mobile Card View Styles - Compact */
    .appointments-cards {
        padding: 0.25rem;
    }
    
    .appointment-card-mobile {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        margin-bottom: 0.75rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .appointment-card-mobile .card-header-mobile {
        background: linear-gradient(135deg, var(--salon-primary, #e91e63) 0%, rgba(233, 30, 99, 0.8) 100%);
        color: white;
        padding: 0.75rem;
    }
    
    .appointment-card-mobile .card-header-mobile h6 {
        color: white !important;
        font-size: 0.9rem;
        margin-bottom: 0.25rem !important;
    }
    
    .appointment-card-mobile .card-header-mobile small {
        color: rgba(255, 255, 255, 0.9) !important;
        font-size: 0.8rem;
    }
    
    .appointment-card-mobile .card-body-mobile {
        padding: 0.75rem;
    }
    
    .appointment-card-mobile .info-row {
        display: flex;
        padding: 0.5rem 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .appointment-card-mobile .info-row:last-child {
        border-bottom: none;
    }
    
    .appointment-card-mobile .info-label {
        flex: 0 0 85px;
        font-weight: 600;
        font-size: 0.8rem;
        color: #6c757d;
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }
    
    .appointment-card-mobile .info-label i {
        font-size: 0.85rem;
    }
    
    .appointment-card-mobile .info-value {
        flex: 1;
        font-size: 0.85rem;
        color: #212529;
        line-height: 1.4;
    }
    
    .appointment-card-mobile .info-value strong {
        font-size: 0.9rem;
    }
    
    .appointment-card-mobile .info-value small {
        font-size: 0.75rem;
    }
    
    .appointment-card-mobile .actions-mobile {
        margin-top: 0.75rem !important;
        padding-top: 0.75rem !important;
    }
    
    .appointment-card-mobile .actions-mobile .btn {
        font-size: 0.8rem;
        padding: 0.4rem 0.75rem;
    }
    
    .appointment-card-mobile .badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
    }
    
    .appointment-card-mobile .btn-sm {
        font-size: 0.75rem;
        padding: 0.3rem 0.6rem;
        margin-top: 0.25rem;
    }
    
    /* Modals - Better on mobile */
    .modal-dialog {
        margin: 0.5rem !important;
        max-width: calc(100% - 1rem) !important;
    }
    
    .modal-content {
        border-radius: 12px !important;
    }
    
    .modal-header {
        padding: 1rem !important;
    }
    
    .modal-body {
        padding: 1rem !important;
    }
    
    .modal-body .row .col-md-6 {
        width: 100% !important;
        max-width: 100% !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin-bottom: 1rem !important;
    }
    
    .modal-body .form-label {
        font-size: 0.9rem !important;
    }
    
    .modal-body .form-control,
    .modal-body .form-select {
        font-size: 0.9rem !important;
        padding: 0.5rem 0.75rem !important;
    }
    
    .modal-body .alert {
        font-size: 0.85rem !important;
        padding: 0.75rem !important;
    }
    
    .modal-footer {
        padding: 1rem !important;
        flex-direction: column !important;
        gap: 0.5rem;
    }
    
    .modal-footer .btn {
        width: 100% !important;
        margin: 0 !important;
    }
    
    /* Payment Proof Modal */
    .modal-lg .modal-body img {
        max-width: 100% !important;
        max-height: 300px !important;
    }
    
    .modal-lg .modal-body .btn {
        width: 100% !important;
        margin-bottom: 0.5rem !important;
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
    
    /* Filter Form - 2 columns on tablet */
    .card-body .row .col-md-3 {
        width: 50% !important;
        max-width: 50% !important;
    }
    
    .card-body .row .col-md-2 {
        width: 50% !important;
        max-width: 50% !important;
    }
}
</style>

<script>
// Ensure sidebar toggle works on this page
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        // Remove any existing event listeners and add fresh one
        sidebarToggle.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof toggleSidebar === 'function') {
                toggleSidebar();
            } else {
                // Fallback if function not loaded yet
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                if (sidebar && overlay) {
                    const isOpen = sidebar.classList.contains('show');
                    if (isOpen) {
                        sidebar.classList.remove('show');
                        overlay.classList.remove('show');
                    } else {
                        sidebar.classList.add('show');
                        overlay.classList.add('show');
                    }
                }
            }
        };
    }
});
</script>

<?php include 'inc/footer_sidebar.php'; ?>
