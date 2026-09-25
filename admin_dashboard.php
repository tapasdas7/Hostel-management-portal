<?php
/**
 * Admin Dashboard
 * Role: admin
 * Panels: Metrics, Add Student, Student CRUD, Manage Hostels & Managers,
 *         Room Allocation, Complaints, Notices, Visitors, Vacancy
 */

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin_shared.php';
require_role('admin');

$student = current_student();

/* ---- Manager accounts ---- */
$managersResult = $conn->query(
    'SELECT s.id, s.sic_number, s.first_name, s.last_name, s.email, s.mobile_number,
            h.hostel_name
     FROM students s
     LEFT JOIN administrators a ON a.student_id = s.id
     LEFT JOIN hostels h ON h.id = a.hostel_id
     WHERE s.role = "manager"
     ORDER BY s.first_name'
);
$managers = $managersResult->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?= e(PORTAL_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="dashboard-body" id="dashboardBody">

<nav class="sidebar" id="sidebar">
    <div class="brand">
        <img class="brand-logo rounded-1 p-2" src="./assets/images/brand-logo.png">
        <div class="brand-text">
            <span class="brand-line">Silicon</span>
            <span class="brand-line">Residence</span>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Toggle sidebar">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link active" data-panel="metrics" data-title="Super Admin Dashboard">
                <i class="bi bi-graph-up"></i> <span>Metrics Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="add-student" data-title="Add Student Profile">
                <i class="bi bi-person-plus-fill"></i> <span>Add Student</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="students" data-title="Student Master Records">
                <i class="bi bi-person-lines-fill"></i> <span>Student Records</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="managers" data-title="Manage Hostels & Managers">
                <i class="bi bi-shield-lock"></i> <span>Managers & Hostels</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="allocation" data-title="Room Allocation Control">
                <i class="bi bi-clipboard-check"></i> <span>Room Allocation</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="complaints" data-title="Complaints Desk & Resolution">
                <i class="bi bi-exclamation-circle"></i> <span>Complaints</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="notice" data-title="Notice Broadcaster">
                <i class="bi bi-megaphone"></i> <span>Notice Broadcaster</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="visitors" data-title="Visitors Control">
                <i class="bi bi-people-fill"></i> <span>Visitors Control</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="leave" data-title="Leave Applications Desk">
                <i class="bi bi-calendar2-x"></i> <span>Leave Desk</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="nightscan" data-title="Attendance Scanner">
                <i class="bi bi-qr-barcode-scan"></i> <span>Attendance</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="fines" data-title="Fine Collector">
                <i class="bi bi-cash-coin"></i> <span>Fine Collector</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="wifi" data-title="Wi-Fi Management">
                <i class="bi bi-wifi"></i> <span>Wi-Fi Mgmt</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="admin-rooms" data-title="Room Infrastructure Control">
                <i class="bi bi-building"></i> <span>Room Infrastructure</span>
            </a>
        </li>
        <li class="nav-item mt-3">
            <a class="nav-link" href="logout.php">
                <i class="bi bi-box-arrow-right"></i> <span>Logout</span>
            </a>
        </li>
    </ul>
</nav>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="main-content">
    <div class="topbar">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="menuToggle">
                <i class="bi bi-list"></i>
            </button>
            <h2 class="page-title" id="pageTitle">Super Admin Dashboard</h2>
        </div>
        <div class="student-chip">
            <div class="avatar"><?= e(strtoupper(substr($student['first_name'], 0, 1))) ?></div>
            <div>
                <div class="fw-semibold"><?= e($student['first_name']) ?></div>
                <div class="text-muted small">
                    <?= e($student['sic_number']) ?>
                    <span class="badge bg-primary ms-1">Admin</span>
                </div>
            </div>
        </div>
    </div>

    <?php if ($actionMsg): ?>
        <div class="alert alert-success py-2 small" id="adminActionAlert"><?= e($actionMsg) ?></div>
    <?php endif; ?>
    <?php if ($actionErr): ?>
        <div class="alert alert-danger py-2 small" id="adminActionAlert"><?= e($actionErr) ?></div>
    <?php endif; ?>

    <!-- Panel: Metrics -->
    <div class="panel active" id="panel-metrics">
        <div class="row g-3 mb-3">
            <div class="col-sm-6 col-lg-3">
                <div class="card metric-card h-100" style="cursor: pointer;" onclick="window.location.href='admin_dashboard.php?panel=students'">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="metric-icon bg-primary-subtle text-primary"><i class="bi bi-people-fill"></i></div>
                        <div>
                            <div class="metric-value"><?= $metrics['total_students'] ?></div>
                            <div class="metric-label">Registered Students</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card metric-card h-100" style="cursor: pointer;" onclick="window.location.href='admin_dashboard.php?panel=admin-rooms'">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="metric-icon bg-info-subtle text-info"><i class="bi bi-door-open"></i></div>
                        <div>
                            <div class="metric-value"><?= $metrics['total_capacity'] ?></div>
                            <div class="metric-label">Total Room Capacity</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card metric-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="metric-icon bg-success-subtle text-success"><i class="bi bi-bed"></i></div>
                        <div>
                            <div class="metric-value"><?= $metrics['available_beds'] ?></div>
                            <div class="metric-label">Available Beds</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card metric-card h-100" style="cursor: pointer;" onclick="window.location.href='admin_dashboard.php?panel=complaints'">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="metric-icon bg-warning-subtle text-warning"><i class="bi bi-exclamation-triangle"></i></div>
                        <div>
                            <div class="metric-value"><?= $metrics['unresolved_complaints'] ?></div>
                            <div class="metric-label">Unresolved Complaints</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel: Add Student Profile -->
    <div class="panel" id="panel-add-student">
        <div class="card" style="max-width:640px">
            <div class="card-header"><i class="bi bi-person-plus-fill me-1"></i> Add Student Profile</div>
            <div class="card-body">
                <form method="POST" action="admin_dashboard.php" class="needs-validation" novalidate>
                    <input type="hidden" name="form_action" value="add_student">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">SIC Number</label>
                            <input type="text" name="sic_number" class="form-control" placeholder="e.g. SIC1003" required>
                            <div class="invalid-feedback">SIC number is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="text" name="password" class="form-control" placeholder="Initial password" required>
                            <div class="invalid-feedback">Password is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" required>
                            <div class="invalid-feedback">First name is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mobile</label>
                            <input type="text" name="mobile_number" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <input type="text" name="department" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Year of Study</label>
                            <input type="number" name="year_of_study" class="form-control" value="1" min="1" max="5">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-check-lg me-1"></i> Create Student</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Panel: Student Records (CRUD) -->
    <div class="panel" id="panel-students">
        <div class="card">
            <div class="card-header"><i class="bi bi-person-lines-fill me-1"></i> Student Master Records</div>
            <div class="card-body">
                <form method="GET" action="admin_dashboard.php" class="row g-2 align-items-end mb-3">
                    <input type="hidden" name="panel" value="students">
                    <div class="col-auto">
                        <label class="form-label small mb-1">Department</label>
                        <select name="filter_department" class="form-select form-select-sm">
                            <option value="">All</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= e($d) ?>" <?= $filterDept === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label small mb-1">Year</label>
                        <select name="filter_year" class="form-select form-select-sm">
                            <option value="">All</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?= (int)$y ?>" <?= $filterYear !== '' && (int)$filterYear === (int)$y ? 'selected' : '' ?>>Year <?= (int)$y ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filter</button>
                        <a href="admin_dashboard.php?panel=students" class="btn btn-sm btn-outline-secondary">Reset</a>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr><th>SIC</th><th>Name</th><th>Dept</th><th>Year</th><th>Mobile</th><th>Room</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allStudents as $s): ?>
                            <tr>
                                <td><code><?= e($s['sic_number']) ?></code></td>
                                <td><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
                                <td class="small"><?= e($s['department']) ?></td>
                                <td class="small"><?= (int)$s['year_of_study'] ?></td>
                                <td class="small"><?= e($s['mobile_number']) ?></td>
                                <td><?= e($s['room_number'] ?? '—') ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= (int)$s['id'] ?>">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <form method="POST" action="admin_dashboard.php" class="d-inline">
                                        <input type="hidden" name="form_action" value="delete_student">
                                        <input type="hidden" name="student_id" value="<?= (int)$s['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete student <?= e($s['sic_number']) ?>?')">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <div class="modal fade" id="editModal<?= (int)$s['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST" action="admin_dashboard.php" class="needs-validation" novalidate>
                                            <input type="hidden" name="form_action" value="update_student">
                                            <input type="hidden" name="student_id" value="<?= (int)$s['id'] ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Student - <?= e($s['sic_number']) ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row g-3">
                                                    <div class="col-md-6"><label class="form-label">First Name</label><input type="text" name="first_name" class="form-control" value="<?= e($s['first_name']) ?>" required></div>
                                                    <div class="col-md-6"><label class="form-label">Last Name</label><input type="text" name="last_name" class="form-control" value="<?= e($s['last_name']) ?>"></div>
                                                    <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($s['email']) ?>"></div>
                                                    <div class="col-md-6"><label class="form-label">Mobile</label><input type="text" name="mobile_number" class="form-control" value="<?= e($s['mobile_number']) ?>"></div>
                                                    <div class="col-md-6"><label class="form-label">Department</label><input type="text" name="department" class="form-control" value="<?= e($s['department']) ?>"></div>
                                                    <div class="col-md-6"><label class="form-label">Year</label><input type="number" name="year_of_study" class="form-control" value="<?= (int)$s['year_of_study'] ?>" min="1" max="5"></div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel: Manage Hostels & Managers -->
    <div class="panel" id="panel-managers">
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><i class="bi bi-shield-lock me-1"></i> Add Manager Account</div>
                    <div class="card-body">
                        <form method="POST" action="admin_dashboard.php" class="needs-validation" novalidate>
                            <input type="hidden" name="form_action" value="add_manager">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">SIC Number</label>
                                    <input type="text" name="sic_number" class="form-control" placeholder="e.g. SIC2002" required>
                                    <div class="invalid-feedback">SIC required.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Password</label>
                                    <input type="text" name="password" class="form-control" required>
                                    <div class="invalid-feedback">Password required.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control" required>
                                    <div class="invalid-feedback">First name required.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mobile</label>
                                    <input type="text" name="mobile_number" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Assigned Hostel</label>
                                    <select name="hostel_id" class="form-select">
                                        <option value="0">-- None --</option>
                                        <?php foreach ($hostels as $h): ?>
                                            <option value="<?= (int)$h['id'] ?>"><?= e($h['hostel_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-check-lg me-1"></i> Create Manager</button>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header"><i class="bi bi-building me-1"></i> Add Hostel</div>
                    <div class="card-body">
                        <form method="POST" action="admin_dashboard.php" class="needs-validation" novalidate>
                            <input type="hidden" name="form_action" value="add_hostel">
                            <div class="row g-3">
                                <div class="col-8">
                                    <label class="form-label">Hostel Name</label>
                                    <input type="text" name="hostel_name" class="form-control" required>
                                    <div class="invalid-feedback">Name required.</div>
                                </div>
                                <div class="col-4">
                                    <label class="form-label">Rooms</label>
                                    <input type="number" name="no_of_rooms" class="form-control" value="0" min="0">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-plus-lg me-1"></i> Add Hostel</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header"><i class="bi bi-shield-fill-check me-1"></i> Manager Accounts</div>
                    <div class="card-body">
                        <?php if (empty($managers)): ?>
                            <p class="text-muted text-center my-4">No manager accounts yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead><tr><th>SIC</th><th>Name</th><th>Mobile</th><th>Hostel</th><th>Action</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($managers as $m): ?>
                                        <tr>
                                            <td><code><?= e($m['sic_number']) ?></code></td>
                                            <td><?= e($m['first_name'] . ' ' . $m['last_name']) ?></td>
                                            <td class="small"><?= e($m['mobile_number']) ?></td>
                                            <td class="small"><?= e($m['hostel_name'] ?? '—') ?></td>
                                            <td>
                                                <form method="POST" action="admin_dashboard.php" class="d-inline">
                                                    <input type="hidden" name="form_action" value="delete_manager">
                                                    <input type="hidden" name="student_id" value="<?= (int)$m['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete manager <?= e($m['sic_number']) ?>?')">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header"><i class="bi bi-building-fill me-1"></i> Hostels</div>
                    <div class="card-body">
                        <?php if (empty($hostels)): ?>
                            <p class="text-muted text-center my-4">No hostels registered.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead><tr><th>Name</th><th>Rooms</th><th>Students</th><th>Action</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($hostels as $h): ?>
                                        <tr>
                                            <td><?= e($h['hostel_name']) ?></td>
                                            <td class="small"><?= (int)$h['no_of_rooms'] ?></td>
                                            <td class="small"><?= (int)$h['no_of_students'] ?></td>
                                            <td>
                                                <form method="POST" action="admin_dashboard.php" class="d-inline">
                                                    <input type="hidden" name="form_action" value="delete_hostel">
                                                    <input type="hidden" name="hostel_id" value="<?= (int)$h['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete hostel <?= e($h['hostel_name']) ?>?')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel: Room Allocation Control -->
    <div class="panel" id="panel-allocation">
        <div class="card">
            <div class="card-header"><i class="bi bi-clipboard-check me-1"></i> Room Allocation Control Engine</div>
            <div class="card-body">
                <?php if (empty($pendingApps)): ?>
                    <p class="text-muted text-center my-4">No applications submitted.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead><tr><th>App No</th><th>Student</th><th>Hostel</th><th>Year</th><th>Purpose</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($pendingApps as $a): ?>
                                <tr>
                                    <td><code><?= e($a['application_no']) ?></code></td>
                                    <td><?= e($a['first_name'] . ' (' . $a['sic_number'] . ')') ?></td>
                                    <td class="small"><?= e($a['hostel_name']) ?></td>
                                    <td class="small"><?= e($a['academic_year']) ?></td>
                                    <td class="small"><?= e($a['purpose']) ?></td>
                                    <td><span class="badge <?= statusBadgeClass($a['status']) ?>"><?= e($a['status']) ?></span></td>
                                    <td>
                                        <?php if ($a['status'] === 'Pending'): ?>
                                            <form method="POST" action="admin_dashboard.php" class="d-flex gap-1 flex-wrap">
                                                <input type="hidden" name="form_action" value="allocate_room">
                                                <input type="hidden" name="application_id" value="<?= (int)$a['id'] ?>">
                                                <input type="hidden" name="student_id" value="<?= (int)$a['student_id'] ?>">
                                                <select name="room_id" class="form-select form-select-sm" style="width:140px" required>
                                                    <option value="">Room...</option>
                                                    <?php foreach ($availableRooms as $rm): ?>
                                                        <option value="<?= (int)$rm['id'] ?>"><?= e($rm['room_number']) ?> (<?= (int)($rm['total_capacity'] - $rm['occupied']) ?> free)</option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Allocate?')"><i class="bi bi-check-lg"></i></button>
                                            </form>
                                            <form method="POST" action="admin_dashboard.php" class="d-inline mt-1">
                                                <input type="hidden" name="form_action" value="reject_application">
                                                <input type="hidden" name="application_id" value="<?= (int)$a['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Reject?')"><i class="bi bi-x-lg"></i> Reject</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small">Reviewed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Panel: Complaints Resolution -->
    <div class="panel" id="panel-complaints">
        <div class="card">
            <div class="card-header"><i class="bi bi-exclamation-circle me-1"></i> Complaints Desk & Ticket Resolution</div>
            <div class="card-body">
                <?php if (empty($allComplaints)): ?>
                    <p class="text-muted text-center my-4">No complaints filed.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead><tr><th>Ticket</th><th>Student</th><th>Category</th><th>Subject</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($allComplaints as $c): ?>
                                <tr>
                                    <td><code><?= e($c['ticket_id']) ?></code></td>
                                    <td class="small"><?= e($c['first_name'] . ' (' . $c['sic_number'] . ')') ?></td>
                                    <td class="small"><?= e($c['category']) ?></td>
                                    <td class="small"><?= e($c['subject']) ?></td>
                                    <td><span class="badge <?= complaintBadgeClass($c['status']) ?>"><?= e($c['status']) ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#compModal<?= (int)$c['id'] ?>">
                                            <i class="bi bi-pencil"></i> Update
                                        </button>
                                    </td>
                                </tr>
                                <div class="modal fade" id="compModal<?= (int)$c['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="admin_dashboard.php">
                                                <input type="hidden" name="form_action" value="update_complaint">
                                                <input type="hidden" name="complaint_id" value="<?= (int)$c['id'] ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Resolve Ticket <?= e($c['ticket_id']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="small"><strong>Issue:</strong> <?= e($c['subject']) ?></p>
                                                    <p class="small"><strong>Description:</strong> <?= e($c['description']) ?></p>
                                                    <div class="mb-3">
                                                        <label class="form-label">Status</label>
                                                        <select name="status" class="form-select">
                                                            <option value="Pending" <?= $c['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                            <option value="In-Progress" <?= $c['status'] === 'In-Progress' ? 'selected' : '' ?>>In-Progress</option>
                                                            <option value="Resolved" <?= $c['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Manager/Admin Remarks</label>
                                                        <textarea name="admin_remarks" class="form-control" rows="3"><?= e($c['admin_remarks']) ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Panel: Notice Broadcaster -->
    <div class="panel" id="panel-notice">
        <div class="card" style="max-width:640px">
            <div class="card-header"><i class="bi bi-megaphone me-1"></i> Publish a Notice</div>
            <div class="card-body">
                <form method="POST" action="admin_dashboard.php" class="needs-validation" novalidate>
                    <input type="hidden" name="form_action" value="publish_notice">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required>
                        <div class="invalid-feedback">Title is required.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content</label>
                        <textarea name="content" class="form-control" rows="5" required></textarea>
                        <div class="invalid-feedback">Content is required.</div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i> Publish Notice</button>
                </form>
            </div>
        </div>
        <div class="card mt-4">
            <div class="card-header"><i class="bi bi-clock-history me-1"></i> Recent Notices</div>
            <div class="card-body">
                <?php if (empty($notices)): ?>
                    <p class="text-muted text-center my-4">No notices published yet.</p>
                <?php else: ?>
                    <?php foreach ($notices as $n): ?>
                        <div class="notice-item">
                            <h6 class="mb-1"><?= e($n['title']) ?></h6>
                            <div class="notice-date mb-1"><?= date('d M Y, h:i A', strtotime($n['created_at'])) ?></div>
                            <p class="mb-0 text-muted small"><?= nl2br(e($n['content'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Panel: Visitors Control -->
    <div class="panel" id="panel-visitors">
        <div class="card">
            <div class="card-header"><i class="bi bi-people-fill me-1"></i> Visitors Control</div>
            <div class="card-body">
                <?php if (empty($allVisitors)): ?>
                    <p class="text-muted text-center my-4">No visitors recorded.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead><tr><th>Visitor</th><th>Relationship</th><th>Host Student</th><th>Room</th><th>In</th><th>Out</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($allVisitors as $v): ?>
                                <tr>
                                    <td><?= e($v['visitor_name']) ?></td>
                                    <td class="small"><?= e($v['relationship_to_student']) ?></td>
                                    <td class="small"><?= e($v['first_name'] . ' (' . $v['sic_number'] . ')') ?></td>
                                    <td class="small"><?= e($v['room_number'] ?? '—') ?></td>
                                    <td class="small"><?= date('d M Y, h:i A', strtotime($v['in_time'])) ?></td>
                                    <td class="small">
                                        <?php if ($v['out_time']): ?>
                                            <span class="badge bg-secondary"><?= date('h:i A', strtotime($v['out_time'])) ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Inside</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$v['out_time']): ?>
                                            <form method="POST" action="admin_dashboard.php" class="d-inline">
                                                <input type="hidden" name="form_action" value="mark_visitor_out">
                                                <input type="hidden" name="visitor_id" value="<?= (int)$v['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-right"></i> Mark Out</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small">Departed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Panel: Leave Applications Approval Desk -->
    <div class="panel" id="panel-leave">
        <div class="card">
            <div class="card-header"><i class="bi bi-calendar2-x me-1"></i> Leave Applications Approval Desk</div>
            <div class="card-body">
                <?php if (empty($allLeaves)): ?>
                    <p class="text-muted text-center my-4">No leave applications submitted.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead><tr><th>Student</th><th>Start</th><th>End</th><th>Reason</th><th>Submitted</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($allLeaves as $l): ?>
                                <tr>
                                    <td><?= e($l['first_name'] . ' (' . $l['sic_number'] . ')') ?></td>
                                    <td class="small"><?= date('d M Y', strtotime($l['start_date'])) ?></td>
                                    <td class="small"><?= date('d M Y', strtotime($l['end_date'])) ?></td>
                                    <td class="small"><?= e($l['reason']) ?></td>
                                    <td class="small"><?= date('d M Y', strtotime($l['submitted_at'])) ?></td>
                                    <td><span class="badge <?= statusBadgeClass($l['status']) ?>"><?= e($l['status']) ?></span></td>
                                    <td>
                                        <?php if ($l['status'] === 'Pending'): ?>
                                            <form method="POST" action="admin_dashboard.php" class="d-inline">
                                                <input type="hidden" name="form_action" value="approve_leave">
                                                <input type="hidden" name="leave_id" value="<?= (int)$l['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this leave?')"><i class="bi bi-check-lg"></i> Approve</button>
                                            </form>
                                            <form method="POST" action="admin_dashboard.php" class="d-inline">
                                                <input type="hidden" name="form_action" value="reject_leave">
                                                <input type="hidden" name="leave_id" value="<?= (int)$l['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Reject this leave?')"><i class="bi bi-x-lg"></i> Reject</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small">Reviewed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Panel: Attendance Scanner -->
    <div class="panel" id="panel-nightscan">
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><i class="bi bi-qr-barcode-scan me-1"></i> Digital Attendance Scanner</div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Enter or scan a student's SIC number to mark them present for tonight's attendance.</p>
                        <form method="POST" action="admin_dashboard.php" class="needs-validation" novalidate>
                            <input type="hidden" name="form_action" value="night_scan">
                            <div class="mb-3">
                                <label class="form-label">Student SIC Number</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                                    <input type="text" name="sic_number" class="form-control" placeholder="e.g. SIC1001" required autofocus>
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Scan</button>
                                </div>
                                <div class="invalid-feedback">Please enter a SIC number.</div>
                            </div>
                        </form>
                        <hr>
                        <form method="POST" action="admin_dashboard.php">
                            <input type="hidden" name="form_action" value="process_absentees">
                            <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Close tonight\'s scan and fine all absent hostellites ₹50 each?')">
                                <i class="bi bi-box-arrow-right me-1"></i> Close Attendance Scan & Process Absentees
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header"><i class="bi bi-clock-history me-1"></i> Tonight's Scan Log</div>
                    <div class="card-body">
                        <?php if (empty($nightScans)): ?>
                            <p class="text-muted text-center my-4">No scans recorded yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead><tr><th>Student</th><th>Room</th><th>Time</th><th>Note</th><th>Fine</th><th>Status</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($nightScans as $ns): ?>
                                        <tr>
                                            <td><?= e($ns['first_name'] . ' (' . $ns['sic_number'] . ')') ?></td>
                                            <td class="small"><?= e($ns['room_number'] ?? '—') ?></td>
                                            <td class="small"><?= date('d M Y, h:i A', strtotime($ns['log_time'])) ?></td>
                                            <td class="small"><?= e($ns['status_note']) ?></td>
                                            <td class="small"><?= $ns['fine_amount'] > 0 ? '&#8377;' . number_format((float)$ns['fine_amount'], 2) : '—' ?></td>
                                            <td>
                                                <?php if ($ns['fine_status'] === 'Unpaid'): ?>
                                                    <span class="badge bg-danger">Unpaid</span>
                                                <?php elseif ($ns['fine_status'] === 'Paid'): ?>
                                                    <span class="badge bg-success">Paid</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel: Fine Collector -->
    <div class="panel" id="panel-fines">
        <div class="card">
            <div class="card-header"><i class="bi bi-cash-coin me-1"></i> Fine Collector Ledger</div>
            <div class="card-body">
                <?php if (empty($unpaidFines)): ?>
                    <p class="text-muted text-center my-4">No unpaid fines. All clear!</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead><tr><th>Student</th><th>Room</th><th>Date</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($unpaidFines as $f): ?>
                                <tr>
                                    <td><?= e($f['first_name'] . ' (' . $f['sic_number'] . ')') ?></td>
                                    <td class="small"><?= e($f['room_number'] ?? '—') ?></td>
                                    <td class="small"><?= date('d M Y, h:i A', strtotime($f['log_time'])) ?></td>
                                    <td><strong>&#8377;<?= number_format((float)$f['fine_amount'], 2) ?></strong></td>
                                    <td><span class="badge bg-danger">Unpaid</span></td>
                                    <td>
                                        <form method="POST" action="admin_dashboard.php" class="d-inline">
                                            <input type="hidden" name="form_action" value="mark_fine_paid">
                                            <input type="hidden" name="log_id" value="<?= (int)$f['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i> Mark as Paid</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Panel: Wi-Fi Management -->
    <div class="panel" id="panel-wifi">
        <div class="card">
            <div class="card-header"><i class="bi bi-wifi me-1"></i> Hostel Wi-Fi Management</div>
            <div class="card-body">
                <p class="text-muted small mb-3">Update Wi-Fi credentials for entire hostels. All rooms within a hostel share the same network.</p>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead><tr><th>Hostel</th><th>Rooms</th><th>Students</th><th>Wi-Fi Name</th><th>Wi-Fi Password</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($hostels as $h): ?>
                            <tr>
                                <td><strong><?= e($h['hostel_name']) ?></strong></td>
                                <td class="small"><?= (int)$h['no_of_rooms'] ?></td>
                                <td class="small"><?= (int)$h['no_of_students'] ?></td>
                                <form method="POST" action="admin_dashboard.php" class="d-flex gap-1 align-items-center">
                                    <input type="hidden" name="form_action" value="update_wifi">
                                    <input type="hidden" name="hostel_id" value="<?= (int)$h['id'] ?>">
                                    <td><input type="text" name="wifi_name" class="form-control form-control-sm" value="<?= e($h['wifi_name']) ?>" style="width:160px"></td>
                                    <td><input type="text" name="wifi_password" class="form-control form-control-sm" value="<?= e($h['wifi_password']) ?>" style="width:140px"></td>
                                    <td><button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> Update</button></td>
                                </form>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel: Room Infrastructure Control -->
    <div class="panel" id="panel-admin-rooms">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><i class="bi bi-plus-circle me-1"></i> Add New Room</div>
                    <div class="card-body">
                        <form method="POST" action="admin_dashboard.php" class="needs-validation" novalidate>
                            <input type="hidden" name="form_action" value="add_room">
                            <div class="mb-3">
                                <label class="form-label">Room Number</label>
                                <input type="text" name="room_number" class="form-control" placeholder="e.g. A-101" required>
                                <div class="invalid-feedback">Room number is required.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Hostel</label>
                                <select name="hostel_id" class="form-select" required>
                                    <option value="">-- Select hostel --</option>
                                    <?php foreach ($hostels as $h): ?>
                                        <option value="<?= (int)$h['id'] ?>"><?= e($h['hostel_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a hostel.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Room Type</label>
                                <select name="type" class="form-select">
                                    <option value="Non-AC">Non-AC</option>
                                    <option value="AC">AC</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Sharing Style</label>
                                <select name="sharing_style" class="form-select">
                                    <option value="Shared">Shared</option>
                                    <option value="Single">Single</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Total Capacity</label>
                                <input type="number" name="total_capacity" class="form-control" value="2" min="1" max="10" required>
                                <div class="invalid-feedback">Capacity must be at least 1.</div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i> Create Room</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header"><i class="bi bi-building me-1"></i> Room Inventory</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead><tr><th>Room</th><th>Hostel</th><th>Type</th><th>Sharing</th><th>Capacity</th><th>Occupied</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($allRoomsInfra as $r): ?>
                                    <tr>
                                        <td><strong><?= e($r['room_number']) ?></strong></td>
                                        <td class="small"><?= e($r['hostel_name'] ?? '—') ?></td>
                                        <td><span class="badge bg-light text-dark"><?= e($r['type']) ?></span></td>
                                        <td><span class="badge bg-light text-dark"><?= e($r['sharing_style']) ?></span></td>
                                        <td><?= (int)$r['total_capacity'] ?></td>
                                        <td><?= (int)$r['occupied'] ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editRoomModal<?= (int)$r['id'] ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" action="admin_dashboard.php" class="d-inline">
                                                <input type="hidden" name="form_action" value="delete_room">
                                                <input type="hidden" name="room_id" value="<?= (int)$r['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete room <?= e($r['room_number']) ?>? This cannot be undone.')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <!-- Edit Room Modal -->
                                    <div class="modal fade" id="editRoomModal<?= (int)$r['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="admin_dashboard.php" class="needs-validation" novalidate>
                                                    <input type="hidden" name="form_action" value="update_room">
                                                    <input type="hidden" name="room_id" value="<?= (int)$r['id'] ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Room <?= e($r['room_number']) ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label">Room Type</label>
                                                            <select name="type" class="form-select">
                                                                <option value="Non-AC" <?= $r['type'] === 'Non-AC' ? 'selected' : '' ?>>Non-AC</option>
                                                                <option value="AC" <?= $r['type'] === 'AC' ? 'selected' : '' ?>>AC</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Sharing Style</label>
                                                            <select name="sharing_style" class="form-select">
                                                                <option value="Shared" <?= $r['sharing_style'] === 'Shared' ? 'selected' : '' ?>>Shared</option>
                                                                <option value="Single" <?= $r['sharing_style'] === 'Single' ? 'selected' : '' ?>>Single</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Total Capacity</label>
                                                            <input type="number" name="total_capacity" class="form-control" value="<?= (int)$r['total_capacity'] ?>" min="1" max="10" required>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/dashboard.js"></script>
<script>
    // Debug script to check if toggle button is working
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('sidebarToggle');
        if (toggleBtn) {
            console.log('Toggle button found:', toggleBtn);
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Toggle button clicked');
                const sidebar = document.getElementById('sidebar');
                const body = document.getElementById('dashboardBody');
                if (sidebar && body) {
                    sidebar.classList.toggle('collapsed');
                    body.classList.toggle('collapsed');
                    console.log('Classes toggled. Sidebar collapsed:', sidebar.classList.contains('collapsed'));
                    
                    // Update icon
                    const icon = toggleBtn.querySelector('i');
                    if (icon) {
                        icon.className = sidebar.classList.contains('collapsed') 
                            ? 'bi bi-chevron-right' 
                            : 'bi bi-chevron-left';
                    }
                }
            });
        } else {
            console.error('Toggle button not found');
        }
    });
</script>
</body>
</html>
