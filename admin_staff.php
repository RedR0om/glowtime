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

<div class="d-flex justify-content-between align-items-center mb-4 page-header">
    <div>
        <h1 class="h2 text-salon mb-0"><i class="bi bi-person-badge"></i> Stylists</h1>
        <p class="text-muted mb-0">Manage stylists (no login) and record absences.</p>
    </div>
    <div>
        <a href="admin_staff_form.php" class="btn btn-salon">Add Stylist</a>
        <a href="staff_attendance.php" class="btn btn-outline-salon d-none d-md-inline-block">Manage Absences</a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-people"></i> All Stylists</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($staff)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-person-x fs-1 d-block mb-2"></i>
                        No stylists found.
                    </div>
                <?php else: ?>
                    <!-- Desktop Table View -->
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staff as $s): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($s['staff_id']) ?></span></td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($s['staff_name']) ?></div>
                                        </td>
                                        <td>
                                            <?php if (($s['is_active'] ?? '') === 'Yes' || ($s['is_active'] ?? '') === '1'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex gap-2 justify-content-center">
                                                <a href="admin_staff_form.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-salon" title="Edit">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                                <a href="admin_staff_delete.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete stylist?')" title="Delete">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Mobile Card View -->
                    <div class="d-md-none staff-cards">
                        <?php foreach ($staff as $s): ?>
                            <div class="staff-card-mobile">
                                <div class="card-body-mobile">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-1 fw-bold text-salon">
                                                <i class="bi bi-person-badge"></i> <?= htmlspecialchars($s['staff_name']) ?>
                                            </h6>
                                            <span class="badge bg-secondary">ID: <?= htmlspecialchars($s['staff_id']) ?></span>
                                        </div>
                                        <div>
                                            <?php if (($s['is_active'] ?? '') === 'Yes' || ($s['is_active'] ?? '') === '1'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="actions-mobile d-flex gap-2">
                                        <a href="admin_staff_form.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-salon flex-fill">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <a href="admin_staff_delete.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-danger flex-fill" onclick="return confirm('Delete stylist?')">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-calendar-x"></i> Upcoming Absences (30 days)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($upcoming)): ?>
                    <div class="text-center text-muted py-3">
                        <i class="bi bi-check-circle fs-4 d-block mb-2"></i>
                        No upcoming absences.
                    </div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($upcoming as $a): ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <?php
                                            $displayName = $a['staff_name'] 
                                                         ?? $a['assigned_staff_id'] 
                                                         ?? $a[' assigned_staff_id'] 
                                                         ?? $a['assigned_staff'] 
                                                         ?? '';
                                        ?>
                                        <div class="fw-bold mb-1"><?= htmlspecialchars((string)$displayName, ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php if (!empty($a['reason'])): ?>
                                            <small class="text-muted d-block mb-1">
                                                <i class="bi bi-info-circle"></i> <?= htmlspecialchars((string)$a['reason'], ENT_QUOTES, 'UTF-8') ?>
                                            </small>
                                        <?php endif; ?>
                                        <span class="badge bg-secondary">
                                            <i class="bi bi-calendar"></i> <?= date('M d, Y', strtotime($a['date_absent'])) ?>
                                        </span>
                                    </div>
                                    <div class="ms-2">
                                        <a href="staff_attendance_delete.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-link text-danger" onclick="return confirm('Remove absence?')" title="Remove">
                                            <i class="bi bi-x-circle"></i>
                                        </a>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Mobile Responsive Styles */
@media (max-width: 768px) {
    /* Main content - Full width on mobile with minimal side padding */
    .main-content {
        width: 100% !important;
        max-width: 100% !important;
        margin-left: 0 !important;
        padding: 1rem 0.5rem !important;
        box-sizing: border-box;
    }
    
    /* Mobile header - Full width with minimal side padding */
    .mobile-header {
        margin: -1rem -0.5rem 1rem -0.5rem !important;
        padding: 1rem 0.5rem !important;
        gap: 0.5rem;
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
    
    .row .col-md-8,
    .row .col-md-4 {
        width: 100% !important;
        max-width: 100% !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin-bottom: 1rem !important;
    }
    
    /* Cards - Compact */
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
    
    /* Mobile Staff Cards */
    .staff-cards {
        padding: 0.5rem;
    }
    
    .staff-card-mobile {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        margin-bottom: 0.75rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .staff-card-mobile .card-body-mobile {
        padding: 0.75rem;
    }
    
    .staff-card-mobile h6 {
        font-size: 0.9rem;
    }
    
    .staff-card-mobile .badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
    }
    
    .staff-card-mobile .actions-mobile {
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid #f0f0f0;
    }
    
    .staff-card-mobile .actions-mobile .btn {
        font-size: 0.8rem;
        padding: 0.4rem 0.75rem;
    }
    
    /* Absences List */
    .list-group-item {
        padding: 0.75rem !important;
        font-size: 0.85rem;
    }
    
    .list-group-item .fw-bold {
        font-size: 0.9rem;
    }
    
    .list-group-item .badge {
        font-size: 0.7rem;
        padding: 0.3rem 0.6rem;
    }
    
    .list-group-item .btn-sm {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
    
    /* Empty States */
    .text-center.text-muted {
        font-size: 0.9rem;
    }
    
    .text-center.text-muted i {
        font-size: 2.5rem !important;
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
}
</style>

<?php include 'inc/footer_sidebar.php'; ?>