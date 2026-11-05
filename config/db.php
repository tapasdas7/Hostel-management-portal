<?php


declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// ---- Portal Branding Configuration ----
define('PORTAL_NAME', 'Silicon Residence');

// ---- Database credentials ----
$DB_HOST = 'localhost';
$DB_NAME = 'hostel_db';
$DB_USER = 'root';
// XAMPP's default local MySQL root account has no password.
// If you changed the MySQL password, update this value accordingly.
$DB_PASS = '';

// ---- Development mode (set to false on a live/production server) ----
// When true, the error message includes the actual MySQL reason so you can
// diagnose connection issues. Always set to false in production.
$DEV_MODE = true;

// ---- Enable MySQLi exception throwing ----
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    
    // Set charset to utf8mb4
    if (!$conn->set_charset('utf8mb4')) {
        throw new Exception('Failed to set charset to utf8mb4');
    }
} catch (Exception $e) {
    http_response_code(500);
    $message = 'Database connection failed. Please contact the administrator.';
    if ($DEV_MODE) {
        // Show the real reason only while developing / debugging.
        $message .= '<br><small>Debug: ' . htmlspecialchars($e->getMessage()) . '</small>';
    }
    die($message);
}
