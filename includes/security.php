<?php
/**
 * Security functions for the application
 */

// Generate a CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && $token === $_SESSION['csrf_token'];
}

// Sanitize input to prevent XSS attacks
function sanitizeInput($input) {
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = sanitizeInput($value);
        }
        return $input;
    }
    
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

// Hash a password
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// Verify a password against its hash
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Generate a random verification code
function generateVerificationCode($length = 6) {
    return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

// Check if a request is AJAX
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || 
           (isset($_POST['is_ajax']) && $_POST['is_ajax'] === 'true');
} 