<?php
require_once 'inc/bootstrap.php';
require_once 'staff.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}
$_SESSION['user_name'] = get_user_name($_SESSION['user_id']);

$allStaff = get_all_staff();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = trim($_POST['date_absent'] ?? '');
    $selected = $_POST['staff_ids'] ?? [];
    $reason = trim($_POST['reason'] ?? '');

    if ($date === '') $errors[] = 'Date is required.';
    if (empty($selected)) $errors[] = 'Select at least one stylist.';

    if (empty($errors)) {
        foreach ($selected as $staff_id_val) {
            create_attendance($staff_id_val, $date, $reason ? $reason : null);
        }
        header('Location: staff_attendance.php');
        exit;
    }
}

$upcoming = get_upcoming_attendance(90);

include 'inc/header_sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Record Stylist Absence</h1>
        <p class="text-muted mb-0">Select stylist(s) and the date they will be absent.</p>
    </div>
    <div>
        <a href="admin_staff.php" class="btn btn-outline-salon">Back to Stylists</a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Absent Date</label>
                        <input type="date" name="date_absent" class="form-control" value="<?= htmlspecialchars($_POST['date_absent'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Stylists</label>
                        <select name="staff_ids[]" class="form-select" multiple size="6">
                            <?php foreach ($allStaff as $s): ?>
                                <option value="<?= htmlspecialchars($s['staff_id']) ?>" <?= in_array($s['staff_id'], $_POST['staff_ids'] ?? []) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['staff_name']) ?> (<?= htmlspecialchars($s['staff_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Hold Ctrl / Cmd to select multiple.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Reason (optional)</label>
                        <input name="reason" class="form-control" value="<?= htmlspecialchars($_POST['reason'] ?? '') ?>">
                    </div>

                    <button class="btn btn-salon">Record Absence</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Upcoming Absences</div>
            <div class="card-body">
                <?php if (empty($upcoming)): ?>
                    <div class="text-muted">No absences recorded.</div>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach ($upcoming as $a): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <?php
                                        // safe display name: prefer resolved staff_name, fall back to any assigned_staff variants,
                                        // ensure a string is passed to htmlspecialchars to avoid deprecation/warnings.
                                        $displayName = $a['staff_name'] 
                                                     ?? $a['assigned_staff_id'] 
                                                     ?? $a[' assigned_staff_id'] 
                                                     ?? $a['assigned_staff'] 
                                                     ?? '';
                                    ?>
                                    <div class="fw-bold"><?= htmlspecialchars((string)$displayName, ENT_QUOTES, 'UTF-8') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars((string)($a['reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-secondary"><?= date('M d, Y', strtotime($a['date_absent'])) ?></span>
                                    <a href="staff_attendance_delete.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-link text-danger" onclick="return confirm('Remove absence?')">Remove</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'inc/footer_sidebar.php'; ?>