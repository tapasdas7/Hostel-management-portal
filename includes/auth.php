<?php
/**
 * Authentication helper.
 * Starts a secure session and guards pages that require a logged-in student.
 */

declare(strict_types=1);

// ---- Start session with secure cookie settings (if served over HTTPS) ----
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Redirect to the login page if the student is not authenticated.
 * Call this at the top of every protected page (dashboard.php, etc.).
 */
function require_auth(): void
{
    if (!isset($_SESSION['student_id']) || empty($_SESSION['student_id'])) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Returns the current student's basic session info as an associative array,
 * or null if not logged in.
 */
function current_student(): ?array
{
    if (!isset($_SESSION['student_id']) || empty($_SESSION['student_id'])) {
        return null;
    }
    return [
        'student_id'  => (int) $_SESSION['student_id'],
        'sic_number'  => $_SESSION['sic_number']  ?? '',
        'first_name'  => $_SESSION['first_name']  ?? '',
        'role'        => $_SESSION['role']        ?? 'student',
    ];
}

function require_admin(): void
{
    require_auth();
    if (($_SESSION['role'] ?? 'student') !== 'admin') {
        http_response_code(403);
        header('Location: dashboard.php');
        exit('Administrator access required.');
    }
}
