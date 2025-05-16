<?php

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function regenerate_csrf_token() {
    $newToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $newToken;
    return $newToken;
}