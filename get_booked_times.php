<?php
require_once 'inc/bootstrap.php';
header('Content-Type: application/json');

// Validate input
if (!isset($_GET['service_id']) || !isset($_GET['date'])) {
    echo json_encode([]);
    exit;
}

$service_id = (int) $_GET['service_id'];
$date = $_GET['date'];

// Get service duration
$stmt = pdo()->prepare("SELECT duration_minutes FROM services WHERE id=?");
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if (!$service) {
    echo json_encode([]);
    exit;
}

$duration = $service['duration_minutes'];

// Get booked slots for this date
$stmt = pdo()->prepare("SELECT start_at, end_at FROM appointments 
    WHERE service_id=? AND DATE(start_at)=? 
    AND status IN ('pending','confirmed')");
$stmt->execute([$service_id, $date]);
$appointments = $stmt->fetchAll();

$bookedTimes = [];
foreach ($appointments as $a) {
    $bookedTimes[] = [
        "start" => date("H:i", strtotime($a['start_at'])),
        "end"   => date("H:i", strtotime($a['end_at']))
    ];
}

// Return as JSON
echo json_encode($bookedTimes);
