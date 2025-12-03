<?php
require_once 'inc/bootstrap.php';

// Get filter category
$filterCategory = $_GET['category'] ?? 'all';

// Fetch gallery images
try {
    // Check if gallery table exists
    $tableCheck = pdo()->query("SHOW TABLES LIKE 'gallery'");
    if ($tableCheck->rowCount() === 0) {
        $images = [];
        $categories = [];
        $carouselImages = [];
        $tableError = true;
    } else {
        $sql = "SELECT * FROM gallery WHERE is_active = 1";
        $params = [];
        
        if ($filterCategory !== 'all') {
            $sql .= " AND category = ?";
            $params[] = $filterCategory;
        }
        
        $sql .= " ORDER BY display_order ASC, created_at DESC";
        
        $stmt = pdo()->prepare($sql);
        $stmt->execute($params);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get random images for carousel
        $carouselStmt = pdo()->query("SELECT * FROM gallery WHERE is_active = 1 ORDER BY RAND() LIMIT 15");
        $allCarouselImages = $carouselStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group images into slides of 5 (desktop) or 1 (mobile - handled by CSS)
        $carouselImages = [];
        $imagesPerSlide = 5;
        for ($i = 0; $i < count($allCarouselImages); $i += $imagesPerSlide) {
            $slide = array_slice($allCarouselImages, $i, $imagesPerSlide);
            if (count($slide) > 0) {
                $carouselImages[] = $slide;
            }
        }
        
        // Get all categories for filter
        $categoriesStmt = pdo()->query("SELECT DISTINCT category FROM gallery WHERE is_active = 1 ORDER BY category");
        $categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);
        $tableError = false;
    }
} catch (PDOException $e) {
    $images = [];
    $categories = [];
    $carouselImages = [];
    $tableError = true;
}

// Helper function for HTML escaping
function h($v) { 
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery - Glowtime Salon</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom Salon CSS -->
    <link href="css/salon-style.css" rel="stylesheet">
    
    <style>
        :root {
            --salon-primary: #e91e63;
            --salon-secondary: #f06292;
            --salon-light: #fdf2f8;
        }
        
        body {
            background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 50%, #fdf2f8 100%);
            background-attachment: fixed;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        /* Subtle pattern overlay */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(233, 30, 99, 0.03) 0%, transparent 40%),
                radial-gradient(circle at 80% 70%, rgba(240, 98, 146, 0.03) 0%, transparent 40%);
            pointer-events: none;
            z-index: 0;
        }
        
        .container {
            position: relative;
            z-index: 1;
        }
        
        .gallery-header {
            background: linear-gradient(135deg, var(--salon-primary) 0%, var(--salon-secondary) 100%);
            color: white;
            padding: 4rem 0 3rem;
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
            overflow: hidden;
        }
        
        .gallery-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 30px 30px;
            animation: float 20s infinite linear;
            opacity: 0.3;
        }
        
        .gallery-header::after {
            content: '🌸';
            position: absolute;
            top: 20px;
            right: 30px;
            font-size: 80px;
            opacity: 0.15;
            transform: rotate(25deg);
        }
        
        @keyframes float {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(30px, 30px) rotate(360deg); }
        }
        
        .gallery-header h1,
        .gallery-header p {
            position: relative;
            z-index: 1;
        }
        
        /* Gallery Carousel */
        .gallery-carousel {
            margin: 2rem 0;
            padding: 1rem 0;
        }
        
        .gallery-carousel .carousel {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(233, 30, 99, 0.15);
            background: white;
        }
        
        .gallery-carousel .carousel-inner {
            padding: 1.5rem;
        }
        
        .gallery-carousel .carousel-item {
            padding: 0.5rem;
        }
        
        .gallery-carousel-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
        }
        
        .gallery-carousel-item {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            aspect-ratio: 1;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background: #f8f9fa;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .gallery-carousel-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(233, 30, 99, 0.2);
        }
        
        .gallery-carousel-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            background: #f8f9fa;
        }
        
        .gallery-carousel-item-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(0deg, rgba(0,0,0,0.7) 0%, transparent 100%);
            color: white;
            padding: 0.75rem;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }
        
        .gallery-carousel-item:hover .gallery-carousel-item-overlay {
            transform: translateY(0);
        }
        
        .gallery-carousel-item-overlay .badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
        }
        
        .gallery-carousel .carousel-control-prev,
        .gallery-carousel .carousel-control-next {
            width: 40px;
            height: 40px;
            background: rgba(233, 30, 99, 0.8);
            border-radius: 50%;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.8;
        }
        
        .gallery-carousel .carousel-control-prev {
            left: 10px;
        }
        
        .gallery-carousel .carousel-control-next {
            right: 10px;
        }
        
        .gallery-carousel .carousel-control-prev:hover,
        .gallery-carousel .carousel-control-next:hover {
            opacity: 1;
            background: var(--salon-primary);
        }
        
        .gallery-carousel .carousel-indicators {
            margin-bottom: 0.5rem;
        }
        
        .gallery-carousel .carousel-indicators button {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: var(--salon-primary);
            opacity: 0.5;
        }
        
        .gallery-carousel .carousel-indicators button.active {
            opacity: 1;
        }
        
        @media (max-width: 1200px) {
            .gallery-carousel-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .gallery-carousel-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.75rem;
            }
            
            .gallery-carousel .carousel-inner {
                padding: 1rem;
            }
            
            .gallery-carousel .carousel-control-prev,
            .gallery-carousel .carousel-control-next {
                width: 35px;
                height: 35px;
            }
        }
        
        @media (max-width: 576px) {
            .gallery-carousel-grid {
                grid-template-columns: 1fr;
                display: flex;
                flex-direction: column;
            }
            
            .gallery-carousel-item {
                width: 100%;
                max-width: 100%;
                margin: 0;
                display: block;
            }
            
            /* Hide all images except the first one on mobile */
            .gallery-carousel-item:not(:first-child) {
                display: none;
            }
        }
        
        .gallery-header h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .gallery-header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .category-filter {
            margin-bottom: 3rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: center;
            padding: 1.5rem;
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(233, 30, 99, 0.1);
        }
        
        .category-btn {
            padding: 0.5rem 1.5rem;
            border: 2px solid var(--salon-primary);
            background: white;
            color: var(--salon-primary);
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .category-btn:hover,
        .category-btn.active {
            background: var(--salon-primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(233, 30, 99, 0.3);
        }
        
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            padding: 2rem 0;
        }
        
        .gallery-item {
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            background: white;
            cursor: pointer;
            aspect-ratio: 4 / 3;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid transparent;
        }
        
        .gallery-item:hover {
            border-color: var(--salon-primary);
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(233, 30, 99, 0.25);
        }
        
        .gallery-item img {
            max-width: 90%;
            max-height: 90%;
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
            position: relative;
            z-index: 1;
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin: auto;
        }
        
        .gallery-item-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(0deg, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.5) 50%, transparent 100%);
            color: white;
            padding: 1.5rem;
            transform: translateY(100%);
            transition: transform 0.3s ease;
            z-index: 2;
        }
        
        .gallery-item:hover .gallery-item-overlay {
            transform: translateY(0);
        }
        
        /* Category badge always visible */
        .gallery-category-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--salon-primary);
            color: white;
            padding: 0.4rem 0.9rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 2;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .gallery-category {
            display: inline-block;
            background: var(--salon-primary);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .gallery-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .gallery-description {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .empty-gallery {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
        }
        
        .empty-gallery i {
            font-size: 4rem;
            color: var(--salon-primary);
            opacity: 0.3;
            margin-bottom: 1rem;
        }
        
        .back-to-home {
            text-align: center;
            margin-top: 3rem;
            padding: 2rem 0;
        }
        
        .back-to-home a {
            color: var(--salon-primary);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .back-to-home a:hover {
            text-decoration: underline;
        }
        
        /* Lightbox Modal */
        .lightbox-modal .modal-backdrop {
            background-color: rgba(233, 30, 99, 0.3);
            backdrop-filter: blur(5px);
        }
        
        .lightbox-modal .modal-dialog {
            max-width: 90vw;
            max-height: 90vh;
        }
        
        .lightbox-modal .modal-content {
            background: rgba(255, 255, 255, 0.98);
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(233, 30, 99, 0.2);
        }
        
        .lightbox-modal .modal-body {
            padding: 2rem;
            text-align: center;
            position: relative;
        }
        
        .lightbox-image-container {
            position: relative;
            display: inline-block;
            padding: 1rem;
            border-radius: 10px;
            background-color: #f8f9fa;
        }
        
        .lightbox-modal img {
            max-width: 100%;
            max-height: 75vh;
            object-fit: contain;
            border-radius: 8px;
            display: block;
            background: #f8f9fa;
        }
        
        .lightbox-info {
            background: rgba(255, 255, 255, 0.98);
            padding: 1.5rem;
            border-radius: 10px;
            margin-top: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        /* Smooth animations and enhanced styling */
        .gallery-item {
            animation: fadeIn 0.5s ease-in;
            min-height: 280px;
        }
        
        /* Ensure PNG images have background color */
        .gallery-item img[src*=".png"],
        .gallery-item img[src*=".PNG"] {
            background: #f8f9fa;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Stagger animation for items */
        .gallery-item:nth-child(1) { animation-delay: 0.1s; }
        .gallery-item:nth-child(2) { animation-delay: 0.2s; }
        .gallery-item:nth-child(3) { animation-delay: 0.3s; }
        .gallery-item:nth-child(4) { animation-delay: 0.4s; }
        .gallery-item:nth-child(5) { animation-delay: 0.5s; }
        .gallery-item:nth-child(6) { animation-delay: 0.6s; }
        
        @media (max-width: 768px) {
            .gallery-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 1rem;
            }
            
            .gallery-header h1 {
                font-size: 2rem;
            }
            
            .gallery-item {
                min-height: 250px;
            }
        }
        
        @media (min-width: 1200px) {
            .gallery-grid {
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            }
        }
        
        @media (min-width: 1200px) {
            .gallery-grid {
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light" style="background: rgba(255, 255, 255, 0.95); box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-flower1 me-2" style="color: var(--salon-primary);"></i>
                <span style="font-weight: 700; color: var(--salon-primary);">Glowtime Salon</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="gallery.php">Gallery</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#contact">Contact</a>
                    </li>
                    <li class="nav-item ms-2">
                        <a href="login.php" class="nav-link btn btn-sm px-3" style="background-color: var(--salon-primary); border-color: var(--salon-primary); color: white;">
                            Book Now
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Gallery Header -->
    <div class="gallery-header">
        <div class="container">
            <h1><i class="bi bi-images"></i> Our Gallery</h1>
            <p>Discover the beauty and artistry of Glowtime Salon</p>
        </div>
    </div>

    <div class="container">
        <!-- Gallery Carousel -->
        <?php if (!empty($carouselImages)): ?>
        <div class="gallery-carousel">
            <div id="galleryCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4000">
                <div class="carousel-indicators">
                    <?php foreach ($carouselImages as $index => $carouselImage): ?>
                        <button type="button" data-bs-target="#galleryCarousel" data-bs-slide-to="<?= $index ?>" <?= $index === 0 ? 'class="active" aria-current="true"' : '' ?> aria-label="Slide <?= $index + 1 ?>"></button>
                    <?php endforeach; ?>
                </div>
                <div class="carousel-inner">
                    <?php foreach ($carouselImages as $index => $slide): ?>
                        <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                            <div class="gallery-carousel-grid">
                                <?php foreach ($slide as $carouselImage): ?>
                                    <div class="gallery-carousel-item" data-bs-toggle="modal" data-bs-target="#lightboxModal" data-image="<?= h($carouselImage['image_url']) ?>" data-title="<?= h($carouselImage['title'] ?? '') ?>" data-description="<?= h($carouselImage['description'] ?? '') ?>" data-category="<?= h($carouselImage['category']) ?>">
                                        <img src="<?= h($carouselImage['image_url']) ?>" alt="<?= h($carouselImage['title'] ?? $carouselImage['category']) ?>" loading="lazy">
                                        <div class="gallery-carousel-item-overlay">
                                            <span class="badge bg-light text-dark"><?= h(ucfirst($carouselImage['category'])) ?></span>
                                            <?php if (!empty($carouselImage['title'])): ?>
                                                <div style="font-size: 0.85rem; font-weight: 600; margin-top: 0.25rem;"><?= h($carouselImage['title']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#galleryCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#galleryCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Category Filter -->
        <div class="category-filter">
            <a href="gallery.php?category=all" class="category-btn <?= $filterCategory === 'all' ? 'active' : '' ?>">
                <i class="bi bi-grid"></i> All
            </a>
            <?php foreach ($categories as $cat): ?>
                <a href="gallery.php?category=<?= urlencode($cat) ?>" class="category-btn <?= $filterCategory === $cat ? 'active' : '' ?>">
                    <?= h(ucfirst($cat)) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Gallery Grid -->
        <?php if (isset($tableError) && $tableError): ?>
            <div class="empty-gallery">
                <i class="bi bi-exclamation-triangle"></i>
                <h3>Gallery not set up yet</h3>
                <p>Please run <code>setup_gallery.php</code> to initialize the gallery system.</p>
            </div>
        <?php elseif (empty($images)): ?>
            <div class="empty-gallery">
                <i class="bi bi-image"></i>
                <h3>No images found</h3>
                <p>Check back soon for our beautiful gallery!</p>
            </div>
        <?php else: ?>
            <div class="gallery-grid">
                <?php foreach ($images as $image): ?>
                    <div class="gallery-item" data-bs-toggle="modal" data-bs-target="#lightboxModal" data-image="<?= h($image['image_url']) ?>" data-title="<?= h($image['title'] ?? '') ?>" data-description="<?= h($image['description'] ?? '') ?>" data-category="<?= h($image['category']) ?>">
                        <span class="gallery-category-badge"><?= h(ucfirst($image['category'])) ?></span>
                        <img src="<?= h($image['image_url']) ?>" alt="<?= h($image['title'] ?? $image['category']) ?>" loading="lazy">
                        <div class="gallery-item-overlay">
                            <?php if (!empty($image['title'])): ?>
                                <div class="gallery-title"><?= h($image['title']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($image['description'])): ?>
                                <div class="gallery-description"><?= h($image['description']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Back to Home -->
        <div class="back-to-home">
            <a href="index.php">
                <i class="bi bi-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>

    <!-- Lightbox Modal -->
    <div class="modal fade lightbox-modal" id="lightboxModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0" style="background: transparent;">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="lightbox-image-container">
                        <img id="lightboxImage" src="" alt="" class="img-fluid">
                    </div>
                    <div class="lightbox-info">
                        <span id="lightboxCategory" class="gallery-category"></span>
                        <h5 id="lightboxTitle" class="mt-2 mb-1"></h5>
                        <p id="lightboxDescription" class="text-muted mb-0"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Lightbox functionality
        const lightboxModal = document.getElementById('lightboxModal');
        if (lightboxModal) {
            lightboxModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const imageUrl = button.getAttribute('data-image');
                const title = button.getAttribute('data-title');
                const description = button.getAttribute('data-description');
                const category = button.getAttribute('data-category');
                
                document.getElementById('lightboxImage').src = imageUrl;
                document.getElementById('lightboxCategory').textContent = category.charAt(0).toUpperCase() + category.slice(1);
                document.getElementById('lightboxTitle').textContent = title || '';
                document.getElementById('lightboxDescription').textContent = description || '';
            });
        }
    </script>
</body>
</html>

