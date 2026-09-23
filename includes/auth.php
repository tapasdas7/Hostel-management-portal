<?php
/**
 * Authentication helper.
 * Starts a secure session and guards pages by role.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function require_auth(): void
{
    if (!isset($_SESSION['student_id']) || empty($_SESSION['student_id'])) {
        header('Location: index.php');
        exit;
    }
}

function current_student(): ?array
{
    if (!isset($_SESSION['student_id']) || empty($_SESSION['student_id'])) {
        return null;
    }
    return [
        'student_id' => (int) $_SESSION['student_id'],
        'sic_number' => $_SESSION['sic_number'] ?? '',
        'first_name' => $_SESSION['first_name'] ?? '',
        'role'       => $_SESSION['role'] ?? 'student',
    ];
}

function require_role(string $role): void
{
    require_auth();
    if (($_SESSION['role'] ?? 'student') !== $role) {
        redirect_by_role($_SESSION['role'] ?? 'student');
    }
}

function redirect_by_role(string $role): void
{
    $map = [
        'student' => 'student_dashboard.php',
        'manager' => 'manager_dashboard.php',
        'admin'   => 'admin_dashboard.php',
    ];
    header('Location: ' . ($map[$role] ?? 'index.php'));
    exit;
}
