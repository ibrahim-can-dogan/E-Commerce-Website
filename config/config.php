<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sustainability_ecommerce');

// Email configuration got from moodle example
define('EMAIL_HOST', 'asmtp.bilkent.edu.tr');
define('EMAIL_USERNAME', ''); 
define('EMAIL_PASSWORD', '');
define('EMAIL_FROM_NAME', 'Sustainability e-Commerce');
define('EMAIL_PORT', 587);
define('EMAIL_ENCRYPTION', 'tls');

// Path constants
define('SITE_URL', 'http://localhost/ctis256-project');
define('PRODUCT_IMAGE_UPLOAD_DIR', __DIR__ . '/../assets/images/products/');
define('PROFILE_IMAGE_UPLOAD_DIR', __DIR__ . '/../assets/images/profile_images/');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// Set timezone
date_default_timezone_set('Europe/Istanbul');

// Application configuration
define('APP_NAME', 'Sustainability e-Commerce');
define('ITEMS_PER_PAGE', 4); // For paginated results 