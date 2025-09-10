<?php
require_once 'inc/bootstrap.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

/* ---------------------------
   Helpers: flash, escape, csrf
   --------------------------- */
if (!isset($_SESSION['flash'])) $_SESSION['flash'] = [];
function set_flash($k, $v) { $_SESSION['flash'][$k] = $v; }
function get_flash($k) { $v = $_SESSION['flash'][$k] ?? null; if (isset($_SESSION['flash'][$k])) unset($_SESSION['flash'][$k]); return $v; }
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
$CSRF = $_SESSION['csrf_token'];

/* ---------------------------
   Image helpers
   --------------------------- */
function services_image_dir() {
    $dir = __DIR__ . '/images/services';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir;
}

function find_service_image($id) {
    $dir = services_image_dir();
    $candidates = glob($dir . "/service{$id}.*");
    if ($candidates && file_exists($candidates[0])) {
        // return web path relative to project root
        $filename = str_replace($_SERVER['DOCUMENT_ROOT'], '', $candidates[0]);
        // if above fails, return using relative path
        return (strpos($filename, '/') === 0 ? $filename : 'images/services/' . basename($candidates[0]));
    }
    // fallback default
    return 'images/default.jpg';
}

function remove_service_images($id) {
    $dir = services_image_dir();
    foreach (glob($dir . "/service{$id}.*") as $f) {
        if (is_file($f)) @unlink($f);
    }
}

function handle_image_upload(array $file, int $serviceId) : bool {
    if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) return false;

    // validate size (2.5MB) and MIME
    if ($file['size'] > 2_500_000) return false;

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $ext = match($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default => null
    };
    if ($ext === null) return false;

    $dir = services_image_dir();
    // remove old images
    remove_service_images($serviceId);

    $dest = $dir . "/service{$serviceId}." . $ext;
    return move_uploaded_file($file['tmp_name'], $dest);
}

/* ---------------------------
   Actions (POST)
   --------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    // CSRF check
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        set_flash('error', 'Invalid CSRF token.');
        header('Location: services.php');
        exit;
    }

    try {
        if ($action === 'add_service') {
            $name = trim($_POST['name'] ?? '');
            $price = $_POST['price'] ?? '';
            $duration = $_POST['duration'] ?? '';

            // validation
            if ($name === '' || strlen($name) > 191) { throw new Exception('Service name required (max 191 chars).'); }
            if (!is_numeric($price) || $price < 0) { throw new Exception('Invalid price.'); }
            if (!ctype_digit((string)$duration) || (int)$duration <= 0) { throw new Exception('Duration must be a positive integer.'); }

            $stmt = pdo()->prepare("INSERT INTO services (name, price, duration_minutes) VALUES (:name, :price, :duration)");
            $stmt->execute([':name'=>$name, ':price'=>$price, ':duration'=>$duration]);
            $serviceId = (int) pdo()->lastInsertId();

            if (!empty($_FILES['image']['name'])) {
                handle_image_upload($_FILES['image'], $serviceId);
            }

            set_flash('success', 'Service added successfully.');
            header('Location: services.php');
            exit;
        }

        if ($action === 'edit_service') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $price = $_POST['price'] ?? '';
            $duration = $_POST['duration'] ?? '';

            if ($id <= 0) throw new Exception('Invalid service ID.');
            if ($name === '' || strlen($name) > 191) { throw new Exception('Service name required (max 191 chars).'); }
            if (!is_numeric($price) || $price < 0) { throw new Exception('Invalid price.'); }
            if (!ctype_digit((string)$duration) || (int)$duration <= 0) { throw new Exception('Duration must be a positive integer.'); }

            $stmt = pdo()->prepare("UPDATE services SET name = :name, price = :price, duration_minutes = :duration WHERE id = :id");
            $stmt->execute([':name'=>$name, ':price'=>$price, ':duration'=>$duration, ':id'=>$id]);

            if (!empty($_FILES['image']['name'])) {
                handle_image_upload($_FILES['image'], $id);
            }

            set_flash('success', 'Service updated successfully.');
            header('Location: services.php');
            exit;
        }

        if ($action === 'delete_service') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid service ID.');

            $stmt = pdo()->prepare("DELETE FROM services WHERE id = ?");
            $stmt->execute([$id]);
            remove_service_images($id);

            set_flash('success', 'Service deleted.');
            header('Location: services.php');
            exit;
        }
    } catch (Exception $ex) {
        set_flash('error', $ex->getMessage());
        header('Location: services.php');
        exit;
    }
}

/* ---------------------------
   Listing: search, sort, pagination
   --------------------------- */
$q = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'name_asc';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];
if ($q !== '') {
    $where = "WHERE name LIKE :q";
    $params[':q'] = "%$q%";
}

$orderBy = match($sort) {
    'name_desc'     => 'ORDER BY name DESC',
    'price_asc'     => 'ORDER BY price ASC',
    'price_desc'    => 'ORDER BY price DESC',
    'duration_asc'  => 'ORDER BY duration_minutes ASC',
    'duration_desc' => 'ORDER BY duration_minutes DESC',
    default         => 'ORDER BY name ASC'
};

// total count
$totalStmt = pdo()->prepare("SELECT COUNT(*) FROM services $where");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();
$totalPages = (int) ceil($total / $perPage);

// fetch page
$sql = "SELECT * FROM services $where $orderBy LIMIT $perPage OFFSET $offset";
$stmt = pdo()->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------------------------
   Flash messages
   --------------------------- */
$success = get_flash('success');
$error = get_flash('error');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — Manage Services</title>
<style>
  :root{--purple:#a21caf;--muted:#f3e8ff}
  body{font-family:Inter, "Segoe UI",system-ui,Arial; background:#faf5ff; margin:0; padding:18px; color:#111}
  header{max-width:1200px;margin:0 auto 18px;display:flex;align-items:center;justify-content:space-between;gap:12px}
  h1{color:var(--purple);margin:0}
  .controls{display:flex;gap:8px;align-items:center}
  .search {padding:8px 10px;border-radius:8px;border:1px solid #ddd}
  .select {padding:8px;border-radius:8px;border:1px solid #ddd}
  .btn-add{background:var(--purple);color:#fff;padding:10px 14px;border-radius:10px;border:none;cursor:pointer;font-weight:700}
  .notice{max-width:1200px;margin:12px auto;padding:12px;border-radius:10px;font-weight:700}
  .success{background:#d1fae5;color:#065f46}
  .error{background:#fee2e2;color:#991b1b}

  .grid-wrap{max-width:1200px;margin:18px auto}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px}
  .card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,0.06);display:flex;flex-direction:column}
  .thumb{height:140px;background:#eee url('images/default.jpg') center/cover no-repeat}
  .card-body{padding:12px;display:flex;flex-direction:column;gap:8px}
  .title{font-weight:800;color:var(--purple);font-size:16px}
  .meta{color:#444;font-size:14px}
  .small{font-size:13px;color:#666}
  .card-actions{display:flex;gap:8px;margin-top:auto}
  .btn{padding:8px 10px;border-radius:8px;border:none;cursor:pointer;font-weight:700}
  .btn-edit{background:#ec4899;color:#fff}
  .btn-delete{background:#ef4444;color:#fff}

  /* modal */
  .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;padding:18px;z-index:40}
  .modal.is-open{display:flex}
  .modal-panel{background:#fff;border-radius:12px;padding:18px;max-width:540px;width:100%;box-shadow:0 12px 36px rgba(0,0,0,0.18)}
  label{display:block;font-weight:700;margin-top:10px}
  input[type="text"], input[type="number"], input[type="file"]{width:100%;padding:8px;border-radius:8px;border:1px solid #ddd;margin-top:6px}
  .modal-actions{display:flex;gap:8px;margin-top:12px}
  .btn-cancel{background:#f3f4f6;border-radius:8px;padding:8px 10px;border:1px solid #ddd}
  .muted{color:#666;font-size:13px}
  .pagination{display:flex;gap:8px;align-items:center;margin-top:12px}
  .page-btn{padding:6px 9px;border-radius:8px;background:#fff;border:1px solid #ddd;cursor:pointer}
</style>
<script>
  function openModal(id){document.getElementById(id)?.classList.add('is-open')}
  function closeModal(id){document.getElementById(id)?.classList.remove('is-open')}
  function confirmDelete(formId){ if (!confirm('Delete this service? This action cannot be undone.')) return false; document.getElementById(formId).submit(); }
</script>
</head>
<body>

<header>
  <h1>💇 Manage Services</h1>
  <div class="controls">
    <form method="get" style="display:flex;gap:8px;align-items:center">
      <input class="search" type="text" name="q" placeholder="Search services..." value="<?= h($q) ?>">
      <select class="select" name="sort" onchange="this.form.submit()">
        <option value="name_asc" <?= $sort==='name_asc'?'selected':'' ?>>Name ▲</option>
        <option value="name_desc" <?= $sort==='name_desc'?'selected':'' ?>>Name ▼</option>
        <option value="price_asc" <?= $sort==='price_asc'?'selected':'' ?>>Price ▲</option>
        <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Price ▼</option>
        <option value="duration_asc" <?= $sort==='duration_asc'?'selected':'' ?>>Duration ▲</option>
        <option value="duration_desc" <?= $sort==='duration_desc'?'selected':'' ?>>Duration ▼</option>
      </select>
      <input type="hidden" name="page" value="1">
    </form>

    <button class="btn-add" onclick="openModal('addModal')">➕ Add Service</button>
  </div>
</header>

<?php if ($success): ?><div class="notice success"><?= h($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= h($error) ?></div><?php endif; ?>

<div class="grid-wrap">
  <div class="grid">
    <?php if (empty($services)): ?>
      <div style="grid-column:1/-1;padding:28px;background:#fff;border-radius:12px;text-align:center;color:#666">No services found.</div>
    <?php endif; ?>

    <?php foreach ($services as $s): 
        $img = find_service_image((int)$s['id']);
    ?>
      <div class="card">
        <div class="thumb" style="background-image:url('<?= h($img) ?>')"></div>
        <div class="card-body">
          <div class="title"><?= h($s['name']) ?></div>
          <div class="meta">₱<?= number_format((float)$s['price'],2) ?> • <?= (int)$s['duration_minutes'] ?> mins</div>
          <div class="small muted">ID: <?= (int)$s['id'] ?></div>

          <div class="card-actions">
            <button class="btn btn-edit" onclick="openModal('editModal<?= (int)$s['id'] ?>')">Edit</button>

            <form id="deleteForm<?= (int)$s['id'] ?>" method="post" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= h($CSRF) ?>">
              <input type="hidden" name="action" value="delete_service">
              <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <button type="button" class="btn btn-delete" onclick="confirmDelete('deleteForm<?= (int)$s['id'] ?>')">Delete</button>
            </form>
          </div>
        </div>
      </div>

      <!-- Edit Modal -->
      <div id="editModal<?= (int)$s['id'] ?>" class="modal" aria-hidden="true">
        <div class="modal-panel">
          <h2>Edit Service</h2>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h($CSRF) ?>">
            <input type="hidden" name="action" value="edit_service">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">

            <label>Name</label>
            <input type="text" name="name" value="<?= h($s['name']) ?>" required maxlength="191">

            <label>Price</label>
            <input type="number" name="price" step="0.01" value="<?= h($s['price']) ?>" required>

            <label>Duration (minutes)</label>
            <input type="number" name="duration" value="<?= (int)$s['duration_minutes'] ?>" required>

            <label>Image (optional — JPG/PNG/WEBP, max 2.5MB)</label>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp">

            <div class="modal-actions">
              <button type="submit" class="btn btn-edit">Save changes</button>
              <button type="button" class="btn btn-cancel" onclick="closeModal('editModal<?= (int)$s['id'] ?>')">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <div style="max-width:1200px;margin:12px auto;display:flex;justify-content:center;align-items:center">
      <div class="pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <a class="page-btn" href="?<?= http_build_query(array_merge($_GET, ['page'=>$p])) ?>" style="<?= $p===$page ? 'background:#f3f4f6;border-color:#ccc;' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- Add Modal -->
<div id="addModal" class="modal" aria-hidden="true">
  <div class="modal-panel">
    <h2>Add Service</h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= h($CSRF) ?>">
      <input type="hidden" name="action" value="add_service">

      <label>Name</label>
      <input type="text" name="name" required maxlength="191">

      <label>Price</label>
      <input type="number" name="price" step="0.01" required>

      <label>Duration (minutes)</label>
      <input type="number" name="duration" required>

      <label>Image (optional — JPG/PNG/WEBP, max 2.5MB)</label>
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp">

      <div class="modal-actions">
        <button type="submit" class="btn btn-edit">Add Service</button>
        <button type="button" class="btn btn-cancel" onclick="closeModal('addModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

</body>
</html>
