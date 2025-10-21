<?php
// Staff + attendance helpers used by admin pages.

function get_all_staff(): array {
    $stmt = pdo()->prepare("SELECT * FROM staff ORDER BY staff_name ASC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_staff_by_id(int $id): ?array {
    $stmt = pdo()->prepare("SELECT * FROM staff WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function create_staff(array $data): int {
    $stmt = pdo()->prepare("INSERT INTO staff (staff_id, staff_name, is_active) VALUES (?, ?, ?)");
    $stmt->execute([
        $data['staff_id'],
        $data['staff_name'],
        $data['is_active'] ?? 'Yes'
    ]);
    return (int) pdo()->lastInsertId();
}

function update_staff(int $id, array $data): bool {
    $stmt = pdo()->prepare("UPDATE staff SET staff_id = ?, staff_name = ?, is_active = ? WHERE id = ?");
    return $stmt->execute([
        $data['staff_id'],
        $data['staff_name'],
        $data['is_active'] ?? 'Yes',
        $id
    ]);
}

function delete_staff(int $id): bool {
    $stmt = pdo()->prepare("DELETE FROM staff WHERE id = ?");
    return $stmt->execute([$id]);
}

/* Attendance helpers
   staff_attendance.assigned_staff_id stores staff.staff_id (e.g. "2025-00001")
*/
function create_attendance(string $assigned_staff_id, string $date_absent, ?string $reason = null): bool {
    $db = pdo();

    // try the normal/expected column name first (backticks safe)
    try {
        $stmt = $db->prepare("INSERT INTO staff_attendance (`assigned_staff_id`, `date_absent`, `reason`) VALUES (?, ?, ?)");
        return (bool)$stmt->execute([$assigned_staff_id, $date_absent, $reason]);
    } catch (PDOException $e) {
        // fallback: handle legacy/misnamed column that has a leading space in its name
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            $stmt = $db->prepare("INSERT INTO staff_attendance (` assigned_staff_id`, `date_absent`, `reason`) VALUES (?, ?, ?)");
            return (bool)$stmt->execute([$assigned_staff_id, $date_absent, $reason]);
        }
        throw $e;
    }
}

function get_upcoming_attendance(int $days = 30): array {
    $stmt = pdo()->prepare("
        SELECT *
        FROM staff_attendance
        WHERE date_absent >= CURDATE()
          AND date_absent <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
          AND (is_deleted = '0' OR is_deleted = '' OR is_deleted IS NULL)
        ORDER BY date_absent ASC
    ");
    $stmt->execute([$days]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        return [];
    }

    // lookup staff by staff_id (string) or numeric id
    $staffLookup = pdo()->prepare("SELECT id, staff_id, staff_name FROM staff WHERE staff_id = ? OR id = ? LIMIT 1");

    $result = [];
    foreach ($rows as $r) {
        $r['staff_name'] = null;
        $assigned = $r['assigned_staff_id'] ?? ($r['assigned_staff'] ?? null); // try common variants
        if ($assigned !== null && $assigned !== '') {
            $numericId = is_numeric($assigned) ? (int)$assigned : 0;
            $staffLookup->execute([$assigned, $numericId]);
            $s = $staffLookup->fetch(PDO::FETCH_ASSOC);
            if ($s) {
                $r['staff_name'] = $s['staff_name'];
            }
        }
        $result[] = $r;
    }

    return $result;
}

function soft_delete_attendance(int $id): bool {
    $stmt = pdo()->prepare("UPDATE staff_attendance SET is_deleted = '1' WHERE id = ?");
    return $stmt->execute([$id]);
}
