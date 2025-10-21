<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'inc/bootstrap.php';
require_once 'staff.php';

header('Content-Type: application/json');

try {
    // Get the date from the query string
    $date = $_GET['date'] ?? '';

    if (empty($date)) {
        echo json_encode(['error' => 'No date provided', 'data' => []]);
        exit;
    }

    // Get all staff
    $allStaff = get_all_staff();

    // Get absences for the specified date
    // Try to handle both column names (with and without space)
    $absentStaffIds = [];
    try {
        $stmt = pdo()->prepare("
            SELECT assigned_staff_id 
            FROM staff_attendance 
            WHERE date_absent = ? 
            AND (is_deleted = '0' OR is_deleted = '' OR is_deleted IS NULL)
        ");
        $stmt->execute([$date]);
        $absentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $absentStaffIds = array_column($absentRows, 'assigned_staff_id');
    } catch (PDOException $e) {
        // Try with space in column name
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            error_log("Trying column with space");
            $stmt = pdo()->prepare("
                SELECT ` assigned_staff_id` as assigned_staff_id
                FROM staff_attendance 
                WHERE date_absent = ? 
                AND (is_deleted = '0' OR is_deleted = '' OR is_deleted IS NULL)
            ");
            $stmt->execute([$date]);
            $absentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $absentStaffIds = array_column($absentRows, 'assigned_staff_id');
        } else {
            throw $e;
        }
    }

    // Debug: Log what we found
    error_log("Date: " . $date);
    error_log("All staff count: " . count($allStaff));
    error_log("Absent staff IDs: " . json_encode($absentStaffIds));

    // Filter out absent staff (keep active and those not absent)
    $availableStaff = [];
    foreach ($allStaff as $staff) {
        // Check if staff is active
        $isActive = ($staff['is_active'] === 'Yes' || $staff['is_active'] === '1' || $staff['is_active'] === 1);
        
        // Check if staff is not absent
        $isNotAbsent = !in_array($staff['staff_id'], $absentStaffIds);
        
        error_log("Staff ID: {$staff['staff_id']}, Name: {$staff['staff_name']}, Active: " . ($isActive ? 'Yes' : 'No') . ", Not Absent: " . ($isNotAbsent ? 'Yes' : 'No'));
        
        if ($isActive && $isNotAbsent) {
            $availableStaff[] = [
                'id' => $staff['id'],
                'staff_id' => $staff['staff_id'],
                'staff_name' => $staff['staff_name']
            ];
        }
    }

    error_log("Available staff count: " . count($availableStaff));
    
    echo json_encode($availableStaff);
} catch (Exception $e) {
    // Return error details for debugging
    echo json_encode(['error' => $e->getMessage(), 'data' => []]);
}

