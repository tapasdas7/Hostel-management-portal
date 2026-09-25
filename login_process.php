<?php
/**
 * Login Process (backend)
 * - Receives the login form via POST.
 * - Validates input, queries the students table with a MySQLi prepared statement.
 * - Verifies the hashed password with password_verify().
 * - Initializes a secure $_SESSION and redirects to the role-specific dashboard.
 */

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$sic_number = trim($_POST['sic_number'] ?? '');
$password   = $_POST['password'] ?? '';

if ($sic_number === '' || $password === '') {
    header('Location: index.php?error=' . urlencode('Both fields are required.'));
    exit;
}

try {
    $stmt = $conn->prepare('SELECT id, sic_number, first_name, password_hash, role
                           FROM students
                           WHERE sic_number = ?
                           LIMIT 1');
    $stmt->bind_param('s', $sic_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();

    if ($student && password_verify($password, $student['password_hash'])) {
        session_regenerate_id(true);

        $_SESSION['student_id'] = (int) $student['id'];
        $_SESSION['sic_number'] = $student['sic_number'];
        $_SESSION['first_name'] = $student['first_name'];
        $_SESSION['role']       = $student['role'];

        redirect_by_role($student['role']);
    }

    header('Location: index.php?error=' . urlencode('Invalid SIC number or password.'));
    exit;

} catch (Exception $e) {
    header('Location: index.php?error=' . urlencode('A server error occurred. Please try again.'));
    exit;
}
