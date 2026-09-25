<?php
/**
 * Shared logic for manager and admin dashboards.
 * Handles all POST actions and fetches data needed by both views.
 * Must be included AFTER $student and $conn are available.
 */

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/helpers.php';

$actionMsg = '';
$actionErr = '';

/* =========================================================
   POST HANDLERS
   ========================================================= */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['form_action'] ?? '';

    /* ---- Add room ---- */
    if ($action === 'add_room') {
        $roomNumber = trim($_POST['room_number'] ?? '');
        $hostelId = (int) ($_POST['hostel_id'] ?? 0);
        $roomType = trim($_POST['type'] ?? 'Non-AC');
        $sharingStyle = trim($_POST['sharing_style'] ?? 'Shared');
        $totalCapacity = (int) ($_POST['total_capacity'] ?? 1);
        
        if ($roomNumber !== '' && $hostelId > 0 && $totalCapacity > 0) {
            try {
                $stmt = $conn->prepare(
                    'INSERT INTO rooms (room_number, hostel_id, type, sharing_style, total_capacity, occupied)
                     VALUES (?, ?, ?, ?, ?, 0)'
                );
                $stmt->bind_param('sissi', $roomNumber, $hostelId, $roomType, $sharingStyle, $totalCapacity);
                $stmt->execute();
                $actionMsg = 'Room added successfully.';
            } catch (Exception $ex) {
                $actionErr = $ex->getCode() === 1062 ? 'Room number already exists.' : 'Failed to add room.';
            }
        } else {
            $actionErr = 'Room number, hostel, and capacity are required.';
        }
    }
    
    /* ---- Update room ---- */
    if ($action === 'update_room') {
        $roomId = (int) ($_POST['room_id'] ?? 0);
        $roomType = trim($_POST['type'] ?? 'Non-AC');
        $sharingStyle = trim($_POST['sharing_style'] ?? 'Shared');
        $totalCapacity = (int) ($_POST['total_capacity'] ?? 1);
        
        if ($roomId > 0 && $totalCapacity > 0) {
            $stmt = $conn->prepare(
                'UPDATE rooms SET type = ?, sharing_style = ?, total_capacity = ? WHERE id = ?'
            );
            $stmt->bind_param('ssii', $roomType, $sharingStyle, $totalCapacity, $roomId);
            $stmt->execute();
            $actionMsg = 'Room updated successfully.';
        } else {
            $actionErr = 'Invalid room data.';
        }
    }
    
    /* ---- Delete room ---- */
    if ($action === 'delete_room') {
        $roomId = (int) ($_POST['room_id'] ?? 0);
        if ($roomId > 0) {
            $conn->begin_transaction();
            try {
                // Get current occupancy
                $occStmt = $conn->prepare('SELECT occupied FROM rooms WHERE id = ?');
                $occStmt->bind_param('i', $roomId);
                $occStmt->execute();
                $occResult = $occStmt->get_result();
                $roomData = $occResult->fetch_assoc();
                
                if ($roomData && $roomData['occupied'] > 0) {
                    $actionErr = 'Cannot delete room with occupants. Reallocate students first.';
                    $conn->rollback();
                } else {
                    // Remove room reference from students
                    $updStu = $conn->prepare('UPDATE students SET room_id = NULL WHERE room_id = ?');
                    $updStu->bind_param('i', $roomId);
                    $updStu->execute();
                    
                    // Delete room
                    $delRoom = $conn->prepare('DELETE FROM rooms WHERE id = ?');
                    $delRoom->bind_param('i', $roomId);
                    $delRoom->execute();
                    
                    $conn->commit();
                    $actionMsg = 'Room deleted successfully.';
                }
            } catch (Exception $ex) {
                $conn->rollback();
                $actionErr = 'Failed to delete room.';
            }
        } else {
            $actionErr = 'Invalid room ID.';
        }
    }

    /* ---- Add student profile ---- */
    if ($action === 'add_student') {
        $sic        = trim($_POST['sic_number'] ?? '');
        $firstName  = trim($_POST['first_name'] ?? '');
        $lastName   = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $password   = $_POST['password'] ?? '';
        $department = trim($_POST['department'] ?? '');
        $mobile     = trim($_POST['mobile_number'] ?? '');
        $year       = (int) ($_POST['year_of_study'] ?? 1);

        if ($sic === '' || $firstName === '' || $password === '') {
            $actionErr = 'SIC, first name and password are required.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $ins = $conn->prepare(
                    'INSERT INTO students
                        (sic_number, first_name, last_name, email, password_hash,
                         mobile_number, department, year_of_study, role)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, "student")'
                );
                $ins->bind_param('sssssssi', $sic, $firstName, $lastName, $email, $hash, $mobile, $department, $year);
                $ins->execute();
                $actionMsg = "Student {$sic} added successfully.";
            } catch (Exception $ex) {
                $actionErr = $ex->getCode() === 1062
                    ? 'A student with that SIC number already exists.'
                    : 'Failed to add student.';
            }
        }
    }

    /* ---- Update student profile ---- */
    if ($action === 'update_student') {
        $sid        = (int) ($_POST['student_id'] ?? 0);
        $firstName  = trim($_POST['first_name'] ?? '');
        $lastName   = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $mobile     = trim($_POST['mobile_number'] ?? '');
        $year       = (int) ($_POST['year_of_study'] ?? 1);

        if ($sid > 0 && $firstName !== '') {
            $upd = $conn->prepare(
                'UPDATE students
                    SET first_name = ?, last_name = ?, email = ?,
                        department = ?, mobile_number = ?, year_of_study = ?
                 WHERE id = ?'
            );
            $upd->bind_param('sssssi', $firstName, $lastName, $email, $department, $mobile, $year, $sid);
            $upd->execute();
            $actionMsg = 'Student record updated.';
        }
    }

    /* ---- Delete student (decrement room occupancy if allocated) ---- */
    if ($action === 'delete_student') {
        $sid = (int) ($_POST['student_id'] ?? 0);
        if ($sid > 0) {
            $roomStmt = $conn->prepare('SELECT room_id FROM students WHERE id = ?');
            $roomStmt->bind_param('i', $sid);
            $roomStmt->execute();
            $result = $roomStmt->get_result();
            $roomId = $result->fetch_assoc()['room_id'] ?? null;

            $conn->begin_transaction();
            try {
                if ($roomId) {
                    $updRoom = $conn->prepare('UPDATE rooms SET occupied = GREATEST(occupied - 1, 0) WHERE id = ?');
                    $updRoom->bind_param('i', $roomId);
                    $updRoom->execute();
                }
                $delStu = $conn->prepare('DELETE FROM students WHERE id = ?');
                $delStu->bind_param('i', $sid);
                $delStu->execute();
                $conn->commit();
                $actionMsg = 'Student record deleted.';
            } catch (Exception $ex) {
                $conn->rollback();
                $actionErr = 'Failed to delete student.';
            }
        }
    }

    /* ---- Manually allocate a room to an application ---- */
    if ($action === 'allocate_room') {
        $appId  = (int) ($_POST['application_id'] ?? 0);
        $stuId  = (int) ($_POST['student_id'] ?? 0);
        $roomId = (int) ($_POST['room_id'] ?? 0);

        if ($appId > 0 && $stuId > 0 && $roomId > 0) {
            $cap = $conn->prepare('SELECT total_capacity, occupied FROM rooms WHERE id = ?');
            $cap->bind_param('i', $roomId);
            $cap->execute();
            $result = $cap->get_result();
            $roomData = $result->fetch_assoc();

            if ($roomData && $roomData['occupied'] < $roomData['total_capacity']) {
                $conn->begin_transaction();
                try {
                    $updApp = $conn->prepare('UPDATE applications SET status = "Approved", reviewed_at = NOW() WHERE id = ?');
                    $updApp->bind_param('i', $appId);
                    $updApp->execute();
                    
                    $updStu = $conn->prepare('UPDATE students SET room_id = ? WHERE id = ?');
                    $updStu->bind_param('ii', $roomId, $stuId);
                    $updStu->execute();
                    
                    $updRoom = $conn->prepare('UPDATE rooms SET occupied = occupied + 1 WHERE id = ?');
                    $updRoom->bind_param('i', $roomId);
                    $updRoom->execute();
                    
                    $conn->commit();
                    $actionMsg = 'Room allocated successfully.';
                } catch (Exception $ex) {
                    $conn->rollback();
                    $actionErr = 'Failed to allocate room.';
                }
            } else {
                $actionErr = 'Selected room is full.';
            }
        }
    }

    /* ---- Reject application ---- */
    if ($action === 'reject_application') {
        $appId = (int) ($_POST['application_id'] ?? 0);
        if ($appId > 0) {
            $stmt = $conn->prepare('UPDATE applications SET status = "Rejected", reviewed_at = NOW() WHERE id = ?');
            $stmt->bind_param('i', $appId);
            $stmt->execute();
            $actionMsg = 'Application rejected.';
        }
    }

    /* ---- Resolve / update complaint status + remarks ---- */
    if ($action === 'update_complaint') {
        $compId  = (int) ($_POST['complaint_id'] ?? 0);
        $status  = trim($_POST['status'] ?? '');
        $remarks = trim($_POST['admin_remarks'] ?? '');

        $allowed = ['Pending', 'In-Progress', 'Resolved'];
        if ($compId > 0 && in_array($status, $allowed, true)) {
            $upd = $conn->prepare(
                'UPDATE complaints SET status = ?, admin_remarks = ? WHERE id = ?'
            );
            $upd->bind_param('ssi', $status, $remarks, $compId);
            $upd->execute();
            $actionMsg = 'Complaint updated.';
        }
    }

    /* ---- Publish notice ---- */
    if ($action === 'publish_notice') {
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if ($title !== '' && $content !== '') {
            $stmt = $conn->prepare('INSERT INTO notices (title, content) VALUES (?, ?)');
            $stmt->bind_param('ss', $title, $content);
            $stmt->execute();
            $actionMsg = 'Notice published.';
        } else {
            $actionErr = 'Title and content are required.';
        }
    }

    /* ---- Mark visitor out ---- */
    if ($action === 'mark_visitor_out') {
        $vid = (int) ($_POST['visitor_id'] ?? 0);
        if ($vid > 0) {
            $stmt = $conn->prepare('UPDATE visitors SET out_time = NOW() WHERE id = ? AND out_time IS NULL');
            $stmt->bind_param('i', $vid);
            $stmt->execute();
            $actionMsg = 'Visitor marked out.';
        }
    }

    /* ---- Admin-only: add manager ---- */
    if ($action === 'add_manager') {
        $sic        = trim($_POST['sic_number'] ?? '');
        $firstName  = trim($_POST['first_name'] ?? '');
        $lastName   = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $password   = $_POST['password'] ?? '';
        $mobile     = trim($_POST['mobile_number'] ?? '');
        $hostelId   = (int) ($_POST['hostel_id'] ?? 0);

        if ($sic === '' || $firstName === '' || $password === '') {
            $actionErr = 'SIC, first name and password are required.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $conn->begin_transaction();
                $ins = $conn->prepare(
                    'INSERT INTO students
                        (sic_number, first_name, last_name, email, password_hash,
                         mobile_number, department, year_of_study, role)
                     VALUES (?, ?, ?, ?, ?, ?, "", 0, "manager")'
                );
                $ins->bind_param('ssssss', $sic, $firstName, $lastName, $email, $hash, $mobile);
                $ins->execute();
                $newStuId = $conn->insert_id;
                
                $hostelIdValue = $hostelId > 0 ? $hostelId : null;
                $insAdmin = $conn->prepare(
                    'INSERT INTO administrators (student_id, mobile_number, hostel_id)
                     VALUES (?, ?, ?)'
                );
                $insAdmin->bind_param('isi', $newStuId, $mobile, $hostelIdValue);
                $insAdmin->execute();
                
                $conn->commit();
                $actionMsg = "Manager {$sic} added successfully.";
            } catch (Exception $ex) {
                $conn->rollback();
                $actionErr = $ex->getCode() === 1062
                    ? 'A user with that SIC number already exists.'
                    : 'Failed to add manager.';
            }
        }
    }

    /* ---- Admin-only: delete manager ---- */
    if ($action === 'delete_manager') {
        $sid = (int) ($_POST['student_id'] ?? 0);
        if ($sid > 0) {
            $stmt = $conn->prepare('DELETE FROM students WHERE id = ? AND role = "manager"');
            $stmt->bind_param('i', $sid);
            $stmt->execute();
            $actionMsg = 'Manager account deleted.';
        }
    }

    /* ---- Admin-only: add hostel ---- */
    if ($action === 'add_hostel') {
        $name  = trim($_POST['hostel_name'] ?? '');
        $rooms = (int) ($_POST['no_of_rooms'] ?? 0);
        if ($name !== '') {
            $stmt = $conn->prepare('INSERT INTO hostels (hostel_name, no_of_rooms, no_of_students) VALUES (?, ?, 0)');
            $stmt->bind_param('si', $name, $rooms);
            $stmt->execute();
            $actionMsg = 'Hostel added.';
        }
    }

    /* ---- Admin-only: delete hostel ---- */
    if ($action === 'delete_hostel') {
        $hid = (int) ($_POST['hostel_id'] ?? 0);
        if ($hid > 0) {
            $stmt = $conn->prepare('DELETE FROM hostels WHERE id = ?');
            $stmt->bind_param('i', $hid);
            $stmt->execute();
            $actionMsg = 'Hostel deleted.';
        }
    }

    /* ---- Approve leave application ---- */
    if ($action === 'approve_leave') {
        $leaveId = (int) ($_POST['leave_id'] ?? 0);
        if ($leaveId > 0) {
            $stmt = $conn->prepare('UPDATE leave_applications SET status = "Approved" WHERE id = ? AND status = "Pending"');
            $stmt->bind_param('i', $leaveId);
            $stmt->execute();
            $actionMsg = 'Leave application approved.';
        }
    }

    /* ---- Reject leave application ---- */
    if ($action === 'reject_leave') {
        $leaveId = (int) ($_POST['leave_id'] ?? 0);
        if ($leaveId > 0) {
            $stmt = $conn->prepare('UPDATE leave_applications SET status = "Rejected" WHERE id = ? AND status = "Pending"');
            $stmt->bind_param('i', $leaveId);
            $stmt->execute();
            $actionMsg = 'Leave application rejected.';
        }
    }

    /* ---- Night scan: mark a student present ---- */
    if ($action === 'night_scan') {
        $sic = trim($_POST['sic_number'] ?? '');
        if ($sic === '') {
            $actionErr = 'Please enter a SIC number.';
        } else {
            $stu = $conn->prepare('SELECT id FROM students WHERE sic_number = ? AND room_id IS NOT NULL');
            $stu->bind_param('s', $sic);
            $stu->execute();
            $result = $stu->get_result();
            $studentRow = $result->fetch_assoc();

            if (!$studentRow) {
                $actionErr = 'SIC not found or student has no room allocated.';
            } else {
                $check = $conn->prepare(
                    'SELECT id FROM gate_logs
                     WHERE student_id = ? AND log_type = "Night Scan"
                       AND DATE(log_time) = CURDATE()'
                );
                $check->bind_param('i', $studentRow['id']);
                $check->execute();
                $checkResult = $check->get_result();

                if ($checkResult->fetch_assoc()) {
                    $actionErr = 'Student already scanned tonight.';
                } else {
                    $insert = $conn->prepare(
                        'INSERT INTO gate_logs (student_id, log_type, status_note, fine_amount, fine_status)
                         VALUES (?, "Night Scan", "Present via Scanner", 0.00, "N/A")'
                    );
                    $insert->bind_param('i', $studentRow['id']);
                    $insert->execute();
                    $actionMsg = 'Student ' . e($sic) . ' marked present.';
                }
            }
        }
    }

    /* ---- Close night scan & process absentees ---- */
    if ($action === 'process_absentees') {
        $hostellites = $conn->query(
            'SELECT id FROM students WHERE room_id IS NOT NULL AND role = "student"'
        );

        $count = 0;
        while ($row = $hostellites->fetch_assoc()) {
            $hid = $row['id'];
            $check = $conn->prepare(
                'SELECT id FROM gate_logs
                 WHERE student_id = ? AND log_type = "Night Scan"
                   AND DATE(log_time) = CURDATE()'
            );
            $check->bind_param('i', $hid);
            $check->execute();
            $checkResult = $check->get_result();
            
            if (!$checkResult->fetch_assoc()) {
                $insert = $conn->prepare(
                    'INSERT INTO gate_logs (student_id, log_type, status_note, fine_amount, fine_status)
                     VALUES (?, "Night Scan", "Absent from Night Check", 50.00, "Unpaid")'
                );
                $insert->bind_param('i', $hid);
                $insert->execute();
                $count++;
            }
        }
        $actionMsg = "Night scan closed. {$count} absent student(s) fined.";
    }

    /* ---- Mark fine as paid ---- */
    if ($action === 'mark_fine_paid') {
        $logId = (int) ($_POST['log_id'] ?? 0);
        if ($logId > 0) {
            $stmt = $conn->prepare('UPDATE gate_logs SET fine_status = "Paid" WHERE id = ? AND fine_status = "Unpaid"');
            $stmt->bind_param('i', $logId);
            $stmt->execute();
            $actionMsg = 'Fine marked as paid.';
        }
    }

    /* ---- Update hostel Wi-Fi credentials ---- */
    if ($action === 'update_wifi') {
        $hostelId = (int) ($_POST['hostel_id'] ?? 0);
        $wifiName = trim($_POST['wifi_name'] ?? '');
        $wifiPass = trim($_POST['wifi_password'] ?? '');

        if ($hostelId > 0 && $wifiName !== '' && $wifiPass !== '') {
            $stmt = $conn->prepare('UPDATE hostels SET wifi_name = ?, wifi_password = ? WHERE id = ?');
            $stmt->bind_param('ssi', $wifiName, $wifiPass, $hostelId);
            $stmt->execute();
            $actionMsg = 'Wi-Fi credentials updated.';
        } else {
            $actionErr = 'All Wi-Fi fields are required.';
        }
    }
}

/* =========================================================
   DATA FETCHERS
   ========================================================= */

/* ---- All students with filter ---- */
$filterDept = trim($_GET['filter_department'] ?? '');
$filterYear = trim($_GET['filter_year'] ?? '');

$studentQuery = 'SELECT s.id, s.sic_number, s.first_name, s.last_name, s.email,
            s.mobile_number, s.department, s.year_of_study, s.role,
            r.room_number
     FROM students s
     LEFT JOIN rooms r ON r.id = s.room_id';
$where  = [];
$params = [];
$types = '';
if ($filterDept !== '') {
    $where[] = 's.department = ?';
    $params[] = $filterDept;
    $types .= 's';
}
if ($filterYear !== '') {
    $where[] = 's.year_of_study = ?';
    $params[] = (int) $filterYear;
    $types .= 'i';
}
if ($where) {
    $studentQuery .= ' WHERE ' . implode(' AND ', $where) . ' AND s.role = "student"';
} else {
    $studentQuery .= ' WHERE s.role = "student"';
}
$studentQuery .= ' ORDER BY s.first_name';

if ($params) {
    $allStudentsStmt = $conn->prepare($studentQuery);
    $allStudentsStmt->bind_param($types, ...$params);
    $allStudentsStmt->execute();
    $result = $allStudentsStmt->get_result();
    $allStudents = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query($studentQuery);
    $allStudents = $result->fetch_all(MYSQLI_ASSOC);
}

$deptResult = $conn->query('SELECT DISTINCT department FROM students WHERE department != "" ORDER BY department');
$departments = [];
while ($row = $deptResult->fetch_assoc()) {
    $departments[] = $row['department'];
}

$yearResult = $conn->query('SELECT DISTINCT year_of_study FROM students ORDER BY year_of_study');
$years = [];
while ($row = $yearResult->fetch_assoc()) {
    $years[] = $row['year_of_study'];
}

/* ---- All applications ---- */
$pendingAppsResult = $conn->query(
    'SELECT a.id, a.application_no, s.id AS student_id, s.sic_number, s.first_name,
            h.hostel_name, a.academic_year, a.purpose, a.status, a.submitted_at
     FROM applications a
     INNER JOIN students s ON s.id = a.student_id
     INNER JOIN hostels h ON h.id = a.hostel_id
     ORDER BY a.submitted_at DESC'
);
$pendingApps = $pendingAppsResult->fetch_all(MYSQLI_ASSOC);

/* ---- Available rooms for allocation dropdown ---- */
$availableRoomsResult = $conn->query(
    'SELECT id, room_number, type, sharing_style, total_capacity, occupied
     FROM rooms
     WHERE occupied < total_capacity
     ORDER BY room_number'
);
$availableRooms = $availableRoomsResult->fetch_all(MYSQLI_ASSOC);

/* ---- All complaints ---- */
$allComplaintsResult = $conn->query(
    'SELECT c.id, c.ticket_id, c.category, c.subject, c.description, c.status,
            c.admin_remarks, c.created_at, s.sic_number, s.first_name, r.room_number
     FROM complaints c
     INNER JOIN students s ON s.id = c.student_id
     LEFT JOIN rooms r ON r.id = s.room_id
     ORDER BY c.created_at DESC'
);
$allComplaints = $allComplaintsResult->fetch_all(MYSQLI_ASSOC);

/* ---- All visitors ---- */
$allVisitorsResult = $conn->query(
    'SELECT v.id, v.visitor_name, v.visit_date, v.in_time, v.out_time,
            v.relationship_to_student, s.first_name, s.sic_number, r.room_number
     FROM visitors v
     INNER JOIN students s ON s.id = v.student_id
     LEFT JOIN rooms r ON r.id = s.room_id
     ORDER BY v.in_time DESC'
);
$allVisitors = $allVisitorsResult->fetch_all(MYSQLI_ASSOC);

/* ---- Hostels list ---- */
$hostelsResult = $conn->query('SELECT id, hostel_name, no_of_rooms, no_of_students, wifi_name, wifi_password FROM hostels ORDER BY hostel_name');
$hostels = $hostelsResult->fetch_all(MYSQLI_ASSOC);

/* ---- Notices ---- */
$noticesResult = $conn->query('SELECT id, title, content, created_at FROM notices ORDER BY created_at DESC LIMIT 20');
$notices = $noticesResult->fetch_all(MYSQLI_ASSOC);

/* ---- Metrics ---- */
$metrics = [
    'total_students'         => (int) $conn->query('SELECT COUNT(*) FROM students WHERE role = "student"')->fetch_row()[0],
    'total_capacity'         => (int) $conn->query('SELECT COALESCE(SUM(total_capacity),0) FROM rooms')->fetch_row()[0],
    'available_beds'         => (int) $conn->query('SELECT COALESCE(SUM(total_capacity - occupied),0) FROM rooms')->fetch_row()[0],
    'unresolved_complaints'   => (int) $conn->query('SELECT COUNT(*) FROM complaints WHERE status IN ("Pending","In-Progress")')->fetch_row()[0],
];

/* ---- All leave applications ---- */
$allLeavesResult = $conn->query(
    'SELECT la.id, s.sic_number, s.first_name, la.start_date, la.end_date,
            la.reason, la.status, la.submitted_at
     FROM leave_applications la
     INNER JOIN students s ON s.id = la.student_id
     ORDER BY la.submitted_at DESC'
);
$allLeaves = $allLeavesResult->fetch_all(MYSQLI_ASSOC);

/* ---- All unpaid fines (Night Scan with fine_amount > 0 and Unpaid) ---- */
$unpaidFinesResult = $conn->query(
    'SELECT gl.id, gl.student_id, s.sic_number, s.first_name, r.room_number,
            gl.log_time, gl.fine_amount, gl.fine_status
     FROM gate_logs gl
     INNER JOIN students s ON s.id = gl.student_id
     LEFT JOIN rooms r ON r.id = s.room_id
     WHERE gl.log_type = "Night Scan" AND gl.fine_amount > 0 AND gl.fine_status = "Unpaid"
     ORDER BY gl.log_time DESC'
);
$unpaidFines = $unpaidFinesResult->fetch_all(MYSQLI_ASSOC);

/* ---- All night scan records (recent) ---- */
$nightScansResult = $conn->query(
    'SELECT gl.id, s.sic_number, s.first_name, r.room_number,
            gl.log_time, gl.status_note, gl.fine_amount, gl.fine_status
     FROM gate_logs gl
     INNER JOIN students s ON s.id = gl.student_id
     LEFT JOIN rooms r ON r.id = s.room_id
     WHERE gl.log_type = "Night Scan"
     ORDER BY gl.log_time DESC
     LIMIT 50'
);
$nightScans = $nightScansResult->fetch_all(MYSQLI_ASSOC);

/* ---- All rooms with Wi-Fi info ---- */
$allRoomsWifiResult = $conn->query(
    'SELECT id, room_number, type, sharing_style, total_capacity, occupied
     FROM rooms
     ORDER BY room_number'
);
$allRoomsWifi = $allRoomsWifiResult->fetch_all(MYSQLI_ASSOC);

/* ---- All rooms with hostel info for infrastructure control ---- */
$allRoomsInfraResult = $conn->query(
    'SELECT r.id, r.room_number, r.type, r.sharing_style, r.total_capacity, r.occupied,
            h.hostel_name, h.id AS hostel_id
     FROM rooms r
     LEFT JOIN hostels h ON h.id = r.hostel_id
     ORDER BY h.hostel_name, r.room_number'
);
$allRoomsInfra = $allRoomsInfraResult->fetch_all(MYSQLI_ASSOC);
