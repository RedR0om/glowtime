<?php
/**
 * Gallery Setup Script
 * Run this once to create the gallery table
 */

require_once 'inc/bootstrap.php';

try {
    // Create gallery table
    $sql = "CREATE TABLE IF NOT EXISTS gallery (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_url VARCHAR(500) NOT NULL,
        category VARCHAR(50) NOT NULL,
        title VARCHAR(255) NULL,
        description TEXT NULL,
        display_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_category (category),
        INDEX idx_is_active (is_active),
        INDEX idx_display_order (display_order)
    )";
    
    pdo()->exec($sql);
    
    echo "<h2 style='color: green;'>✓ Gallery table created successfully!</h2>";
    echo "<p>The gallery system is now ready to use.</p>";
    echo "<p><a href='admin_gallery.php'>Go to Gallery Management</a> | <a href='gallery.php'>View Public Gallery</a></p>";
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>✗ Error creating gallery table:</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

