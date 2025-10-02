<?php
require_once 'inc/bootstrap.php';
require_once 'inc/data_loader.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// Handle data updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_service':
                // Add new service to JSON
                $services = SalonDataLoader::getServicesData();
                $newService = [
                    'id' => count($services['services']) + 1,
                    'name' => $_POST['service_name'],
                    'description' => $_POST['service_description'],
                    'duration' => (int)$_POST['service_duration'],
                    'price' => (float)$_POST['service_price'],
                    'category' => $_POST['service_category'],
                    'trends' => explode(',', $_POST['service_trends']),
                    'recommended_for' => explode(',', $_POST['service_recommended']),
                    'tips' => explode(',', $_POST['service_tips'])
                ];
                
                $services['services'][] = $newService;
                $jsonData = json_encode($services, JSON_PRETTY_PRINT);
                if (file_put_contents('data/salon_services.json', $jsonData)) {
                    $message = "Service added successfully!";
                } else {
                    $error = "Failed to save service data.";
                }
                break;
                
            case 'add_tip':
                $tips = SalonDataLoader::getBeautyTips();
                $category = $_POST['tip_category'];
                $tip = $_POST['tip_content'];
                
                if (!isset($tips[$category])) {
                    $tips[$category] = [];
                }
                $tips[$category][] = $tip;
                
                $services = SalonDataLoader::getServicesData();
                $services['beauty_tips'] = $tips;
                $jsonData = json_encode($services, JSON_PRETTY_PRINT);
                if (file_put_contents('data/salon_services.json', $jsonData)) {
                    $message = "Beauty tip added successfully!";
                } else {
                    $error = "Failed to save tip data.";
                }
                break;
        }
    }
}

include 'inc/header_sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 text-salon mb-0">
            <i class="bi bi-database"></i> Custom Data Management
        </h1>
        <p class="text-muted mb-0">Manage AI chatbot knowledge base</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Add New Service -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-plus-circle"></i> Add New Service
                </h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="add_service">
                    
                    <div class="mb-3">
                        <label class="form-label">Service Name</label>
                        <input type="text" class="form-control" name="service_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="service_description" rows="2" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Duration (minutes)</label>
                                <input type="number" class="form-control" name="service_duration" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Price ($)</label>
                                <input type="number" step="0.01" class="form-control" name="service_price" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select class="form-control" name="service_category" required>
                            <option value="hair">Hair</option>
                            <option value="color">Color</option>
                            <option value="skincare">Skincare</option>
                            <option value="nails">Nails</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Trends (comma-separated)</label>
                        <input type="text" class="form-control" name="service_trends" placeholder="e.g., layered cuts, face-framing">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Recommended For (comma-separated)</label>
                        <input type="text" class="form-control" name="service_recommended" placeholder="e.g., all hair types, professional look">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tips (comma-separated)</label>
                        <input type="text" class="form-control" name="service_tips" placeholder="e.g., Regular trims maintain shape, Layered cuts add volume">
                    </div>
                    
                    <button type="submit" class="btn btn-salon">
                        <i class="bi bi-plus"></i> Add Service
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Beauty Tip -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-lightbulb"></i> Add Beauty Tip
                </h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="add_tip">
                    
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select class="form-control" name="tip_category" required>
                            <option value="hair_care">Hair Care</option>
                            <option value="skincare">Skincare</option>
                            <option value="nail_care">Nail Care</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tip Content</label>
                        <textarea class="form-control" name="tip_content" rows="3" required placeholder="Enter beauty tip..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-salon">
                        <i class="bi bi-plus"></i> Add Tip
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Current Data Overview -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-list"></i> Current Data Overview
                </h5>
            </div>
            <div class="card-body">
                <?php
                $services = SalonDataLoader::getServicesData();
                $knowledge = SalonDataLoader::getKnowledgeData();
                ?>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center p-3 bg-light rounded">
                            <h4 class="text-salon"><?= count($services['services'] ?? []) ?></h4>
                            <p class="mb-0">Services</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center p-3 bg-light rounded">
                            <h4 class="text-salon"><?= count($services['beauty_tips'] ?? []) ?></h4>
                            <p class="mb-0">Beauty Tip Categories</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center p-3 bg-light rounded">
                            <h4 class="text-salon"><?= count($knowledge['common_questions'] ?? []) ?></h4>
                            <p class="mb-0">FAQ Categories</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <a href="test_custom_data.php" class="btn btn-outline-salon">
                        <i class="bi bi-play"></i> Test Data Integration
                    </a>
                    <a href="client_ai.php" class="btn btn-salon">
                        <i class="bi bi-robot"></i> Test AI Chatbot
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'inc/footer_sidebar.php'; ?>
