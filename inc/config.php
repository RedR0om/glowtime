<?php
/**
 * Configuration file for Glowtime Salon
 * 
 * For local development: Use .env file
 * For production (InfinityFree): Copy this file and fill in your values
 * 
 * IMPORTANT: This file should be gitignored - never commit sensitive data!
 */

// Check if config file exists and hasn't been committed with placeholder values
if (!defined('CONFIG_LOADED')) {
    // You can override these in config.php if .env is not available
    // For InfinityFree: Create inc/config.php with your actual values
    
    // If config.php exists with real values, it will define these constants
    // Otherwise, bootstrap.php will load from .env or use fallbacks
}
