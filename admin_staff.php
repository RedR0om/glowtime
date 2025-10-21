<?php
require_once 'inc/bootstrap.php';
require_once 'staff.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}
$_SESSION['user_name'] = get_user_name($_SESSION['user_id']);

$staff = get_all_staff();
$upcoming = get_upcoming_attendance(30);

include 'inc/header_sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 text-salon mb-0"><i class="bi bi-person-badge"></i> Stylists</h1>
        <p class="text-muted mb-0">Manage stylists (no login) and record absences.</p>
    </div>
    <div>
        <a href="admin_staff_form.php" class="btn btn-salon">Add Stylist</a>
        <a href="staff_attendance.php" class="btn btn-outline-salon">Manage Absences</a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">All Stylists</div>
            <div class="card-body p-0">
                <?php if (empty($staff)): ?>
                    <div class="p-4 text-muted">No stylists found.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="table-light">
                                <tr><th>ID</th><th>Name</th><th>Active</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staff as $s): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($s['staff_id']) ?></td>
                                        <td><?= htmlspecialchars($s['staff_name']) ?></td>
                                        <td><?= htmlspecialchars($s['is_active'] ?? '') ?></td>
                                        <td>
                                            <a href="admin_staff_form.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-salon">Edit</a>
                                            <a href="admin_staff_delete.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete stylist?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Upcoming Absences (30 days)</div>
            <div class="card-body">
                <?php if (empty($upcoming)): ?>
                    <div class="text-muted">No upcoming absences.</div>
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