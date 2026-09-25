<?php
/**
 * Student Dashboard
 * Role: student
 * State A (unallocated): notices, room availability, application status + form
 * State B (allocated): notices, gate logs, my room + roommates, complaints, visitors
 */

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_role('student');

$student   = current_student();
$studentId = $student['student_id'];

/* ---- Determine allocation state ---- */
$myRoomStmt = $conn->prepare(
    'SELECT r.*, h.wifi_name, h.wifi_password FROM rooms r
     INNER JOIN students s ON s.room_id = r.id
     LEFT JOIN hostels h ON h.id = r.hostel_id
     WHERE s.id = ?'
);
$myRoomStmt->bind_param('i', $studentId);
$myRoomStmt->execute();
$result = $myRoomStmt->get_result();
$myRoom = $result->fetch_assoc();

$isAllocated = $myRoom !== false;

/* =========================================================
   POST HANDLERS (allocated student actions)
   ========================================================= */
$complaintMsg = '';
$complaintErr = '';
$visitorMsg   = '';
$visitorErr   = '';
$appMsg       = '';
$appErr       = '';
$leaveMsg     = '';
$leaveErr     = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['form_action'] ?? '';

    /* ---- New complaint ---- */
    if ($action === 'new_complaint') {
        $category    = trim($_POST['category'] ?? '');
        $subject     = trim($_POST['subject'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $allowedCats = ['Plumbing', 'Electrical', 'Housekeeping', 'Wi-Fi'];

        if ($category === '' || $subject === '' || $description === '') {
            $complaintErr = 'All fields are required.';
        } elseif (!in_array($category, $allowedCats, true)) {
            $complaintErr = 'Invalid category.';
        } else {
            $ticketId = 'TKT-' . date('Y') . '-' . strtoupper(substr(uniqid(), -5));
            $stmt = $conn->prepare(
                'INSERT INTO complaints (student_id, ticket_id, category, subject, description, status)
                 VALUES (?, ?, ?, ?, ?, "Pending")'
            );
            $stmt->bind_param('issss', $studentId, $ticketId, $category, $subject, $description);
            $stmt->execute();
            $complaintMsg = 'Complaint submitted. Ticket ID: ' . $ticketId;
        }
    }

    /* ---- New visitor registration ---- */
    if ($action === 'new_visitor') {
        $vName     = trim($_POST['visitor_name'] ?? '');
        $vDate     = trim($_POST['visit_date'] ?? '');
        $inTime    = trim($_POST['in_time'] ?? '');
        $relation  = trim($_POST['relationship_to_student'] ?? '');

        $allowedRel = ['Parent', 'Sibling', 'Relative', 'Friend', 'Other'];
        if ($vName === '' || $vDate === '' || $inTime === '' || $relation === '') {
            $visitorErr = 'All fields are required.';
        } elseif (!in_array($relation, $allowedRel, true)) {
            $visitorErr = 'Invalid relationship selected.';
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO visitors (student_id, visitor_name, visit_date, in_time, relationship_to_student)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('issss', $studentId, $vName, $vDate, $inTime, $relation);
            $stmt->execute();
            $visitorMsg = 'Visitor registered successfully.';
        }
    }

        /* ---- New leave application (allocated student) ---- */
    if ($action === 'new_leave') {
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate   = trim($_POST['end_date'] ?? '');
        $reason    = trim($_POST['reason'] ?? '');

        if ($startDate === '' || $endDate === '' || $reason === '') {
            $leaveErr = 'All fields are required.';
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO leave_applications (student_id, start_date, end_date, reason, status)
                 VALUES (?, ?, ?, ?, "Pending")'
            );
            $stmt->bind_param('isss', $studentId, $startDate, $endDate, $reason);
            $stmt->execute();
            $leaveMsg = 'Leave application submitted successfully.';
        }
    }

    /* ---- New room application (unallocated student) ---- */
    if ($action === 'new_application') {
        $hostelId     = (int) ($_POST['hostel_id'] ?? 0);
        $academicYear = trim($_POST['academic_year'] ?? '');
        $purpose      = trim($_POST['purpose'] ?? '');

        if ($hostelId <= 0 || $academicYear === '' || $purpose === '') {
            $appErr = 'All fields are required.';
        } else {
            $appNo = 'APP-' . date('Y') . '-' . strtoupper(substr(uniqid(), -5));
            $stmt = $conn->prepare(
                'INSERT INTO applications (student_id, hostel_id, application_no, academic_year, purpose, status)
                 VALUES (?, ?, ?, ?, ?, "Pending")'
            );
            $stmt->bind_param('iisss', $studentId, $hostelId, $appNo, $academicYear, $purpose);
            $stmt->execute();
            $appMsg = 'Application submitted. Application No: ' . $appNo;
        }
    }
}

/* =========================================================
   DATA FETCHERS
   ========================================================= */

/* ---- Notices (both states) ---- */
$noticesResult = $conn->query('SELECT id, title, content, created_at FROM notices ORDER BY created_at DESC LIMIT 10');
$notices = $noticesResult->fetch_all(MYSQLI_ASSOC);

/* ---- All rooms (State A only) ---- */
$allRoomsResult = $conn->query('SELECT * FROM rooms ORDER BY room_number');
$allRooms = $allRoomsResult->fetch_all(MYSQLI_ASSOC);

/* ---- My applications (State A) ---- */
$myAppsStmt = $conn->prepare(
    'SELECT a.application_no, h.hostel_name, a.academic_year, a.purpose, a.status, a.submitted_at
     FROM applications a
     INNER JOIN hostels h ON h.id = a.hostel_id
     WHERE a.student_id = ?
     ORDER BY a.submitted_at DESC'
);
$myAppsStmt->bind_param('i', $studentId);
$myAppsStmt->execute();
$myAppsResult = $myAppsStmt->get_result();
$myApplications = $myAppsResult->fetch_all(MYSQLI_ASSOC);

/* ---- Hostels for dropdown ---- */
$hostelsResult = $conn->query('SELECT id, hostel_name, no_of_rooms FROM hostels ORDER BY hostel_name');
$hostels = $hostelsResult->fetch_all(MYSQLI_ASSOC);

/* ---- Gate logs (State B) ---- */
$gateStmt = $conn->prepare(
    'SELECT id, log_type, log_time, status_note, fine_amount, fine_status
     FROM gate_logs WHERE student_id = ? ORDER BY log_time DESC'
);
$gateStmt->bind_param('i', $studentId);
$gateStmt->execute();
$gateResult = $gateStmt->get_result();
$gateLogs = $gateResult->fetch_all(MYSQLI_ASSOC);

/* ---- My leave applications (State B) ---- */
$myLeavesStmt = $conn->prepare(
    'SELECT id, start_date, end_date, reason, status, submitted_at
     FROM leave_applications WHERE student_id = ? ORDER BY submitted_at DESC'
);
$myLeavesStmt->bind_param('i', $studentId);
$myLeavesStmt->execute();
$myLeavesResult = $myLeavesStmt->get_result();
$myLeaves = $myLeavesResult->fetch_all(MYSQLI_ASSOC);

/* ---- My fines (State B: Night Scan fines) ---- */
$myFinesStmt = $conn->prepare(
    'SELECT id, log_time, status_note, fine_amount, fine_status
     FROM gate_logs
     WHERE student_id = ? AND log_type = "Night Scan" AND fine_amount > 0
     ORDER BY log_time DESC'
);
$myFinesStmt->bind_param('i', $studentId);
$myFinesStmt->execute();
$myFinesResult = $myFinesStmt->get_result();
$myFines = $myFinesResult->fetch_all(MYSQLI_ASSOC);

/* ---- Roommates (State B) ---- */
$roommates = [];
if ($isAllocated) {
    $rmStmt = $conn->prepare(
        'SELECT s.sic_number, s.first_name, s.last_name, s.department, s.year_of_study, s.mobile_number
         FROM students s
         WHERE s.room_id = ? AND s.id != ?
         ORDER BY s.first_name'
    );
    $rmStmt->bind_param('ii', $myRoom['id'], $studentId);
    $rmStmt->execute();
    $rmResult = $rmStmt->get_result();
    $roommates = $rmResult->fetch_all(MYSQLI_ASSOC);
}

/* ---- My complaints (State B) ---- */
$myComplaintsStmt = $conn->prepare(
    'SELECT ticket_id, category, subject, description, status, admin_remarks, created_at
     FROM complaints WHERE student_id = ? ORDER BY created_at DESC'
);
$myComplaintsStmt->bind_param('i', $studentId);
$myComplaintsStmt->execute();
$myComplaintsResult = $myComplaintsStmt->get_result();
$myComplaints = $myComplaintsResult->fetch_all(MYSQLI_ASSOC);

/* ---- My visitors (State B) ---- */
$myVisitorsStmt = $conn->prepare(
    'SELECT id, visitor_name, visit_date, in_time, out_time, relationship_to_student
     FROM visitors WHERE student_id = ? ORDER BY visit_date DESC, in_time DESC'
);
$myVisitorsStmt->bind_param('i', $studentId);
$myVisitorsStmt->execute();
$myVisitorsResult = $myVisitorsStmt->get_result();
$myVisitors = $myVisitorsResult->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?= e(PORTAL_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="./bootstrap/bootstrap.bundle.min.js"></script>
</head>
<body class="dashboard-body" id="dashboardBody">

<nav class="sidebar" id="sidebar">
    <div class="brand">
        <img class="brand-logo" src="./assets/images/brand-logo.png" alt="Brand logo">
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
            <a class="nav-link active" data-panel="notices" data-title="Notice Board">
                <i class="bi bi-megaphone"></i> <span>Notice Board</span>
            </a>
        </li>

        <?php if (!$isAllocated): /* ---- STATE A ---- */ ?>
        <li class="nav-item">
            <a class="nav-link" data-panel="availability" data-title="Room Availability">
                <i class="bi bi-door-closed"></i> <span>Room Availability</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="applications" data-title="Room Allocation Status">
                <i class="bi bi-file-earmark-text"></i> <span>Allocation Status</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="blocked" data-title="Access Denied">
                <i class="bi bi-lock-fill"></i> <span>Portal Services</span>
            </a>
        </li>

        <?php else: /* ---- STATE B ---- */ ?>
        <li class="nav-item">
            <a class="nav-link" data-panel="myroom" data-title="My Room & Roommates">
                <i class="bi bi-door-open"></i> <span>My Room</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="gatelogs" data-title="Digital Gate Log">
                <i class="bi bi-journal-text"></i> <span>Gate Log</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="complaints" data-title="Complaints Desk">
                <i class="bi bi-exclamation-circle"></i> <span>Complaints</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="visitors" data-title="Visitors Log">
                <i class="bi bi-people"></i> <span>Visitors</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="leave" data-title="Leave Application">
                <i class="bi bi-calendar2-x"></i> <span>Leave Application</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="fines" data-title="Penalties / Fine Tracker">
                <i class="bi bi-cash-coin"></i> <span>Fine Tracker</span>
            </a>
        </li>
        <?php endif; ?>

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
            <h2 class="page-title" id="pageTitle">Notice Board</h2>
        </div>
        <div class="student-chip">
            <div class="avatar"><?= e(strtoupper(substr($student['first_name'], 0, 1))) ?></div>
            <div>
                <div class="fw-semibold"><?= e($student['first_name']) ?></div>
                <div class="text-muted small">
                    <?= e($student['sic_number']) ?>
                    <span class="badge bg-light text-dark ms-1">Student</span>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$isAllocated): ?>
    <!-- ============ STATE A: UNALLOCATED ============ -->

    <!-- Panel: Notice Board -->
    <div class="panel active" id="panel-notices">
        <div class="card">
            <div class="card-header"><i class="bi bi-megaphone me-1"></i> Recent Notices</div>
            <div class="card-body">
                <?php if (empty($notices)): ?>
                    <p class="text-muted text-center my-4">No notices published yet.</p>
                <?php else: ?>
                    <?php foreach ($notices as $n): ?>
                        <div class="notice-item">
                            <h6 class="mb-1"><?= e($n['title']) ?></h6>
                            <div class="notice-date mb-1">
                                <i class="bi bi-calendar3"></i>
                                <?= date('d M Y, h:i A', strtotime($n['created_at'])) ?>
                            </div>
                            <p class="mb-0 text-muted"><?= nl2br(e($n['content'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Panel: Room Availability -->
    <div class="panel" id="panel-availability">
        <div class="card">
            <div class="card-header"><i class="bi bi-door-closed me-1"></i> General Room Availability</div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($allRooms as $room):
                        $available = $room['total_capacity'] - $room['occupied'];
                        $statusClass = $available > 0 ? 'status-available' : 'status-full';
                        $badgeClass  = $available > 0 ? 'bg-success' : 'bg-danger';
                    ?>
                        <div class="col-sm-6 col-lg-4">
                            <div class="card room-card <?= $statusClass ?> h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <h6 class="room-number mb-0"><?= e($room['room_number']) ?></h6>
                                        <span class="badge <?= $badgeClass ?>"><?= $available > 0 ? 'Available' : 'Full' ?></span>
                                    </div>
                                    <div class="mt-2 small">
                                        <span class="badge bg-light text-dark me-1"><?= e($room['type']) ?></span>
                                        <span class="badge bg-light text-dark"><?= e($room['sharing_style']) ?></span>
                                    </div>
                                    <hr class="my-2">
                                    <div class="d-flex justify-content-between small text-muted">
                                        <span>Capacity: <?= (int)$room['total_capacity'] ?></span>
                                        <span>Vacancy: <strong><?= $available ?></strong></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel: Application Status + Apply Form -->
    <div class="panel" id="panel-applications">
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><i class="bi bi-file-earmark-plus me-1"></i> Apply for Hostel Room</div>
                    <div class="card-body">
                        <?php if ($appMsg): ?>
                            <div class="alert alert-success py-2 small"><?= e($appMsg) ?></div>
                        <?php endif; ?>
                        <?php if ($appErr): ?>
                            <div class="alert alert-danger py-2 small"><?= e($appErr) ?></div>
                        <?php endif; ?>
                        <form method="POST" action="student_dashboard.php" class="needs-validation" novalidate>
                            <input type="hidden" name="form_action" value="new_application">
                            <div class="mb-3">
                                <label class="form-label">Hostel Choice</label>
                                <select name="hostel_id" class="form-select" required>
                                    <option value="">-- Select hostel --</option>
                                    <?php foreach ($hostels as $h): ?>
                                        <option value="<?= (int)$h['id'] ?>"><?= e($h['hostel_name']) ?> (<?= (int)$h['no_of_rooms'] ?> rooms)</option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please choose a hostel.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Academic Year</label>
                                <input type="text" name="academic_year" class="form-control" placeholder="e.g. 2026-2027" required>
                                <div class="invalid-feedback">Academic year is required.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Purpose</label>
                                <textarea name="purpose" class="form-control" rows="3" placeholder="Reason for application" required></textarea>
                                <div class="invalid-feedback">Purpose is required.</div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Submit Application</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header"><i class="bi bi-clock-history me-1"></i> Your Application Status</div>
                    <div class="card-body">
                        <?php if (empty($myApplications)): ?>
                            <p class="text-muted text-center my-4">No applications submitted yet. Use the form to apply.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr><th>Application No</th><th>Hostel</th><th>Year</th><th>Date</th><th>Status</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($myApplications as $a): ?>
                                            <tr>
                                                <td><code><?= e($a['application_no']) ?></code></td>
                                                <td><?= e($a['hostel_name']) ?></td>
                                                <td class="small"><?= e($a['academic_year']) ?></td>
                                                <td class="small"><?= date('d M Y', strtotime($a['submitted_at'])) ?></td>
                                                <td><span class="badge <?= statusBadgeClass($a['status']) ?>"><?= e($a['status']) ?></span></td>
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

    <!-- Panel: Blocked (Access Denied) -->
    <div class="panel" id="panel-blocked">
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-lock-fill display-1 text-muted"></i>
                <h4 class="mt-3">Access Denied</h4>
                <p class="text-muted">Please wait for your room allocation request to be approved before using hostel portal services.</p>
                <p class="text-muted small mt-2">Leave Application and Fine Tracker features are strictly reserved for hostellites with active room allocations.</p>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ============ STATE B: ALLOCATED ============ -->

    <!-- Panel: My Room & Roommates -->
    <div class="panel active" id="panel-myroom">
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card room-card status-allocated">
                    <div class="card-body">
                        <span class="badge bg-primary mb-2">Your Room</span>
                        <h5 class="room-number"><?= e($myRoom['room_number']) ?></h5>
                        <div class="row text-muted small">
                            <div class="col-6 mb-1"><strong>Type:</strong> <?= e($myRoom['type']) ?></div>
                            <div class="col-6 mb-1"><strong>Sharing:</strong> <?= e($myRoom['sharing_style']) ?></div>
                            <div class="col-6"><strong>Capacity:</strong> <?= (int)$myRoom['total_capacity'] ?></div>
                            <div class="col-6"><strong>Occupied:</strong> <?= (int)$myRoom['occupied'] ?></div>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-wifi text-primary"></i>
                            <div class="small">
                                <span class="badge bg-success me-1">Wi-Fi</span>
                                <strong><?= e($myRoom['wifi_name'] ?? 'Hostel_Zone_5G') ?></strong>
                                <span class="text-muted ms-1">| Pass: <code><?= e($myRoom['wifi_password'] ?? 'HostelPass@2026') ?></code></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header"><i class="bi bi-people me-1"></i> Your Roommates</div>
                    <div class="card-body">
                        <?php if (empty($roommates)): ?>
                            <p class="text-muted text-center my-4">You are the only occupant of this room.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr><th>Name</th><th>SIC</th><th>Dept</th><th>Year</th><th>Mobile</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($roommates as $rm): ?>
                                            <tr>
                                                <td><?= e($rm['first_name'] . ' ' . $rm['last_name']) ?></td>
                                                <td><code><?= e($rm['sic_number']) ?></code></td>
                                                <td class="small"><?= e($rm['department']) ?></td>
                                                <td class="small"><?= (int)$rm['year_of_study'] ?></td>
                                                <td class="small"><?= e($rm['mobile_number']) ?></td>
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

    <!-- Panel: Notice Board -->
    <div class="panel" id="panel-notices">
        <div class="card">
            <div class="card-header"><i class="bi bi-megaphone me-1"></i> Recent Notices</div>
            <div class="card-body">
                <?php if (empty($notices)): ?>
                    <p class="text-muted text-center my-4">No notices published yet.</p>
                <?php else: ?>
                    <?php foreach ($notices as $n): ?>
                        <div class="notice-item">
                            <h6 class="mb-1"><?= e($n['title']) ?></h6>
                            <div class="notice-date mb-1">
                                <i class="bi bi-calendar3"></i>
                                <?= date('d M Y, h:i A', strtotime($n['created_at'])) ?>
                            </div>
                            <p class="mb-0 text-muted"><?= nl2br(e($n['content'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Panel: Gate Logs -->
    <div class="panel" id="panel-gatelogs">
        <div class="card">
            <div class="card-header"><i class="bi bi-journal-text me-1"></i> Gate Movement Log</div>
            <div class="card-body">
                <?php if (empty($gateLogs)): ?>
                    <p class="text-muted text-center my-4">No gate logs recorded yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr><th>Log ID</th><th>Type</th><th>Timestamp</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gateLogs as $g): ?>
                                    <tr>
                                        <td>#<?= (int)$g['id'] ?></td>
                                        <td><span class="badge <?= gateLogBadgeClass($g['log_type']) ?>"><?= e($g['log_type']) ?></span></td>
                                        <td class="small"><?= date('d M Y, h:i A', strtotime($g['log_time'])) ?></td>
                                        <td class="small"><?= e($g['status_note']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Panel: Complaints -->
    <div class="panel" id="panel-complaints">
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><i class="bi bi-plus-circle me-1"></i> Submit a Complaint</div>
                    <div class="card-body">
                        <?php if ($complaintMsg): ?>
                            <div class="alert alert-success py-2 small"><?= e($complaintMsg) ?></div>
                        <?php endif; ?>
                        <?php if ($complaintErr): ?>
                            <div class="alert alert-danger py-2 small"><?= e($complaintErr) ?></div>
                        <?php endif; ?>
                        <form method="POST" action="student_dashboard.php" class="needs-validation" novalidate>
                            <input type="hidden" name="form_action" value="new_complaint">
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select" required>
                                    <option value="">-- Select --</option>
                                    <option value="Plumbing">Plumbing</option>
                                    <option value="Electrical">Electrical</option>
                                    <option value="Housekeeping">Housekeeping</option>
                                    <option value="Wi-Fi">Wi-Fi</option>
                                </select>
                                <div class="invalid-feedback">Choose a category.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Subject</label>
                                <input type="text" name="subject" class="form-control" placeholder="Brief subject" required>
                                <div class="invalid-feedback">Subject is required.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="4" placeholder="Describe the issue" required></textarea>
                                <div class="invalid-feedback">Description is required.</div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Submit Complaint</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header"><i class="bi bi-clock-history me-1"></i> Your Complaint History</div>
                    <div class="card-body">
                        <?php if (empty($myComplaints)): ?>
                            <p class="text-muted text-center my-4">No complaints submitted yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr><th>Ticket</th><th>Date</th><th>Subject</th><th>Status</th><th>Remarks</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($myComplaints as $c): ?>
                                            <tr>
                                                <td><code><?= e($c['ticket_id']) ?></code></td>
                                                <td class="small"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                                                <td><?= e($c['subject']) ?></td>
                                                <td><span class="badge <?= complaintBadgeClass($c['status']) ?>"><?= e($c['status']) ?></span></td>
                                                <td class="small"><?= e($c['admin_remarks']) ?></td>
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

    <!-- Panel: Visitors -->
    <div class="panel" id="panel-visitors">
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><i class="bi bi-person-plus me-1"></i> Register a Visitor</div>
                    <div class="card-body">
                        <?php if ($visitorMsg): ?>
                            <div class="alert alert-success py-2 small"><?= e($visitorMsg) ?></div>
                        <?php endif; ?>
                        <?php if ($visitorErr): ?>
                            <div class="alert alert-danger py-2 small"><?= e($visitorErr) ?></div>
                        <?php endif; ?>
                        <form method="POST" action="student_dashboard.php" class="needs-validation" novalidate>
                            <input type="hidden" name="form_action" value="new_visitor">
                            <div class="mb-3">
                                <label class="form-label">Visitor Name</label>
                                <input type="text" name="visitor_name" class="form-control" placeholder="Full name" required>
                                <div class="invalid-feedback">Visitor name is required.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Relationship to Student</label>
                                <select name="relationship_to_student" class="form-select" required>
                                    <option value="">-- Select --</option>
                                    <option value="Parent">Parent</option>
                                    <option value="Sibling">Sibling</option>
                                    <option value="Relative">Relative</option>
                                    <option value="Friend">Friend</option>
                                    <option value="Other">Other</option>
                                </select>
                                <div class="invalid-feedback">Please select a relationship.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Visit Date</label>
                                <input type="date" name="visit_date" class="form-control" required>
                                <div class="invalid-feedback">Visit date is required.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">In Time</label>
                                <input type="datetime-local" name="in_time" class="form-control" required>
                                <div class="invalid-feedback">In time is required.</div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Register Visitor</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header"><i class="bi bi-clock-history me-1"></i> Your Visitor History</div>
                    <div class="card-body">
                        <?php if (empty($myVisitors)): ?>
                            <p class="text-muted text-center my-4">No visitors recorded yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr><th>Visitor</th><th>Relationship</th><th>Visit Date</th><th>In</th><th>Out</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($myVisitors as $v): ?>
                                            <tr>
                                                <td><?= e($v['visitor_name']) ?></td>
                                                <td class="small"><?= e($v['relationship_to_student']) ?></td>
                                                <td class="small"><?= date('d M Y', strtotime($v['visit_date'])) ?></td>
                                                <td class="small"><?= date('d M Y, h:i A', strtotime($v['in_time'])) ?></td>
                                                <td class="small">
                                                    <?php if ($v['out_time']): ?>
                                                        <span class="badge bg-secondary"><?= date('h:i A', strtotime($v['out_time'])) ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Inside</span>
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

    <!-- Panel: Leave Application -->
    <div class="panel" id="panel-leave">
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><i class="bi bi-calendar2-x me-1"></i> Apply for Leave</div>
                    <div class="card-body">
                        <?php if ($leaveMsg): ?>
                            <div class="alert alert-success py-2 small"><?= e($leaveMsg) ?></div>
                        <?php endif; ?>
                        <?php if ($leaveErr): ?>
                            <div class="alert alert-danger py-2 small"><?= e($leaveErr) ?></div>
                        <?php endif; ?>
                        <form method="POST" action="student_dashboard.php" class="needs-validation" novalidate>
                            <input type="hidden" name="form_action" value="new_leave">
                            <div class="mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control" required>
                                <div class="invalid-feedback">Start date is required.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" class="form-control" required>
                                <div class="invalid-feedback">End date is required.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reason</label>
                                <textarea name="reason" class="form-control" rows="3" placeholder="Reason for leave" required></textarea>
                                <div class="invalid-feedback">Reason is required.</div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Submit Leave Request</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header"><i class="bi bi-clock-history me-1"></i> Your Leave History</div>
                    <div class="card-body">
                        <?php if (empty($myLeaves)): ?>
                            <p class="text-muted text-center my-4">No leave applications submitted yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead><tr><th>Start</th><th>End</th><th>Reason</th><th>Submitted</th><th>Status</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($myLeaves as $l): ?>
                                            <tr>
                                                <td class="small"><?= date('d M Y', strtotime($l['start_date'])) ?></td>
                                                <td class="small"><?= date('d M Y', strtotime($l['end_date'])) ?></td>
                                                <td class="small"><?= e($l['reason']) ?></td>
                                                <td class="small"><?= date('d M Y', strtotime($l['submitted_at'])) ?></td>
                                                <td><span class="badge <?= statusBadgeClass($l['status']) ?>"><?= e($l['status']) ?></span></td>
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

    <!-- Panel: Penalties / Fine Tracker -->
    <div class="panel" id="panel-fines">
        <div class="card">
            <div class="card-header"><i class="bi bi-cash-coin me-1"></i> Penalties & Fine Tracker</div>
            <div class="card-body">
                <?php if (empty($myFines)): ?>
                    <p class="text-muted text-center my-4">No fines recorded. You are all clear!</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead><tr><th>Date</th><th>Note</th><th>Amount</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($myFines as $f): ?>
                                    <tr>
                                        <td class="small"><?= date('d M Y, h:i A', strtotime($f['log_time'])) ?></td>
                                        <td class="small"><?= e($f['status_note']) ?></td>
                                        <td><strong>&#8377;<?= number_format((float)$f['fine_amount'], 2) ?></strong></td>
                                        <td>
                                            <?php if ($f['fine_status'] === 'Unpaid'): ?>
                                                <span class="badge bg-danger"><?= e($f['fine_status']) ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-success"><?= e($f['fine_status']) ?></span>
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

    <?php endif; ?>
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
