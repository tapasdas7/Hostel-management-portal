<?php
/**
 * Dashboard page (protected).
 * Requires an authenticated student or administrator session.
 * Renders a fixed sidebar + topbar and these modules:
 *   Student modules:
 *     1. Room Tracking Panel
 *     2. Complaints Desk Panel
 *     3. Notice Board Panel
 *     4. Digital Gate Log Panel
 *     5. Room Applications Panel
 *     6. Vacate Room Panel
 *     7. Visitors Log Panel
 *   Administrator modules (visible only to role = admin):
 *     8. Student Records
 *     9. Room Allocation (Applications)
 *    10. Vacancy Management (Vacating Requests)
 *    11. Furniture Inventory
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_auth();

$student = current_student();
$studentId = $student['student_id'];

/* ===================================================================
   1. ROOM TRACKING DATA
   - Fetch ALL rooms (for the grid overview).
   - Fetch the room allocated to THIS student (if any).
   =================================================================== */
$roomsStmt = $pdo->query('SELECT * FROM rooms ORDER BY room_number');
$allRooms  = $roomsStmt->fetchAll();

$myRoomStmt = $pdo->prepare(
    'SELECT r.* FROM rooms r
     INNER JOIN students s ON s.room_id = r.id
     WHERE s.id = :sid'
);
$myRoomStmt->execute([':sid' => $studentId]);
$myRoom = $myRoomStmt->fetch();

/* ===================================================================
   2. COMPLAINTS DATA
   - Handle a new complaint submission (INSERT).
   - Fetch the student's existing complaints (SELECT by student_id).
   =================================================================== */
$complaintMsg = '';
$complaintErr = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['form_action'] ?? '') === 'new_complaint') {

    $category    = trim($_POST['category']    ?? '');
    $subject     = trim($_POST['subject']     ?? '');
    $description = trim($_POST['description'] ?? '');

    $allowedCategories = ['Plumbing', 'Electrical', 'Housekeeping', 'Wi-Fi'];

    if ($category === '' || $subject === '' || $description === '') {
        $complaintErr = 'All fields are required.';
    } elseif (!in_array($category, $allowedCategories, true)) {
        $complaintErr = 'Invalid category selected.';
    } else {
        // Generate a unique ticket id: TKT-YYYY-NNN
        $ticketId = 'TKT-' . date('Y') . '-' . strtoupper(substr(uniqid(), -5));

        $insert = $pdo->prepare(
            'INSERT INTO complaints (student_id, ticket_id, category, subject, description, status)
             VALUES (:sid, :ticket, :cat, :subj, :descr, "Pending")'
        );
        $insert->execute([
            ':sid'    => $studentId,
            ':ticket' => $ticketId,
            ':cat'    => $category,
            ':subj'   => $subject,
            ':descr'  => $description,
        ]);
        $complaintMsg = 'Complaint submitted successfully. Ticket ID: ' . $ticketId;
    }
}

$myComplaintsStmt = $pdo->prepare(
    'SELECT ticket_id, category, subject, description, status, created_at
     FROM complaints
     WHERE student_id = :sid
     ORDER BY created_at DESC'
);
$myComplaintsStmt->execute([':sid' => $studentId]);
$myComplaints = $myComplaintsStmt->fetchAll();

/* ===================================================================
   3. NOTICES DATA
   - Fetch recent notices ordered by date (newest first).
   =================================================================== */
$noticesStmt = $pdo->query('SELECT id, title, content, created_at
                            FROM notices
                            ORDER BY created_at DESC
                            LIMIT 10');
$notices = $noticesStmt->fetchAll();

/* ===================================================================
   4. GATE LOGS DATA
   - Fetch the student's check-in / check-out / late-night logs.
   =================================================================== */
$gateStmt = $pdo->prepare(
    'SELECT id, log_type, log_time, status_note
     FROM gate_logs
     WHERE student_id = :sid
     ORDER BY log_time DESC'
);
$gateStmt->execute([':sid' => $studentId]);
$gateLogs = $gateStmt->fetchAll();

/* ===================================================================
   5. HOSTELS + APPLICATIONS + VACATING + VISITORS (SRS/SDD flows)
   =================================================================== */
// Hostels list (for application dropdown)
$hostelsStmt = $pdo->query('SELECT id, hostel_name, no_of_rooms, no_of_students FROM hostels ORDER BY hostel_name');
$hostels = $hostelsStmt->fetchAll();

// ---- Handle new room application submission ----
$applicationMsg = '';
$applicationErr = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['form_action'] ?? '') === 'new_application') {
    $hostelId      = (int) ($_POST['hostel_id'] ?? 0);
    $academicYear  = trim($_POST['academic_year'] ?? '');
    $purpose       = trim($_POST['purpose'] ?? '');

    if ($hostelId <= 0 || $academicYear === '' || $purpose === '') {
        $applicationErr = 'All fields are required.';
    } else {
        $applicationNo = 'APP-' . date('Y') . '-' . strtoupper(substr(uniqid(), -5));
        $insert = $pdo->prepare(
            'INSERT INTO applications (student_id, hostel_id, application_no, academic_year, purpose, status)
             VALUES (:sid, :hid, :appno, :ay, :purpose, "Pending")'
        );
        $insert->execute([
            ':sid'     => $studentId,
            ':hid'     => $hostelId,
            ':appno'   => $applicationNo,
            ':ay'      => $academicYear,
            ':purpose' => $purpose,
        ]);
        $applicationMsg = 'Application submitted successfully. Application No: ' . $applicationNo;
    }
}

// Fetch student's applications
$myAppsStmt = $pdo->prepare(
    'SELECT a.application_no, h.hostel_name, a.academic_year, a.purpose, a.status, a.submitted_at
     FROM applications a
     INNER JOIN hostels h ON h.id = a.hostel_id
     WHERE a.student_id = :sid
     ORDER BY a.submitted_at DESC'
);
$myAppsStmt->execute([':sid' => $studentId]);
$myApplications = $myAppsStmt->fetchAll();

// ---- Handle vacating request submission ----
$vacatingMsg = '';
$vacatingErr = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['form_action'] ?? '') === 'new_vacating') {
    $reason      = trim($_POST['reason'] ?? '');
    $vacateDate  = trim($_POST['vacate_date'] ?? '');

    if ($reason === '' || $vacateDate === '') {
        $vacatingErr = 'All fields are required.';
    } else {
        $roomId = $myRoom ? (int) $myRoom['id'] : null;
        $insert = $pdo->prepare(
            'INSERT INTO vacating_requests (student_id, room_id, reason, vacate_date, status)
             VALUES (:sid, :rid, :reason, :vdate, "Pending")'
        );
        $insert->execute([
            ':sid'    => $studentId,
            ':rid'    => $roomId,
            ':reason' => $reason,
            ':vdate'  => $vacateDate,
        ]);
        $vacatingMsg = 'Vacating request submitted successfully.';
    }
}

// Fetch student's vacating requests
$myVacatingStmt = $pdo->prepare(
    'SELECT vr.reason, vr.vacate_date, vr.status, vr.submitted_at, r.room_number
     FROM vacating_requests vr
     LEFT JOIN rooms r ON r.id = vr.room_id
     WHERE vr.student_id = :sid
     ORDER BY vr.submitted_at DESC'
);
$myVacatingStmt->execute([':sid' => $studentId]);
$myVacating = $myVacatingStmt->fetchAll();

// ---- Handle new visitor registration (student form) ----
$visitorMsg = '';
$visitorErr = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['form_action'] ?? '') === 'new_visitor') {
    $visitorName = trim($_POST['visitor_name'] ?? '');
    $visitDate   = trim($_POST['visit_date'] ?? '');
    $inTime      = trim($_POST['in_time'] ?? '');

    if ($visitorName === '' || $visitDate === '' || $inTime === '') {
        $visitorErr = 'All fields are required.';
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO visitors (student_id, visitor_name, visit_date, in_time)
             VALUES (:sid, :vname, :vdate, :vtime)'
        );
        $insert->execute([
            ':sid'   => $studentId,
            ':vname' => $visitorName,
            ':vdate' => $visitDate,
            ':vtime' => $inTime,
        ]);
        $visitorMsg = 'Visitor registered successfully.';
    }
}

// ---- Visitors log (student's own visitors) ----
$myVisitorsStmt = $pdo->prepare(
    'SELECT id, visitor_name, visit_date, in_time, out_time
     FROM visitors
     WHERE student_id = :sid
     ORDER BY visit_date DESC, in_time DESC'
);
$myVisitorsStmt->execute([':sid' => $studentId]);
$myVisitors = $myVisitorsStmt->fetchAll();

/* ===================================================================
   6. ADMINISTRATOR DATA + ACTIONS (only loaded when role == admin)
   =================================================================== */
$isAdmin = ($student['role'] ?? 'student') === 'admin';
$allStudents = [];
$pendingApps = [];
$pendingVacating = [];
$furnitureList = [];
$adminMetrics = [];
$allVisitors = [];
$adminActionMsg = '';
$adminActionErr = '';

if ($isAdmin) {

    /* ---- Admin POST handlers: approve / reject / mark-out ---- */
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $action = $_POST['form_action'] ?? '';

        // -- Approve a room application: set status + auto-allocate a room --
        if ($action === 'approve_application') {
            $appId     = (int) ($_POST['application_id'] ?? 0);
            $stuId     = (int) ($_POST['student_id'] ?? 0);

            if ($appId > 0 && $stuId > 0) {
                // Find an available room (occupied < capacity)
                $avail = $pdo->query('SELECT id FROM rooms
                                      WHERE occupied < total_capacity
                                      ORDER BY room_number LIMIT 1');
                $room = $avail->fetch();

                if ($room) {
                    $pdo->beginTransaction();
                    try {
                        $updApp = $pdo->prepare(
                            'UPDATE applications SET status = "Approved", reviewed_at = NOW()
                             WHERE id = :aid AND status = "Pending"'
                        );
                        $updApp->execute([':aid' => $appId]);

                        $updStu = $pdo->prepare('UPDATE students SET room_id = :rid WHERE id = :sid');
                        $updStu->execute([':rid' => $room['id'], ':sid' => $stuId]);

                        $updRoom = $pdo->prepare('UPDATE rooms SET occupied = occupied + 1 WHERE id = :rid');
                        $updRoom->execute([':rid' => $room['id']]);

                        $pdo->commit();
                        $adminActionMsg = 'Application approved. Room allocated to student.';
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        $adminActionErr = 'Failed to approve application.';
                    }
                } else {
                    $adminActionErr = 'No available rooms to allocate.';
                }
            }
        }

        // -- Reject a room application --
        if ($action === 'reject_application') {
            $appId = (int) ($_POST['application_id'] ?? 0);
            if ($appId > 0) {
                $upd = $pdo->prepare(
                    'UPDATE applications SET status = "Rejected", reviewed_at = NOW()
                     WHERE id = :aid AND status = "Pending"'
                );
                $upd->execute([':aid' => $appId]);
                $adminActionMsg = 'Application rejected.';
            }
        }

        // -- Approve a vacating request: set status + free the room --
        if ($action === 'approve_vacating') {
            $vrId  = (int) ($_POST['vacating_id'] ?? 0);
            $stuId = (int) ($_POST['student_id'] ?? 0);
            $roomId = (int) ($_POST['room_id'] ?? 0);

            if ($vrId > 0 && $stuId > 0) {
                $pdo->beginTransaction();
                try {
                    $updVr = $pdo->prepare(
                        'UPDATE vacating_requests SET status = "Approved", reviewed_at = NOW()
                         WHERE id = :vid AND status = "Pending"'
                    );
                    $updVr->execute([':vid' => $vrId]);

                    // Set student's room_id to NULL (vacate the bed)
                    $updStu = $pdo->prepare('UPDATE students SET room_id = NULL WHERE id = :sid');
                    $updStu->execute([':sid' => $stuId]);

                    // Decrement room occupancy if we know the room
                    if ($roomId > 0) {
                        $updRoom = $pdo->prepare(
                            'UPDATE rooms SET occupied = GREATEST(occupied - 1, 0) WHERE id = :rid'
                        );
                        $updRoom->execute([':rid' => $roomId]);
                    }

                    $pdo->commit();
                    $adminActionMsg = 'Vacating request approved. Bed is now vacant.';
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $adminActionErr = 'Failed to approve vacating request.';
                }
            }
        }

        // -- Reject a vacating request --
        if ($action === 'reject_vacating') {
            $vrId = (int) ($_POST['vacating_id'] ?? 0);
            if ($vrId > 0) {
                $upd = $pdo->prepare(
                    'UPDATE vacating_requests SET status = "Rejected", reviewed_at = NOW()
                     WHERE id = :vid AND status = "Pending"'
                );
                $upd->execute([':vid' => $vrId]);
                $adminActionMsg = 'Vacating request rejected.';
            }
        }

        // -- Mark a visitor out (set out_time to current timestamp) --
        if ($action === 'mark_visitor_out') {
            $visitorId = (int) ($_POST['visitor_id'] ?? 0);
            if ($visitorId > 0) {
                $upd = $pdo->prepare('UPDATE visitors SET out_time = NOW() WHERE id = :vid AND out_time IS NULL');
                $upd->execute([':vid' => $visitorId]);
                $adminActionMsg = 'Visitor marked out.';
            }
        }
    }

    /* ---- Admin metrics (4 widgets) ---- */
    $adminMetrics['total_students'] = (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
    $adminMetrics['total_capacity'] = (int) $pdo->query('SELECT COALESCE(SUM(total_capacity),0) FROM rooms')->fetchColumn();
    $adminMetrics['available_beds'] = (int) $pdo->query('SELECT COALESCE(SUM(total_capacity - occupied),0) FROM rooms')->fetchColumn();
    $adminMetrics['unresolved_complaints'] = (int) $pdo->query(
        'SELECT COUNT(*) FROM complaints WHERE status IN ("Pending","In-Progress")'
    )->fetchColumn();

    /* ---- All students with filter support ---- */
    $filterDept = trim($_GET['filter_department'] ?? '');
    $filterYear = trim($_GET['filter_year'] ?? '');

    $studentQuery = 'SELECT s.id, s.sic_number, s.first_name, s.last_name, s.email,
                s.mobile_number, s.department, s.year_of_study,
                r.room_number, s.role
         FROM students s
         LEFT JOIN rooms r ON r.id = s.room_id';
    $where  = [];
    $params = [];

    if ($filterDept !== '') {
        $where[]  = 's.department = :dept';
        $params[':dept'] = $filterDept;
    }
    if ($filterYear !== '') {
        $where[]  = 's.year_of_study = :year';
        $params[':year'] = (int) $filterYear;
    }
    if ($where) {
        $studentQuery .= ' WHERE ' . implode(' AND ', $where);
    }
    $studentQuery .= ' ORDER BY s.first_name';

    $allStudentsStmt = $pdo->prepare($studentQuery);
    $allStudentsStmt->execute($params);
    $allStudents = $allStudentsStmt->fetchAll();

    // Distinct departments + years for the filter dropdowns
    $departments = $pdo->query('SELECT DISTINCT department FROM students WHERE department != "" ORDER BY department')->fetchAll(PDO::FETCH_COLUMN);
    $years = $pdo->query('SELECT DISTINCT year_of_study FROM students ORDER BY year_of_study')->fetchAll(PDO::FETCH_COLUMN);

    /* ---- All applications for admin view ---- */
    $pendingAppsStmt = $pdo->query(
        'SELECT a.id, a.application_no, s.id AS student_id, s.sic_number, s.first_name,
                h.hostel_name, a.academic_year, a.purpose, a.status, a.submitted_at
         FROM applications a
         INNER JOIN students s ON s.id = a.student_id
         INNER JOIN hostels h ON h.id = a.hostel_id
         ORDER BY a.submitted_at DESC'
    );
    $pendingApps = $pendingAppsStmt->fetchAll();

    /* ---- All vacating requests for admin view ---- */
    $pendingVacatingStmt = $pdo->query(
        'SELECT vr.id, s.id AS student_id, s.sic_number, s.first_name, r.id AS room_id,
                r.room_number, vr.reason, vr.vacate_date, vr.status, vr.submitted_at
         FROM vacating_requests vr
         INNER JOIN students s ON s.id = vr.student_id
         LEFT JOIN rooms r ON r.id = vr.room_id
         ORDER BY vr.submitted_at DESC'
    );
    $pendingVacating = $pendingVacatingStmt->fetchAll();

    /* ---- All active visitors (out_time IS NULL = still inside) ---- */
    $allVisitorsStmt = $pdo->query(
        'SELECT v.id, v.visitor_name, v.visit_date, v.in_time, v.out_time,
                s.first_name, s.sic_number, r.room_number
         FROM visitors v
         INNER JOIN students s ON s.id = v.student_id
         LEFT JOIN rooms r ON r.id = s.room_id
         ORDER BY v.in_time DESC'
    );
    $allVisitors = $allVisitorsStmt->fetchAll();

    // Furniture inventory
    $furnitureStmt = $pdo->query(
        'SELECT f.id, f.furniture_type, f.quantity, f.condition_note,
                r.room_number, h.hostel_name
         FROM furniture f
         INNER JOIN rooms r ON r.id = f.room_id
         LEFT JOIN hostels h ON h.id = f.hostel_id
         ORDER BY r.room_number'
    );
    $furnitureList = $furnitureStmt->fetchAll();
}

/** Helper: returns a Bootstrap badge class for an application / vacating status. */
function statusBadgeClass(string $status): string
{
    return match ($status) {
        'Pending'     => 'bg-warning text-dark',
        'Approved'    => 'bg-success',
        'Rejected'    => 'bg-danger',
        'In-Progress' => 'bg-info text-dark',
        'Resolved'    => 'bg-success',
        default       => 'bg-secondary',
    };
}

/** Helper: returns a Bootstrap badge class for a complaint status. */
function complaintBadgeClass(string $status): string
{
    return match ($status) {
        'Pending'      => 'bg-warning text-dark',
        'In-Progress'  => 'bg-info text-dark',
        'Resolved'     => 'bg-success',
        default        => 'bg-secondary',
    };
}

/** Helper: returns a Bootstrap badge class for a gate-log type. */
function gateLogBadgeClass(string $type): string
{
    return match ($type) {
        'Check-in'         => 'bg-success',
        'Check-out'        => 'bg-primary',
        'Late-night Leave' => 'bg-danger',
        default            => 'bg-secondary',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Hostel Portal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="dashboard-body">

<!-- ============ Sidebar ============ -->
<nav class="sidebar" id="sidebar">
    <div class="brand">
        <span class="brand-dot"></span>
        Hostel Portal
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link active" data-panel="rooms">
                <i class="bi bi-door-closed"></i> Room Tracking
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="complaints">
                <i class="bi bi-exclamation-circle"></i> Complaints Desk
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="notices">
                <i class="bi bi-megaphone"></i> Notice Board
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="gatelogs">
                <i class="bi bi-journal-text"></i> Digital Gate Log
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="applications">
                <i class="bi bi-file-earmark-text"></i> Applications
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="vacating">
                <i class="bi bi-box-arrow-out-right"></i> Vacate Room
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="visitors">
                <i class="bi bi-people"></i> Visitors Log
            </a>
        </li>

        <?php if ($isAdmin): ?>
        <li class="nav-item mt-3">
            <div class="sidebar-section-label">Administrator</div>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="admin-metrics">
                <i class="bi bi-graph-up"></i> Metrics Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="admin-students">
                <i class="bi bi-person-lines-fill"></i> Student Records
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="admin-applications">
                <i class="bi bi-clipboard-check"></i> Room Allocation
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="admin-vacating">
                <i class="bi bi-house-exit"></i> Vacancy Mgmt
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="admin-visitors">
                <i class="bi bi-people-fill"></i> Visitors Control
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-panel="admin-furniture">
                <i class="bi bi-lamp"></i> Furniture Inventory
            </a>
        </li>
        <?php endif; ?>

        <li class="nav-item mt-3">
            <a class="nav-link" href="logout.php">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </li>
    </ul>
</nav>

<!-- Mobile backdrop -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<!-- ============ Main Content ============ -->
<div class="main-content">

    <!-- Topbar -->
    <div class="topbar">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="menuToggle">
                <i class="bi bi-list"></i>
            </button>
            <h2 class="page-title" id="pageTitle">Room Tracking</h2>
        </div>
        <div class="student-chip">
            <div class="avatar"><?= htmlspecialchars(strtoupper(substr($student['first_name'], 0, 1))) ?></div>
            <div>
                <div class="fw-semibold"><?= htmlspecialchars($student['first_name']) ?></div>
                <div class="text-muted small">
                    <?= htmlspecialchars($student['sic_number']) ?>
                    <?php if ($isAdmin): ?>
                        <span class="badge bg-primary ms-1">Admin</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ====================================================
         PANEL 1: ROOM TRACKING
         ==================================================== -->
    <div class="panel active" id="panel-rooms">

        <!-- My allocated room (highlighted) -->
        <?php if ($myRoom): ?>
            <div class="card mb-4 room-card status-allocated">
                <div class="card-body">
                    <span class="badge bg-primary mb-2">Your Room</span>
                    <h5 class="room-number"><?= htmlspecialchars($myRoom['room_number']) ?></h5>
                    <div class="row text-muted small">
                        <div class="col-6 mb-1"><strong>Type:</strong> <?= htmlspecialchars($myRoom['type']) ?></div>
                        <div class="col-6 mb-1"><strong>Sharing:</strong> <?= htmlspecialchars($myRoom['sharing_style']) ?></div>
                        <div class="col-6"><strong>Capacity:</strong> <?= (int)$myRoom['total_capacity'] ?></div>
                        <div class="col-6"><strong>Occupied:</strong> <?= (int)$myRoom['occupied'] ?></div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No room has been allocated to you yet.</div>
        <?php endif; ?>

        <h6 class="text-muted mb-3">All Rooms Overview</h6>
        <div class="row g-3">
            <?php foreach ($allRooms as $room):
                $available = $room['total_capacity'] - $room['occupied'];
                $statusClass = $available > 0 ? 'status-available' : 'status-full';
                $statusLabel = $available > 0 ? 'Available' : 'Full';
                $badgeClass  = $available > 0 ? 'bg-success' : 'bg-danger';
            ?>
                <div class="col-sm-6 col-lg-4">
                    <div class="card room-card <?= $statusClass ?> h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <h6 class="room-number mb-0"><?= htmlspecialchars($room['room_number']) ?></h6>
                                <span class="badge <?= $badgeClass ?>"><?= $statusLabel ?></span>
                            </div>
                            <div class="mt-2 small">
                                <span class="badge bg-light text-dark me-1"><?= htmlspecialchars($room['type']) ?></span>
                                <span class="badge bg-light text-dark"><?= htmlspecialchars($room['sharing_style']) ?></span>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between small text-muted">
                                <span>Capacity: <?= (int)$room['total_capacity'] ?></span>
                                <span>Available: <strong><?= $available ?></strong></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ====================================================
         PANEL 2: COMPLAINTS DESK
         ==================================================== -->
    <div class="panel" id="panel-complaints">
        <div class="row g-4">

            <!-- Column A: New complaint form -->
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><i class="bi bi-plus-circle me-1"></i> Submit a Complaint</div>
                    <div class="card-body">

                        <?php if ($complaintMsg): ?>
                            <div class="alert alert-success py-2 small"><?= htmlspecialchars($complaintMsg) ?></div>
                        <?php endif; ?>
                        <?php if ($complaintErr): ?>
                            <div class="alert alert-danger py-2 small"><?= htmlspecialchars($complaintErr) ?></div>
                        <?php endif; ?>

                        <form method="POST" action="dashboard.php" class="needs-validation" novalidate>
                            <input type="hidden" name="form_action" value="new_complaint">

                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select" required>
                                    <option value="">-- Select category --</option>
                                    <option value="Plumbing">Plumbing</option>
                                    <option value="Electrical">Electrical</option>
                                    <option value="Housekeeping">Housekeeping</option>
                                    <option value="Wi-Fi">Wi-Fi</option>
                                </select>
                                <div class="invalid-feedback">Please choose a category.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Subject</label>
                                <input type="text" name="subject" class="form-control"
                                       placeholder="Brief subject" required>
                                <div class="invalid-feedback">Subject is required.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="4"
                                          placeholder="Describe the issue in detail" required></textarea>
                                <div class="invalid-feedback">Description is required.</div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Submit Complaint</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Column B: Complaint history -->
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
                                        <tr>
                                            <th>Ticket ID</th>
                                            <th>Date</th>
                                            <th>Subject</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($myComplaints as $c): ?>
                                            <tr>
                                                <td><code><?= htmlspecialchars($c['ticket_id']) ?></code></td>
                                                <td class="small"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                                                <td><?= htmlspecialchars($c['subject']) ?></td>
                                                <td><span class="badge <?= complaintBadgeClass($c['status']) ?>"><?= htmlspecialchars($c['status']) ?></span></td>
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

    <!-- ====================================================
         PANEL 3: NOTICE BOARD
         ==================================================== -->
    <div class="panel" id="panel-notices">
        <div class="card">
            <div class="card-header"><i class="bi bi-megaphone me-1"></i> Recent Notices</div>
            <div class="card-body">

                <?php if (empty($notices)): ?>
                    <p class="text-muted text-center my-4">No notices published yet.</p>
                <?php else: ?>
                    <div class="notice-feed">
                        <?php foreach ($notices as $n): ?>
                            <div class="notice-item">
                                <h6 class="mb-1"><?= htmlspecialchars($n['title']) ?></h6>
                                <div class="notice-date mb-1">
                                    <i class="bi bi-calendar3"></i>
                                    <?= date('d M Y, h:i A', strtotime($n['created_at'])) ?>
                                </div>
                                <p class="mb-0 text-muted"><?= nl2br(htmlspecialchars($n['content'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- ====================================================
         PANEL 4: DIGITAL GATE LOG
         ==================================================== -->
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
                                <tr>
                                    <th>Log ID</th>
                                    <th>Log Type</th>
                                    <th>Timestamp</th>
                                    <th>Status / Approval</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gateLogs as $g): ?>
                                    <tr>
                                        <td>#<?= (int)$g['id'] ?></td>
                                        <td><span class="badge <?= gateLogBadgeClass($g['log_type']) ?>"><?= htmlspecialchars($g['log_type']) ?></span></td>
                                        <td class="small"><?= date('d M Y, h:i A', strtotime($g['log_time'])) ?></td>
                                        <td class="small"><?= htmlspecialchars($g['status_note']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ====================================================
         PANEL 5: ROOM APPLICATIONS
         ==================================================== -->
    <div class="panel" id="panel-applications">
        <div class="row g-4">

            <!-- Column A: New application form -->
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><i class="bi bi-file-earmark-plus me-1"></i> Apply for a Room</div>
                    <div class="card-body">

                        <?php if ($applicationMsg): ?>
                            <div class="alert alert-success py-2 small"><?= htmlspecialchars($applicationMsg) ?></div>
                        <?php endif; ?>
                        <?php if ($applicationErr): ?>
                            <div class="alert alert-danger py-2 small"><?= htmlspecialchars($applicationErr) ?></div>
                        <?php endif; ?>

                        <form method="POST" action="dashboard.php" class="needs-validation" novalidate>
                            <input type="hidden" name="form_action" value="new_application">

                            <div class="mb-3">
                                <label class="form-label">Hostel</label>
                                <select name="hostel_id" class="form-select" required>
                                    <option value="">-- Select hostel --</option>
                                    <?php foreach ($hostels as $h): ?>
                                        <option value="<?= (int)$h['id'] ?>">
                                            <?= htmlspecialchars($h['hostel_name']) ?>
                                            (<?= (int)$h['no_of_rooms'] ?> rooms)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please choose a hostel.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Academic Year</label>
                                <input type="text" name="academic_year" class="form-control"
                                       placeholder="e.g. 2026-2027" required>
                                <div class="invalid-feedback">Academic year is required.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Purpose</label>
                                <textarea name="purpose" class="form-control" rows="3"
                                          placeholder="Reason for application" required></textarea>
                                <div class="invalid-feedback">Purpose is required.</div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Submit Application</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Column B: Application history -->
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header"><i class="bi bi-clock-history me-1"></i> Your Applications</div>
                    <div class="card-body">
                        <?php if (empty($myApplications)): ?>
                            <p class="text-muted text-center my-4">No applications submitted yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Application No</th>
                                            <th>Hostel</th>
                                            <th>Year</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($myApplications as $a): ?>
                                            <tr>
                                                <td><code><?= htmlspecialchars($a['application_no']) ?></code></td>
                                                <td><?= htmlspecialchars($a['hostel_name']) ?></td>
                                                <td class="small"><?= htmlspecialchars($a['academic_year']) ?></td>
                                                <td class="small"><?= date('d M Y', strtotime($a['submitted_at'])) ?></td>
                                                <td><span class="badge <?= statusBadgeClass($a['status']) ?>"><?= htmlspecialchars($a['status']) ?></span></td>
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

    <!-- ====================================================
         PANEL 6: VACATE ROOM
         ==================================================== -->
    <div class="panel" id="panel-vacating">
        <div class="row g-4">

            <!-- Column A: Vacating request form -->
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><i class="bi bi-box-arrow-out-right me-1"></i> Request Room Vacation</div>
                    <div class="card-body">

                        <?php if ($vacatingMsg): ?>
                            <div class="alert alert-success py-2 small"><?= htmlspecialchars($vacatingMsg) ?></div>
                        <?php endif; ?>
                        <?php if ($vacatingErr): ?>
                            <div class="alert alert-danger py-2 small"><?= htmlspecialchars($vacatingErr) ?></div>
                        <?php endif; ?>

                        <?php if (!$myRoom): ?>
                            <div class="alert alert-info py-2 small">You currently have no room allocated to vacate.</div>
                        <?php endif; ?>

                        <form method="POST" action="dashboard.php" class="needs-validation" novalidate>
                            <input type="hidden" name="form_action" value="new_vacating">

                            <div class="mb-3">
                                <label class="form-label">Vacate Date</label>
                                <input type="date" name="vacate_date" class="form-control" required>
                                <div class="invalid-feedback">Please select a vacate date.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Reason</label>
                                <textarea name="reason" class="form-control" rows="4"
                                          placeholder="Reason for vacating" required></textarea>
                                <div class="invalid-feedback">Reason is required.</div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Submit Request</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Column B: Vacating request history -->
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header"><i class="bi bi-clock-history me-1"></i> Your Vacating Requests</div>
                    <div class="card-body">
                        <?php if (empty($myVacating)): ?>
                            <p class="text-muted text-center my-4">No vacating requests submitted yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Room</th>
                                            <th>Vacate Date</th>
                                            <th>Reason</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($myVacating as $v): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($v['room_number'] ?? 'N/A') ?></td>
                                                <td class="small"><?= date('d M Y', strtotime($v['vacate_date'])) ?></td>
                                                <td class="small"><?= htmlspecialchars($v['reason']) ?></td>
                                                <td><span class="badge <?= statusBadgeClass($v['status']) ?>"><?= htmlspecialchars($v['status']) ?></span></td>
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

    <!-- ====================================================
         PANEL 7: VISITORS LOG (student view: form + history)
         ==================================================== -->
    <div class="panel" id="panel-visitors">
        <div class="row g-4">

            <!-- Column A: Register a new visitor -->
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><i class="bi bi-person-plus me-1"></i> Register a Visitor</div>
                    <div class="card-body">

                        <?php if ($visitorMsg): ?>
                            <div class="alert alert-success py-2 small"><?= htmlspecialchars($visitorMsg) ?></div>
                        <?php endif; ?>
                        <?php if ($visitorErr): ?>
                            <div class="alert alert-danger py-2 small"><?= htmlspecialchars($visitorErr) ?></div>
                        <?php endif; ?>

                        <form method="POST" action="dashboard.php" class="needs-validation" novalidate>
                            <input type="hidden" name="form_action" value="new_visitor">

                            <div class="mb-3">
                                <label class="form-label">Visitor Name</label>
                                <input type="text" name="visitor_name" class="form-control"
                                       placeholder="Full name of the guest" required>
                                <div class="invalid-feedback">Visitor name is required.</div>
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

            <!-- Column B: Visitor history -->
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
                                        <tr>
                                            <th>Visitor Name</th>
                                            <th>Visit Date</th>
                                            <th>In Time</th>
                                            <th>Out Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($myVisitors as $v): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($v['visitor_name']) ?></td>
                                                <td class="small"><?= date('d M Y', strtotime($v['visit_date'])) ?></td>
                                                <td class="small"><?= date('d M Y, h:i A', strtotime($v['in_time'])) ?></td>
                                                <td class="small">
                                                    <?php if ($v['out_time']): ?>
                                                        <span class="badge bg-secondary"><?= date('d M Y, h:i A', strtotime($v['out_time'])) ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Still Inside</span>
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

    <!-- ====================================================
         ADMIN PANEL 0: METRICS DASHBOARD
         ==================================================== -->
    <?php if ($isAdmin): ?>

    <?php if ($adminActionMsg): ?>
        <div class="alert alert-success py-2 small" id="adminActionAlert"><?= htmlspecialchars($adminActionMsg) ?></div>
    <?php endif; ?>
    <?php if ($adminActionErr): ?>
        <div class="alert alert-danger py-2 small" id="adminActionAlert"><?= htmlspecialchars($adminActionErr) ?></div>
    <?php endif; ?>

    <div class="panel" id="panel-admin-metrics">
        <div class="row g-3 mb-3">
            <div class="col-sm-6 col-lg-3">
                <div class="card metric-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="metric-icon bg-primary-subtle text-primary"><i class="bi bi-people-fill"></i></div>
                        <div>
                            <div class="metric-value"><?= $adminMetrics['total_students'] ?></div>
                            <div class="metric-label">Registered Students</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card metric-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="metric-icon bg-info-subtle text-info"><i class="bi bi-door-open"></i></div>
                        <div>
                            <div class="metric-value"><?= $adminMetrics['total_capacity'] ?></div>
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
                            <div class="metric-value"><?= $adminMetrics['available_beds'] ?></div>
                            <div class="metric-label">Available Beds</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card metric-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="metric-icon bg-warning-subtle text-warning"><i class="bi bi-exclamation-triangle"></i></div>
                        <div>
                            <div class="metric-value"><?= $adminMetrics['unresolved_complaints'] ?></div>
                            <div class="metric-label">Unresolved Complaints</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ====================================================
         ADMIN PANEL 1: STUDENT RECORDS (with filter)
         ==================================================== -->
    <div class="panel" id="panel-admin-students">
        <div class="card">
            <div class="card-header"><i class="bi bi-person-lines-fill me-1"></i> All Student Records</div>
            <div class="card-body">

                <!-- Filter bar -->
                <form method="GET" action="dashboard.php" class="row g-2 align-items-end mb-3">
                    <input type="hidden" name="panel" value="admin-students">
                    <div class="col-auto">
                        <label class="form-label small mb-1">Department</label>
                        <select name="filter_department" class="form-select form-select-sm">
                            <option value="">All</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= htmlspecialchars($d) ?>" <?= $filterDept === $d ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label small mb-1">Year</label>
                        <select name="filter_year" class="form-select form-select-sm">
                            <option value="">All</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?= (int)$y ?>" <?= $filterYear !== '' && (int)$filterYear === (int)$y ? 'selected' : '' ?>>
                                    Year <?= (int)$y ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filter</button>
                        <a href="dashboard.php?panel=admin-students" class="btn btn-sm btn-outline-secondary">Reset</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>SIC No.</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Year</th>
                                <th>Mobile</th>
                                <th>Room</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allStudents as $s): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($s['sic_number']) ?></code></td>
                                    <td><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></td>
                                    <td class="small"><?= htmlspecialchars($s['department']) ?></td>
                                    <td class="small"><?= (int)$s['year_of_study'] ?></td>
                                    <td class="small"><?= htmlspecialchars($s['mobile_number']) ?></td>
                                    <td><?= htmlspecialchars($s['room_number'] ?? '—') ?></td>
                                    <td><span class="badge <?= $s['role'] === 'admin' ? 'bg-primary' : 'bg-light text-dark' ?>"><?= htmlspecialchars($s['role']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ====================================================
         ADMIN PANEL 2: ROOM ALLOCATION (Applications + actions)
         ==================================================== -->
    <div class="panel" id="panel-admin-applications">
        <div class="card">
            <div class="card-header"><i class="bi bi-clipboard-check me-1"></i> Room Allocation - Applications</div>
            <div class="card-body">
                <?php if (empty($pendingApps)): ?>
                    <p class="text-muted text-center my-4">No applications submitted.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Application No</th>
                                    <th>Student</th>
                                    <th>Hostel</th>
                                    <th>Year</th>
                                    <th>Purpose</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingApps as $a): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($a['application_no']) ?></code></td>
                                        <td><?= htmlspecialchars($a['first_name'] . ' (' . $a['sic_number'] . ')') ?></td>
                                        <td class="small"><?= htmlspecialchars($a['hostel_name']) ?></td>
                                        <td class="small"><?= htmlspecialchars($a['academic_year']) ?></td>
                                        <td class="small"><?= htmlspecialchars($a['purpose']) ?></td>
                                        <td class="small"><?= date('d M Y', strtotime($a['submitted_at'])) ?></td>
                                        <td><span class="badge <?= statusBadgeClass($a['status']) ?>"><?= htmlspecialchars($a['status']) ?></span></td>
                                        <td>
                                            <?php if ($a['status'] === 'Pending'): ?>
                                                <form method="POST" action="dashboard.php" class="d-inline">
                                                    <input type="hidden" name="form_action" value="approve_application">
                                                    <input type="hidden" name="application_id" value="<?= (int)$a['id'] ?>">
                                                    <input type="hidden" name="student_id" value="<?= (int)$a['student_id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve and auto-allocate a room?')">
                                                        <i class="bi bi-check-lg"></i> Approve
                                                    </button>
                                                </form>
                                                <form method="POST" action="dashboard.php" class="d-inline">
                                                    <input type="hidden" name="form_action" value="reject_application">
                                                    <input type="hidden" name="application_id" value="<?= (int)$a['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Reject this application?')">
                                                        <i class="bi bi-x-lg"></i> Reject
                                                    </button>
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

    <!-- ====================================================
         ADMIN PANEL 3: VACANCY MANAGEMENT (Vacating + actions)
         ==================================================== -->
    <div class="panel" id="panel-admin-vacating">
        <div class="card">
            <div class="card-header"><i class="bi bi-house-exit me-1"></i> Vacancy Management - Vacating Requests</div>
            <div class="card-body">
                <?php if (empty($pendingVacating)): ?>
                    <p class="text-muted text-center my-4">No vacating requests submitted.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Room</th>
                                    <th>Vacate Date</th>
                                    <th>Reason</th>
                                    <th>Submitted</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingVacating as $v): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($v['first_name'] . ' (' . $v['sic_number'] . ')') ?></td>
                                        <td><?= htmlspecialchars($v['room_number'] ?? '—') ?></td>
                                        <td class="small"><?= date('d M Y', strtotime($v['vacate_date'])) ?></td>
                                        <td class="small"><?= htmlspecialchars($v['reason']) ?></td>
                                        <td class="small"><?= date('d M Y', strtotime($v['submitted_at'])) ?></td>
                                        <td><span class="badge <?= statusBadgeClass($v['status']) ?>"><?= htmlspecialchars($v['status']) ?></span></td>
                                        <td>
                                            <?php if ($v['status'] === 'Pending'): ?>
                                                <form method="POST" action="dashboard.php" class="d-inline">
                                                    <input type="hidden" name="form_action" value="approve_vacating">
                                                    <input type="hidden" name="vacating_id" value="<?= (int)$v['id'] ?>">
                                                    <input type="hidden" name="student_id" value="<?= (int)$v['student_id'] ?>">
                                                    <input type="hidden" name="room_id" value="<?= (int)($v['room_id'] ?? 0) ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve vacating and free the bed?')">
                                                        <i class="bi bi-check-lg"></i> Approve
                                                    </button>
                                                </form>
                                                <form method="POST" action="dashboard.php" class="d-inline">
                                                    <input type="hidden" name="form_action" value="reject_vacating">
                                                    <input type="hidden" name="vacating_id" value="<?= (int)$v['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Reject this vacating request?')">
                                                        <i class="bi bi-x-lg"></i> Reject
                                                    </button>
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

    <!-- ====================================================
         ADMIN PANEL 5: VISITORS CONTROL (all active visitors)
         ==================================================== -->
    <div class="panel" id="panel-admin-visitors">
        <div class="card">
            <div class="card-header"><i class="bi bi-people-fill me-1"></i> Visitors Control - All Visitors</div>
            <div class="card-body">
                <?php if (empty($allVisitors)): ?>
                    <p class="text-muted text-center my-4">No visitors recorded.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Visitor</th>
                                    <th>Host Student</th>
                                    <th>Room</th>
                                    <th>Visit Date</th>
                                    <th>In Time</th>
                                    <th>Out Time</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allVisitors as $v): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($v['visitor_name']) ?></td>
                                        <td class="small"><?= htmlspecialchars($v['first_name'] . ' (' . $v['sic_number'] . ')') ?></td>
                                        <td class="small"><?= htmlspecialchars($v['room_number'] ?? '—') ?></td>
                                        <td class="small"><?= date('d M Y', strtotime($v['visit_date'])) ?></td>
                                        <td class="small"><?= date('d M Y, h:i A', strtotime($v['in_time'])) ?></td>
                                        <td class="small">
                                            <?php if ($v['out_time']): ?>
                                                <span class="badge bg-secondary"><?= date('d M Y, h:i A', strtotime($v['out_time'])) ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Inside</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$v['out_time']): ?>
                                                <form method="POST" action="dashboard.php" class="d-inline">
                                                    <input type="hidden" name="form_action" value="mark_visitor_out">
                                                    <input type="hidden" name="visitor_id" value="<?= (int)$v['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-box-arrow-right"></i> Mark Out
                                                    </button>
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

    <!-- ====================================================
         ADMIN PANEL 4: FURNITURE INVENTORY
         ==================================================== -->
    <div class="panel" id="panel-admin-furniture">
        <div class="card">
            <div class="card-header"><i class="bi bi-lamp me-1"></i> Furniture Inventory</div>
            <div class="card-body">
                <?php if (empty($furnitureList)): ?>
                    <p class="text-muted text-center my-4">No furniture records found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Room</th>
                                    <th>Hostel</th>
                                    <th>Type</th>
                                    <th>Quantity</th>
                                    <th>Condition</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($furnitureList as $f): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($f['room_number']) ?></td>
                                        <td class="small"><?= htmlspecialchars($f['hostel_name'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($f['furniture_type']) ?></td>
                                        <td><?= (int)$f['quantity'] ?></td>
                                        <td class="small"><?= htmlspecialchars($f['condition_note']) ?></td>
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

</div><!-- /.main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
