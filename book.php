<?php
require_once 'inc/bootstrap.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    header('Location: client_dashboard.php'); 
    exit; 
}

// Only logged-in clients
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: login.php'); 
    exit;
}

$client_id = $_SESSION['user_id'];
$service_id = intval($_POST['service_id'] ?? 0);
$start_raw  = $_POST['start_at'] ?? '';

$pdo = pdo();

// ✅ Get service duration
$stmt = $pdo->prepare("SELECT duration_minutes FROM services WHERE id=?");
$stmt->execute([$service_id]);
$duration = $stmt->fetchColumn();

if (!$duration) { 
    die("❌ Service not found."); 
}

// ✅ Parse start time
$start = DateTime::createFromFormat('Y-m-d\TH:i', $start_raw);
if (!$start) { 
    die("❌ Invalid date/time format."); 
}

$end = (clone $start)->modify("+{$duration} minutes");

// ✅ Check for conflicts
if (has_conflict(null, $start, $end)) {
    die("⚠️ That time slot is already taken. Please pick another.");
}

// ✅ Insert appointment
$stmt = $pdo->prepare("INSERT INTO appointments 
    (client_id, service_id, start_at, end_at, status, created_at) 
    VALUES (?, ?, ?, ?, 'pending', NOW())");
$stmt->execute([
    $client_id, 
    $service_id, 
    $start->format('Y-m-d H:i:s'), 
    $end->format('Y-m-d H:i:s')
]);

// ✅ Redirect back
header('Location: client_dashboard.php?success=1');
exit;
