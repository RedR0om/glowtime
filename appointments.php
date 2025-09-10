<?php
require_once 'inc/bootstrap.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

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
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin - Appointments</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    :root{--purple:#a21caf;--muted:#f3e8ff}
    body { font-family: "Segoe UI", sans-serif; background:#faf5ff; margin:0; padding:22px; color:#111; }
    h1 { text-align:center; color:var(--purple); margin-bottom:18px; }
    .notice { max-width:1200px; margin:12px auto; padding:12px 16px; border-radius:8px; font-weight:600; }
    .success { background:#d1fae5; color:#065f46; }
    .error { background:#fee2e2; color:#991b1b; }
    .table-container { max-width:1200px; margin:0 auto 40px; background:#fff; border-radius:12px; padding:14px; box-shadow:0 6px 22px rgba(0,0,0,0.06); overflow:auto; }
    table { width:100%; border-collapse:collapse; font-size:14px; }
    th, td { padding:10px 12px; text-align:left; border-bottom:1px solid var(--muted); vertical-align:middle; }
    th { background:var(--purple); color:#fff; font-size:13px; text-transform:uppercase; letter-spacing:0.6px; }
    tr:hover td { background:#fff7ff; }
    /* column widths for precision */
    th.col-ref { width:16%; }      td.col-ref {}
    th.col-service { width:16%; }
    th.col-info { width:18%; }
    th.col-schedule { width:14%; }
    th.col-payment { width:16%; }
    th.col-status { width:10%; text-align:center; }
    th.col-action { width:10%; text-align:center; }

    .small { font-size:12px; color:#444; display:block; margin-top:4px; }
    .muted { color:#666; font-size:13px; }
    .proof-link { color:#2563eb; text-decoration:none; font-weight:600; }
    .proof-link:hover { text-decoration:underline; }
    .status-badge { display:inline-block; padding:6px 9px; border-radius:12px; font-weight:700; font-size:12px; text-transform:capitalize; }
    .pending { background:#fff7cc; color:#92400e; }
    .verified { background:#d1fae5; color:#065f46; }
    .rejected { background:#fee2e2; color:#991b1b; }
    .confirmed { background:#bbf7d0; color:#166534; }
    .cancelled { background:#fecaca; color:#7f1d1d; }
    .btn { display:inline-block; padding:7px 10px; border-radius:8px; font-weight:700; border:none; cursor:pointer; text-decoration:none; margin:0 4px; }
    .btn-verify { background:#10b981; color:#fff; }
    .btn-reject { background:#ef4444; color:#fff; }
    .btn-verify:hover { opacity:0.95; }
    .btn-reject:hover { opacity:0.95; }
    .no-data { text-align:center; padding:28px; color:#666; }
  </style>
  <script>
    function confirmAction(action) {
      if (action === 'reject') {
        return confirm('Reject this booking? This will cancel the appointment. Continue?');
      }
      return true;
    }
  </script>
</head>
<body>
  <h1>📋 Manage Appointments</h1>

  <?php if ($success): ?>
    <div class="notice success"><?= h($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="notice error"><?= h($error) ?></div>
  <?php endif; ?>

  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th class="col-ref">Booking Ref / Client</th>
          <th class="col-service">Service / Style</th>
          <th class="col-info">Booking Info</th>
          <th class="col-schedule">Schedule</th>
          <th class="col-payment">Payment</th>
          <th class="col-status">Payment</th>
          <th class="col-status">Appointment</th>
          <th class="col-action">Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($appointments)): ?>
        <tr><td colspan="8" class="no-data">No appointments found.</td></tr>
      <?php else: ?>
        <?php foreach ($appointments as $a): ?>
          <tr>
            <!-- Booking Ref + Client -->
            <td class="col-ref">
              <strong><?= h($a['booking_ref'] ?? '-') ?></strong>
              <span class="small"><?= h($a['client'] ?? '-') ?></span>
            </td>

            <!-- Service + Style -->
            <td class="col-service">
              <?= h($a['service'] ?? '-') ?>
              <span class="small"><?= !empty($a['style']) ? h($a['style']) : '-' ?></span>
            </td>

            <!-- Booking Info -->
            <td class="col-info">
              <strong><?= ucfirst(h($a['booking_type'] ?? 'salon')) ?></strong>
              <?php if (($a['booking_type'] ?? '') === 'home' && !empty($a['location_address'])): ?>
                <span class="small">📍 <?= h($a['location_address']) ?></span>
              <?php endif; ?>
            </td>

            <!-- Schedule -->
            <td class="col-schedule">
              <?php
                $start = !empty($a['start_at']) ? date("M d, Y", strtotime($a['start_at'])) : '-';
                $startTime = !empty($a['start_at']) ? date("h:i A", strtotime($a['start_at'])) : '-';
                $endTime = !empty($a['end_at']) ? date("h:i A", strtotime($a['end_at'])) : '-';
              ?>
              <strong><?= h($start) ?></strong>
              <span class="small"><?= h($startTime) ?> → <?= h($endTime) ?></span>
            </td>

            <!-- Payment summary -->
            <td class="col-payment">
              <strong>₱<?= number_format((float)($a['down_payment'] ?? 0), 2) ?></strong>
              <?php if (($a['booking_type'] ?? '') === 'home'): ?>
                <span class="small">+₱<?= number_format((float)($a['transport_fee'] ?? 0), 2) ?> transport</span>
              <?php endif; ?>
              <div class="small">
                <?php if (!empty($a['payment_proof'])): ?>
                  <a class="proof-link" href="<?= h($a['payment_proof']) ?>" target="_blank">📷 View proof</a>
                <?php else: ?>
                  <em>No proof</em>
                <?php endif; ?>
              </div>
            </td>

            <!-- Payment status -->
            <td class="col-status" style="text-align:center;">
              <span class="status-badge <?= statusBadgeClass($a['payment_status'] ?? 'pending') ?>">
                <?= h($a['payment_status'] ?? 'pending') ?>
              </span>
            </td>

            <!-- Appointment status -->
            <td class="col-status" style="text-align:center;">
              <span class="status-badge <?= statusBadgeClass($a['status'] ?? 'pending') ?>">
                <?= h($a['status'] ?? 'pending') ?>
              </span>
            </td>

            <!-- Actions -->
            <td class="col-action" style="text-align:center;">
              <?php if (($a['payment_status'] ?? '') === 'pending'): ?>
                <form method="post" style="display:inline;" onsubmit="return confirmAction('verify')">
                  <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                  <input type="hidden" name="action" value="verify">
                  <button type="submit" class="btn btn-verify">✅ Verify</button>
                </form>

                <form method="post" style="display:inline;" onsubmit="return confirmAction('reject')">
                  <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                  <input type="hidden" name="action" value="reject">
                  <button type="submit" class="btn btn-reject">❌ Reject</button>
                </form>
              <?php else: ?>
                <span class="small">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
