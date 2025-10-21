<?php
require_once 'inc/bootstrap.php';
require_once 'staff.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}
$_SESSION['user_name'] = get_user_name($_SESSION['user_id']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$staff = $id ? get_staff_by_id($id) : null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'staff_id' => trim($_POST['staff_id'] ?? ''),
        'staff_name' => trim($_POST['staff_name'] ?? ''),
        'is_active' => trim($_POST['is_active'] ?? 'Yes')
    ];

    if ($data['staff_id'] === '') $errors[] = 'Staff ID is required.';
    if ($data['staff_name'] === '') $errors[] = 'Name is required.';

    if (empty($errors)) {
        if ($id && $staff) {
            update_staff($id, $data);
        } else {
            create_staff($data);
        }
        header('Location: admin_staff.php');
        exit;
    }
}

include 'inc/header_sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-0"><?= $staff ? 'Edit Stylist' : 'Add Stylist' ?></h1></div>
    <div><a href="admin_staff.php" class="btn btn-outline-salon">Back</a></div>
</div>

<div class="card">
    <div class="card-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label class="form-label">Staff ID (unique)</label>
                <input name="staff_id" class="form-control" value="<?= htmlspecialchars($staff['staff_id'] ?? ($_POST['staff_id'] ?? '')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Name</label>
                <input name="staff_name" class="form-control" value="<?= htmlspecialchars($staff['staff_name'] ?? ($_POST['staff_name'] ?? '')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Active</label>
                <select name="is_active" class="form-select">
                    <?php $val = $staff['is_active'] ?? ($_POST['is_active'] ?? 'Yes'); ?>
                    <option value="Yes" <?= $val === 'Yes' ? 'selected' : '' ?>>Yes</option>
                    <option value="No" <?= $val === 'No' ? 'selected' : '' ?>>No</option>
                </select>
            </div>
            <button class="btn btn-salon"><?= $staff ? 'Save Changes' : 'Create Stylist' ?></button>
        </form>
    </div>
</div>

<?php include 'inc/footer_sidebar.php'; ?>