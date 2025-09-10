<?php
require_once 'inc/bootstrap.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: login.php');
    exit;
}

$clientId = $_SESSION['user_id'];

// Handle cancellation
if (isset($_GET['cancel'])) {
    $id = (int)$_GET['cancel'];
    $stmt = pdo()->prepare("UPDATE appointments SET status='cancelled' WHERE id=? AND client_id=? AND status='pending'");
    $stmt->execute([$id, $clientId]);
    header("Location: client_history.php");
    exit;
}

// Fetch appointments
$stmt = pdo()->prepare("
    SELECT a.id, a.start_at, a.status, s.name AS service_name
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    WHERE a.client_id=?
    ORDER BY a.start_at DESC
");
$stmt->execute([$clientId]);
$appointments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Appointments</title>
  <style>
    body { font-family:"Segoe UI",sans-serif; background:#faf5ff; margin:0; padding:20px; }
    h1 { color:#ec4899; }
    .timeline { position:relative; margin:20px 0; padding-left:40px; }
    .timeline::before { content:""; position:absolute; left:15px; top:0; bottom:0; width:4px; background:#ec4899; border-radius:2px; }
    .event { background:white; margin-bottom:20px; padding:15px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.1); position:relative; transition:transform .2s; }
    .event:hover { transform:scale(1.02); }
    .event::before { content:""; position:absolute; left:-28px; top:20px; width:16px; height:16px; background:#a21caf; border-radius:50%; border:3px solid #faf5ff; }
    .event h3 { margin:0; color:#a21caf; }
    .date { color:#555; margin:5px 0; font-size:0.95em; }
    .status { display:inline-block; font-weight:bold; padding:3px 10px; border-radius:8px; font-size:0.85em; margin-top:5px; }
    .pending { background:#fef3c7; color:#d97706; }
    .confirmed { background:#d1fae5; color:#059669; }
    .cancelled { background:#fee2e2; color:#dc2626; }
    .reminder { background:#fef08a; color:#854d0e; font-size:0.8em; padding:2px 6px; border-radius:6px; margin-left:6px; }
    .btn-cancel { display:inline-block; margin-top:8px; background:#dc2626; color:white; padding:5px 12px; border-radius:6px; text-decoration:none; font-size:0.85em; transition:background .2s; }
    .btn-cancel:hover { background:#b91c1c; }
  </style>
</head>
<body>
  <h1>🗂️ My Appointments</h1>
  <div class="timeline">
    <?php if ($appointments): ?>
      <?php foreach ($appointments as $a): ?>
        <?php
          $dateFormatted = date("M d, Y - h:i A", strtotime($a['start_at']));
          $isSoon = (strtotime($a['start_at']) - time()) <= 86400 && $a['status'] === 'confirmed'; // within 24h
        ?>
        <div class="event">
          <h3><?= htmlspecialchars($a['service_name']) ?></h3>
          <p class="date">📅 <?= $dateFormatted ?>
            <?php if ($isSoon): ?><span class="reminder">⏰ Soon!</span><?php endif; ?>
          </p>
          <span class="status <?= htmlspecialchars($a['status']) ?>">
            <?= ucfirst($a['status']) ?>
          </span><br>
          <?php if ($a['status']==='pending'): ?>
            <a class="btn-cancel" href="?cancel=<?= $a['id'] ?>" onclick="return confirm('Cancel this appointment?');">Cancel</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p>No appointments yet.</p>
    <?php endif; ?>
  </div>
</body>
</html>
