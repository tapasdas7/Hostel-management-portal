<?php
/**
 * Login Landing Page
 * Renders a clean Bootstrap 5 login card for the Hostel Management System.
 * Displays an error message if login_process.php redirected back with one.
 */
require_once __DIR__ . '/includes/auth.php';

// If already logged in, go straight to dashboard.
if (isset($_SESSION['student_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Grab any error message passed via the URL after a failed login attempt.
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostel Portal - Login</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom styles -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="login-wrapper">

    <!-- ============ Login Card ============ -->
    <div class="login-card">

        <div class="brand-icon">
            <i class="bi bi-building-fill"></i>
        </div>

        <h1>Hostel Portal</h1>
        <p class="subtitle">Sign in with your Student Identification Code</p>

        <!-- Server-side error message (hidden by default) -->
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger py-2 small" role="alert">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Login form: posted to login_process.php -->
        <form action="login_process.php" method="POST" class="needs-validation" novalidate>

            <!-- SIC Number -->
            <div class="mb-3">
                <label for="sic_number" class="form-label">SIC Number</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                    <input type="text"
                           class="form-control"
                           id="sic_number"
                           name="sic_number"
                           placeholder="e.g. SIC1001"
                           required
                           autocomplete="username">
                    <div class="invalid-feedback">Please enter your SIC number.</div>
                </div>
            </div>

            <!-- Password -->
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password"
                           class="form-control"
                           id="password"
                           name="password"
                           placeholder="Enter password"
                           required
                           autocomplete="current-password">
                    <button class="btn btn-outline-secondary"
                            type="button"
                            id="togglePassword"
                            tabindex="-1">
                        <i class="bi bi-eye"></i>
                    </button>
                    <div class="invalid-feedback">Please enter your password.</div>
                </div>
            </div>

            <!-- Submit -->
            <div class="d-grid">
                <button type="submit" class="btn btn-primary py-2">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                </button>
            </div>

        </form>
    </div>
</div>

<!-- Bootstrap 5 JS bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ---- Bootstrap client-side form validation ----
(function () {
    'use strict';

    const form = document.querySelector('.needs-validation');

    form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    }, false);

    // ---- Show / hide password toggle ----
    const toggle = document.getElementById('togglePassword');
    const pwd    = document.getElementById('password');

    toggle.addEventListener('click', function () {
        const isPwd = pwd.type === 'password';
        pwd.type = isPwd ? 'text' : 'password';
        toggle.querySelector('i').className = isPwd ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
})();
</script>

</body>
</html>
