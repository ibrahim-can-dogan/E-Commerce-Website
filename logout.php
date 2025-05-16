<?php
// Include configuration file
require_once 'config/config.php';

// Destroy session
session_unset();
session_destroy();

// Redirect to home page
header('Location: index.php');
exit;
?> 