<?php
require_once 'inc/bootstrap.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Store user name for display
$_SESSION['user_name'] = get_user_name($_SESSION['user_id']);

$success = $error = '';

// Helper function
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Handle image upload
    if ($action === 'upload') {
        $category = trim($_POST['category'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        
        if (empty($category)) {
            $error = 'Please select a category.';
        } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please select an image to upload.';
        } else {
            // Upload to Cloudinary
            $uploadResult = uploadToCloudinary('image', 'glowtime/gallery');
            
            if ($uploadResult['success']) {
                try {
                    $stmt = pdo()->prepare("INSERT INTO gallery (image_url, category, title, description, display_order) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $uploadResult['url'],
                        $category,
                        $title ?: null,
                        $description ?: null,
                        $displayOrder
                    ]);
                    
                    $success = 'Image uploaded successfully!';
                } catch (PDOException $e) {
                    $error = 'Error saving image to database: ' . $e->getMessage();
                }
            } else {
                $error = 'Upload failed: ' . $uploadResult['error'];
            }
        }
    }
    
    // Handle image deletion
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id > 0) {
            try {
                // Get image URL before deleting
                $stmt = pdo()->prepare("SELECT image_url FROM gallery WHERE id = ?");
                $stmt->execute([$id]);
                $image = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($image) {
                    // Delete from database
                    $stmt = pdo()->prepare("DELETE FROM gallery WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    // Note: Cloudinary deletion would require additional API call
                    // For now, we just delete from database
                    // The image will remain in Cloudinary but won't be displayed
                    
                    $success = 'Image deleted successfully!';
                } else {
                    $error = 'Image not found.';
                }
            } catch (PDOException $e) {
                $error = 'Error deleting image: ' . $e->getMessage();
            }
        } else {
            $error = 'Invalid image ID.';
        }
    }
    
    // Handle toggle active status
    if ($action === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id > 0) {
            try {
                $stmt = pdo()->prepare("UPDATE gallery SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'Image status updated!';
            } catch (PDOException $e) {
                $error = 'Error updating status: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all gallery images
try {
    // Check if gallery table exists
    $tableCheck = pdo()->query("SHOW TABLES LIKE 'gallery'");
    if ($tableCheck->rowCount() === 0) {
        $images = [];
        $tableError = true;
        if (empty($error)) {
            $error = 'Gallery table not found. Please run setup_gallery.php first.';
        }
    } else {
        $stmt = pdo()->query("SELECT * FROM gallery ORDER BY display_order ASC, created_at DESC");
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $tableError = false;
    }
} catch (PDOException $e) {
    $images = [];
    $tableError = true;
    if (empty($error)) {
        $error = 'Error loading gallery images: ' . $e->getMessage();
    }
}

// Available categories
$categories = ['Hair', 'Nails', 'Make up', 'Spa', 'Facial', 'Other'];
?>
<?php include 'inc/header_sidebar.php'; ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4 page-header">
    <div>
        <h1 class="h2 text-salon mb-0">
            <i class="bi bi-images"></i> Manage Gallery
        </h1>
        <p class="text-muted mb-0">Upload and manage gallery images</p>
    </div>
    <div>
        <a href="admin_dashboard.php" class="btn btn-outline-salon d-none d-md-inline-block">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
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

<!-- Upload Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-cloud-upload"></i> Upload New Image
        </h5>
    </div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="image" class="form-label fw-bold">
                            <i class="bi bi-image"></i> Select Image *
                        </label>
                        <input type="file" class="form-control" name="image" id="image" accept="image/*" required>
                        <div class="form-text">Accepted formats: JPG, PNG, GIF. Max size: 10MB</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="category" class="form-label fw-bold">
                            <i class="bi bi-tags"></i> Category *
                        </label>
                        <select class="form-select" name="category" id="category" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= h(strtolower($cat)) ?>"><?= h($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="title" class="form-label fw-bold">
                            <i class="bi bi-type"></i> Title <span class="text-muted">(Optional)</span>
                        </label>
                        <input type="text" class="form-control" name="title" id="title" placeholder="e.g., Summer Hair Color">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="display_order" class="form-label fw-bold">
                            <i class="bi bi-sort-numeric-down"></i> Display Order
                        </label>
                        <input type="number" class="form-control" name="display_order" id="display_order" value="0" min="0">
                        <div class="form-text">Lower numbers appear first. Default: 0</div>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label fw-bold">
                    <i class="bi bi-card-text"></i> Description <span class="text-muted">(Optional)</span>
                </label>
                <textarea class="form-control" name="description" id="description" rows="3" placeholder="Add a description for this image..."></textarea>
            </div>
            <button type="submit" class="btn btn-salon">
                <i class="bi bi-upload"></i> Upload Image
            </button>
        </form>
    </div>
</div>

<!-- Gallery Images -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-grid"></i> Gallery Images (<?= count($images) ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (isset($tableError) && $tableError): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                <strong>Gallery table not found!</strong> Please run <a href="setup_gallery.php" class="alert-link">setup_gallery.php</a> to create the gallery table.
            </div>
        <?php elseif (empty($images)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-image fs-1 d-block mb-2"></i>
                No images in gallery yet. Upload your first image above!
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($images as $img): ?>
                    <div class="col-md-4 col-lg-3">
                        <div class="card gallery-card border-0 shadow-sm h-100">
                            <div class="position-relative">
                                <img src="<?= h($img['image_url']) ?>" class="card-img-top" alt="<?= h($img['title'] ?? $img['category']) ?>" style="height: 200px; object-fit: cover;">
                                <span class="badge position-absolute top-0 end-0 m-2 <?= $img['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                    <i class="bi bi-<?= $img['is_active'] ? 'eye' : 'eye-slash' ?>"></i> <?= $img['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <div class="mb-2">
                                    <span class="badge bg-salon">
                                        <i class="bi bi-tag"></i> <?= h(ucfirst($img['category'])) ?>
                                    </span>
                                </div>
                                <?php if (!empty($img['title'])): ?>
                                    <h6 class="card-title text-salon mb-2"><?= h($img['title']) ?></h6>
                                <?php endif; ?>
                                <?php if (!empty($img['description'])): ?>
                                    <p class="card-text small text-muted mb-2 flex-grow-1"><?= h(substr($img['description'], 0, 60)) ?><?= strlen($img['description']) > 60 ? '...' : '' ?></p>
                                <?php endif; ?>
                                <div class="small text-muted mb-3">
                                    <i class="bi bi-sort-numeric-down"></i> Order: <?= $img['display_order'] ?>
                                </div>
                                <div class="d-flex gap-2 mt-auto">
                                    <form method="post" style="display:inline; flex: 1;" onsubmit="return confirm('Are you sure you want to delete this image?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $img['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger w-100" title="Delete Image">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                    <form method="post" style="display:inline; flex: 1;">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="id" value="<?= $img['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-<?= $img['is_active'] ? 'warning' : 'success' ?> w-100" title="<?= $img['is_active'] ? 'Hide Image' : 'Show Image' ?>">
                                            <i class="bi bi-<?= $img['is_active'] ? 'eye-slash' : 'eye' ?>"></i> <?= $img['is_active'] ? 'Hide' : 'Show' ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Gallery Card Styling */
.gallery-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-radius: 12px;
    overflow: hidden;
}

.gallery-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
}

.gallery-card .card-img-top {
    transition: transform 0.3s ease;
}

.gallery-card:hover .card-img-top {
    transform: scale(1.05);
}

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
    
    /* Alerts - Better spacing */
    .alert {
        margin: 0 0.5rem 1rem 0.5rem !important;
        font-size: 0.9rem;
        padding: 0.75rem 1rem !important;
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
    
    /* Upload Form - Stack on mobile */
    .card-body .row .col-md-6 {
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
    .card-body .form-select,
    .card-body textarea {
        font-size: 0.85rem !important;
        padding: 0.4rem 0.65rem !important;
    }
    
    .card-body .form-text {
        font-size: 0.75rem !important;
    }
    
    .card-body .btn {
        font-size: 0.8rem !important;
        padding: 0.45rem 0.75rem !important;
        width: 100% !important;
    }
    
    /* Gallery Grid - 2 columns on mobile */
    .row.g-4 {
        --bs-gutter-y: 1rem;
        --bs-gutter-x: 0.5rem;
    }
    
    .row.g-4 .col-md-4,
    .row.g-4 .col-lg-3 {
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
        margin-bottom: 1rem !important;
    }
    
    .gallery-card {
        border-radius: 8px !important;
    }
    
    .gallery-card .card-img-top {
        height: 180px !important;
    }
    
    .gallery-card .card-body {
        padding: 0.75rem !important;
    }
    
    .gallery-card .card-title {
        font-size: 0.95rem !important;
        margin-bottom: 0.5rem !important;
    }
    
    .gallery-card .card-text {
        font-size: 0.8rem !important;
    }
    
    .gallery-card .badge {
        font-size: 0.7rem !important;
        padding: 0.3rem 0.5rem !important;
    }
    
    .gallery-card .btn-sm {
        font-size: 0.75rem !important;
        padding: 0.35rem 0.5rem !important;
    }
    
    .gallery-card .small {
        font-size: 0.75rem !important;
    }
    
    /* Empty State */
    .text-center.py-5 {
        padding: 2rem 0.5rem !important;
    }
    
    .text-center.py-5 i {
        font-size: 3rem !important;
    }
    
    .text-center.py-5 .text-muted {
        font-size: 0.9rem !important;
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
    
    /* Gallery Grid - 3 columns on tablet */
    .row.g-4 .col-md-4 {
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
    }
}
</style>

<?php include 'inc/footer_sidebar.php'; ?>

