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

function get_service_image_url($service) {
    // If service has image_url from Cloudinary, use it
    if (!empty($service['image_url'])) {
        return $service['image_url'];
    }
    
    // Fallback to local image if exists
    $dir = services_image_dir();
    $candidates = glob($dir . "/service{$service['id']}.*");
    if ($candidates && file_exists($candidates[0])) {
        $filename = str_replace($_SERVER['DOCUMENT_ROOT'], '', $candidates[0]);
        return (strpos($filename, '/') === 0 ? $filename : 'images/services/' . basename($candidates[0]));
    }
    
    // Default fallback
    return 'images/default.jpg';
}

function remove_service_images($id) {
    $dir = services_image_dir();
    foreach (glob($dir . "/service{$id}.*") as $f) {
        if (is_file($f)) @unlink($f);
    }
}

function handle_service_image_upload(array $file, int $serviceId) : array {
    if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'url' => '', 'error' => 'No file uploaded or upload error'];
    }

    // validate size (2.5MB)
    if ($file['size'] > 2_500_000) {
        return ['success' => false, 'url' => '', 'error' => 'File too large. Maximum size is 2.5MB.'];
    }

    // validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $mimeType = mime_content_type($file['tmp_name']);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'url' => '', 'error' => 'Invalid file type. Only JPG, PNG, and WEBP are allowed.'];
    }

    try {
        // Create a temporary $_FILES array for the uploadToCloudinary function
        $_FILES['temp_image'] = $file;
        
        // Upload to Cloudinary using existing function
        $uploadResult = uploadToCloudinary('temp_image', 'glowtime/service_images');
        
        // Clean up temp file reference
        unset($_FILES['temp_image']);
        
        if ($uploadResult['success']) {
            return ['success' => true, 'url' => $uploadResult['url'], 'error' => ''];
        } else {
            return ['success' => false, 'url' => '', 'error' => $uploadResult['error']];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'url' => '', 'error' => 'Upload failed: ' . $e->getMessage()];
    }
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

            // Handle image upload first
            $imageUrl = '';
            if (!empty($_FILES['image']['name'])) {
                $uploadResult = handle_service_image_upload($_FILES['image'], 0); // 0 since we don't have ID yet
                if (!$uploadResult['success']) {
                    throw new Exception('Image upload failed: ' . $uploadResult['error']);
                }
                $imageUrl = $uploadResult['url'];
            }

            // Add image_url column to the database (will be created if not exists)
            $stmt = pdo()->prepare("INSERT INTO services (name, price, duration_minutes, image_url) VALUES (:name, :price, :duration, :image_url)");
            $stmt->execute([':name'=>$name, ':price'=>$price, ':duration'=>$duration, ':image_url'=>$imageUrl]);
            $serviceId = (int) pdo()->lastInsertId();

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

            // Handle image upload if new image is provided
            $imageUrl = '';
            if (!empty($_FILES['image']['name'])) {
                $uploadResult = handle_service_image_upload($_FILES['image'], $id);
                if (!$uploadResult['success']) {
                    throw new Exception('Image upload failed: ' . $uploadResult['error']);
                }
                $imageUrl = $uploadResult['url'];
                
                // Update with new image URL
                $stmt = pdo()->prepare("UPDATE services SET name = :name, price = :price, duration_minutes = :duration, image_url = :image_url WHERE id = :id");
                $stmt->execute([':name'=>$name, ':price'=>$price, ':duration'=>$duration, ':image_url'=>$imageUrl, ':id'=>$id]);
            } else {
                // Update without changing image
                $stmt = pdo()->prepare("UPDATE services SET name = :name, price = :price, duration_minutes = :duration WHERE id = :id");
                $stmt->execute([':name'=>$name, ':price'=>$price, ':duration'=>$duration, ':id'=>$id]);
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

switch($sort) {
    case 'name_desc':
        $orderBy = 'ORDER BY name DESC';
        break;
    case 'price_asc':
        $orderBy = 'ORDER BY price ASC';
        break;
    case 'price_desc':
        $orderBy = 'ORDER BY price DESC';
        break;
    case 'duration_asc':
        $orderBy = 'ORDER BY duration_minutes ASC';
        break;
    case 'duration_desc':
        $orderBy = 'ORDER BY duration_minutes DESC';
        break;
    default:
        $orderBy = 'ORDER BY name ASC';
        break;
}

// total count
$totalStmt = pdo()->prepare("SELECT COUNT(*) FROM services $where");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();
$totalPages = (int) ceil($total / $perPage);

// fetch page - ensure image_url column is included
$sql = "SELECT id, name, price, duration_minutes, image_url FROM services $where $orderBy LIMIT $perPage OFFSET $offset";
$stmt = pdo()->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------------------------
   Flash messages
   --------------------------- */
$success = get_flash('success');
$error = get_flash('error');
?>
<?php include 'inc/header_sidebar.php'; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4 page-header">
    <div>
        <h1 class="h2 text-salon mb-0">
            <i class="bi bi-scissors"></i> Manage Services
        </h1>
        <p class="text-muted mb-0">Add, edit, and manage salon services</p>
    </div>
    <div>
        <a href="admin_dashboard.php" class="btn btn-outline-salon me-2 d-none d-md-inline-block">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
        <button class="btn btn-salon" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-circle"></i> Add Service
        </button>
    </div>
</div>

<!-- Search and Filter Controls -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-funnel"></i> Search & Filter</h5>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-6">
                <label for="search" class="form-label fw-bold">
                    <i class="bi bi-search"></i> Search Services
                </label>
                <input type="text" class="form-control" id="search" name="q" placeholder="Search by service name..." value="<?= h($q) ?>">
            </div>
            <div class="col-md-4">
                <label for="sort" class="form-label fw-bold">
                    <i class="bi bi-sort-down"></i> Sort By
                </label>
                <select class="form-select" id="sort" name="sort" onchange="this.form.submit()">
                    <option value="name_asc" <?= $sort==='name_asc'?'selected':'' ?>>Name (A-Z)</option>
                    <option value="name_desc" <?= $sort==='name_desc'?'selected':'' ?>>Name (Z-A)</option>
                    <option value="price_asc" <?= $sort==='price_asc'?'selected':'' ?>>Price (Low to High)</option>
                    <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Price (High to Low)</option>
                    <option value="duration_asc" <?= $sort==='duration_asc'?'selected':'' ?>>Duration (Short to Long)</option>
                    <option value="duration_desc" <?= $sort==='duration_desc'?'selected':'' ?>>Duration (Long to Short)</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-salon w-100">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>
            <input type="hidden" name="page" value="1">
        </form>
    </div>
</div>

<!-- Alerts -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?= h($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?= h($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Services Grid -->
<div class="row">
    <?php if (empty($services)): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-scissors fs-1 text-muted d-block mb-3"></i>
                    <h5 class="text-muted">No services found</h5>
                    <p class="text-muted">Try adjusting your search criteria or add a new service.</p>
                    <button class="btn btn-salon" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="bi bi-plus-circle"></i> Add First Service
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($services as $s): 
        $img = get_service_image_url($s);
    ?>
        <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
            <div class="card service-card h-100 shadow-sm">
                <div class="position-relative">
                    <img src="<?= h($img) ?>" class="card-img-top" alt="<?= h($s['name']) ?>" style="height: 200px; object-fit: cover;">
                    <div class="position-absolute top-0 end-0 m-2">
                        <span class="badge bg-dark">ID: <?= (int)$s['id'] ?></span>
                    </div>
                </div>
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title text-salon mb-3"><?= h($s['name']) ?></h5>
                    <div class="mb-3">
                        <span class="badge bg-success fs-6 me-1">
                            <i class="bi bi-currency-dollar"></i> ₱<?= number_format((float)$s['price'],2) ?>
                        </span>
                        <span class="badge bg-info fs-6">
                            <i class="bi bi-clock"></i> <?= (int)$s['duration_minutes'] ?> mins
                        </span>
                    </div>
                    
                    <div class="mt-auto">
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-outline-salon btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= (int)$s['id'] ?>" title="Edit Service">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDelete('deleteForm<?= (int)$s['id'] ?>')" title="Delete Service">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </div>
                        
                        <form id="deleteForm<?= (int)$s['id'] ?>" method="post" class="d-none">
                            <input type="hidden" name="csrf_token" value="<?= h($CSRF) ?>">
                            <input type="hidden" name="action" value="delete_service">
                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div class="modal fade" id="editModal<?= (int)$s['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header modal-header-salon">
                        <h5 class="modal-title">
                            <i class="bi bi-pencil"></i> Edit Service
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="post" enctype="multipart/form-data">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?= h($CSRF) ?>">
                            <input type="hidden" name="action" value="edit_service">
                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">

                            <div class="mb-3">
                                <label for="edit_name_<?= (int)$s['id'] ?>" class="form-label">Service Name</label>
                                <input type="text" class="form-control" id="edit_name_<?= (int)$s['id'] ?>" name="name" value="<?= h($s['name']) ?>" required maxlength="191">
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_price_<?= (int)$s['id'] ?>" class="form-label">Price (₱)</label>
                                        <input type="number" class="form-control" id="edit_price_<?= (int)$s['id'] ?>" name="price" step="0.01" value="<?= h($s['price']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_duration_<?= (int)$s['id'] ?>" class="form-label">Duration (minutes)</label>
                                        <input type="number" class="form-control" id="edit_duration_<?= (int)$s['id'] ?>" name="duration" value="<?= (int)$s['duration_minutes'] ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="edit_image_<?= (int)$s['id'] ?>" class="form-label">Service Image</label>
                                <input type="file" class="form-control" id="edit_image_<?= (int)$s['id'] ?>" name="image" accept="image/jpeg,image/png,image/webp">
                                <div class="form-text">Optional — JPG/PNG/WEBP, max 2.5MB</div>
                            </div>

                            <div class="text-center">
                                <img src="<?= h($img) ?>" alt="Current image" class="img-thumbnail" style="max-height: 150px;">
                                <div class="form-text">Current image</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-salon">
                                <i class="bi bi-check"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
    <nav aria-label="Services pagination" class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$p])) ?>">
                        <?= $p ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<!-- Add Service Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header modal-header-salon">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle"></i> Add New Service
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= h($CSRF) ?>">
                    <input type="hidden" name="action" value="add_service">

                    <div class="mb-3">
                        <label for="add_name" class="form-label">Service Name</label>
                        <input type="text" class="form-control" id="add_name" name="name" required maxlength="191" placeholder="e.g., Haircut, Hair Color">
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_price" class="form-label">Price (₱)</label>
                                <input type="number" class="form-control" id="add_price" name="price" step="0.01" required placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_duration" class="form-label">Duration (minutes)</label>
                                <input type="number" class="form-control" id="add_duration" name="duration" required placeholder="60">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="add_image" class="form-label">Service Image</label>
                        <input type="file" class="form-control" id="add_image" name="image" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">Optional — JPG/PNG/WEBP, max 2.5MB</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-salon">
                        <i class="bi bi-plus-circle"></i> Add Service
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete(formId) {
    if (confirm('Delete this service? This action cannot be undone.')) {
        document.getElementById(formId).submit();
    }
}
</script>

<style>
/* Service Card Styling */
.service-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: none;
    border-radius: 12px;
    overflow: hidden;
}

.service-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
}

.service-card .card-img-top {
    border-radius: 0;
    transition: transform 0.3s ease;
}

.service-card:hover .card-img-top {
    transform: scale(1.05);
}

/* Mobile Responsive Styles */
@media (max-width: 768px) {
    /* Main content - Full width on mobile with minimal side padding - Account for fixed header */
    .main-content {
        width: 100% !important;
        max-width: 100% !important;
        margin-left: 0 !important;
        padding: calc(60px + 1rem) 0.5rem 1rem 0.5rem !important; /* Account for fixed header (60px header + 1rem spacing) */
        box-sizing: border-box;
    }
    
    /* Mobile header - Full width with minimal side padding - FIXED POSITION */
    .mobile-header {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        width: 100% !important;
        z-index: 1050 !important;
        margin: 0 !important;
        padding: 0.75rem 0.5rem !important; /* Consistent padding */
        gap: 0.5rem;
        background: var(--user-card-bg, #fff) !important;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1) !important;
        min-height: 60px !important; /* Consistent height */
        max-height: 60px !important; /* Consistent height */
        display: flex !important;
        align-items: center !important;
    }
    
    /* Mobile header buttons - Consistent sizing */
    .mobile-header .btn {
        margin: 0 !important;
        padding: 0.5rem 0.75rem !important;
        min-width: 44px !important;
        min-height: 44px !important;
        max-height: 44px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 0.875rem !important;
    }
    
    .mobile-header .dropdown-toggle {
        padding: 0.5rem 0.75rem !important;
        min-width: 44px !important;
        min-height: 44px !important;
        max-height: 44px !important;
        font-size: 0.875rem !important;
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
    
    /* Alerts - Better spacing */
    .alert {
        margin: 0 0.5rem 1rem 0.5rem !important;
        font-size: 0.9rem;
        padding: 0.75rem 1rem !important;
    }
    
    /* Search Card - Compact */
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
    
    /* Search Form - Stack on mobile */
    .card-body .row .col-md-6,
    .card-body .row .col-md-4,
    .card-body .row .col-md-2 {
        width: 100% !important;
        max-width: 100% !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin-bottom: 0.75rem !important;
    }
    
    .card-body .form-label {
        font-size: 0.85rem !important;
        margin-bottom: 0.35rem !important;
    }
    
    .card-body .form-control,
    .card-body .form-select {
        font-size: 0.85rem !important;
        padding: 0.4rem 0.65rem !important;
    }
    
    .card-body .btn {
        font-size: 0.8rem !important;
        padding: 0.45rem 0.75rem !important;
    }
    
    /* Service Cards - 2 columns on mobile */
    .row .col-lg-3,
    .row .col-md-4,
    .row .col-sm-6 {
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
        margin-bottom: 1rem !important;
    }
    
    .service-card {
        border-radius: 8px !important;
    }
    
    .service-card .card-img-top {
        height: 180px !important;
    }
    
    .service-card .card-body {
        padding: 0.75rem !important;
    }
    
    .service-card .card-title {
        font-size: 1rem !important;
        margin-bottom: 0.75rem !important;
    }
    
    .service-card .badge {
        font-size: 0.75rem !important;
        padding: 0.35rem 0.6rem !important;
    }
    
    .service-card .btn-sm {
        font-size: 0.8rem !important;
        padding: 0.4rem 0.6rem !important;
    }
    
    /* Empty State */
    .card .card-body.text-center {
        padding: 2rem 0.75rem !important;
    }
    
    .card .card-body.text-center i {
        font-size: 3rem !important;
    }
    
    .card .card-body.text-center h5 {
        font-size: 1.1rem !important;
    }
    
    .card .card-body.text-center p {
        font-size: 0.9rem !important;
    }
    
    .card .card-body.text-center .btn {
        font-size: 0.875rem !important;
        padding: 0.5rem 1rem !important;
    }
    
    /* Pagination */
    .pagination {
        margin: 1rem 0.5rem !important;
    }
    
    .pagination .page-link {
        font-size: 0.85rem !important;
        padding: 0.4rem 0.75rem !important;
    }
    
    /* Modals - Better on mobile */
    .modal-dialog {
        margin: 0.5rem !important;
        max-width: calc(100% - 1rem) !important;
    }
    
    .modal-content {
        border-radius: 12px !important;
    }
    
    .modal-header {
        padding: 1rem !important;
    }
    
    .modal-body {
        padding: 1rem !important;
    }
    
    .modal-body .row .col-md-6 {
        width: 100% !important;
        max-width: 100% !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin-bottom: 1rem !important;
    }
    
    .modal-body .form-label {
        font-size: 0.9rem !important;
    }
    
    .modal-body .form-control {
        font-size: 0.9rem !important;
        padding: 0.5rem 0.75rem !important;
    }
    
    .modal-body .form-text {
        font-size: 0.8rem !important;
    }
    
    .modal-body .img-thumbnail {
        max-height: 120px !important;
    }
    
    .modal-footer {
        padding: 1rem !important;
        flex-direction: column !important;
        gap: 0.5rem;
    }
    
    .modal-footer .btn {
        width: 100% !important;
        margin: 0 !important;
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
    
    /* Service Cards - 3 columns on tablet */
    .row .col-md-4 {
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
    }
}
</style>

<?php include 'inc/footer_sidebar.php'; ?>
