<?php
/**
 * Configuration Example for Glowtime Salon
 * 
 * FOR INFINITYFREE DEPLOYMENT:
 * 1. Copy this file to inc/config.php
 * 2. Fill in your actual values below
 * 3. Upload inc/config.php to your InfinityFree server
 * 4. DO NOT commit inc/config.php to git (it's already in .gitignore)
 * 
 * This file will override .env settings and is the recommended method for InfinityFree
 */

// OpenAI API Configuration
define('OPENAI_API_KEY', 'your_openai_api_key_here');

// Database Configuration (InfinityFree provides these in your control panel)
define('DB_HOST', 'localhost');  // Usually localhost or mysql.epizy.com
define('DB_NAME', 'your_database_name');  // From InfinityFree control panel
define('DB_USER', 'your_database_user');  // From InfinityFree control panel
define('DB_PASS', 'your_database_password');  // From InfinityFree control panel

// Cloudinary Configuration
define('CLOUDINARY_URL', 'https://api.cloudinary.com/v1_1/your_cloud_name/image/upload');
define('CLOUDINARY_UPLOAD_PRESET', 'your_upload_preset_here');

// Mark config as loaded
define('CONFIG_LOADED', true);
