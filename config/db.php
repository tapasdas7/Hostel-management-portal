<?php
/**
 * Database connection using PDO (MySQL).
 * Credentials are centralized here so every page imports the same $pdo.
 */

declare(strict_types=1);

// ---- Database credentials ----
$DB_HOST = 'localhost';
$DB_NAME = 'hostel_db';
$DB_USER = 'root';
// XAMPP's default local MySQL root account has no password.
// If you changed the MySQL password, update this value accordingly.
$DB_PASS = '';

// ---- PDO DSN ----
$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";

// ---- PDO options ----
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // throw exceptions on error
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                    // real prepared statements
];

// ---- Development mode (set to false on a live/production server) ----
// When true, the error message includes the actual MySQL reason so you can
// diagnose connection issues. Always set to false in production.
$DEV_MODE = true;

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    http_response_code(500);
    $message = 'Database connection failed. Please contact the administrator.';
    if ($DEV_MODE) {
        // Show the real reason only while developing / debugging.
        $message .= '<br><small>Debug: ' . htmlspecialchars($e->getMessage()) . '</small>';
    }
    die($message);
}
