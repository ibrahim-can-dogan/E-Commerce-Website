<?php

function getConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        return new PDO($dsn, DB_USER, DB_PASS);
    } catch (PDOException $e) {
        echo "Connection failed: " . $e->getMessage();
    }
} 