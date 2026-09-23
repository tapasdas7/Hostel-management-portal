<?php
/**
 * Shared logic for manager and admin dashboards.
 * Handles all POST actions and fetches data needed by both views.
 * Must be included AFTER $student and $pdo are available.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$actionMsg = '';
$actionErr = '';

/* =========================================================
   POST HANDLERS
   ========================================================= */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['form_action'] ?? '';

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
                $ins = $pdo->prepare(
                    'INSERT INTO students
                        (sic_number, first_name, last_name, email, password_hash,
                         mobile_number, department, year_of_study, role)
                     VALUES (:sic, :fn, :ln, :em, :ph, :mo, :dep, :yr, "student")'
                );
                $ins->execute([
                    ':sic' => $sic, ':fn' => $firstName, ':ln' => $lastName,
                    ':em'  => $email, ':ph' => $hash, ':mo' => $mobile,
                    ':dep' => $department, ':yr' => $year,
                ]);
                $actionMsg = "Student {$sic} added successfully.";
            } catch (PDOException $ex) {
                $actionErr = $ex->getCode() === '23000'
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
            $upd = $pdo->prepare(
                'UPDATE students
                    SET first_name = :fn, last_name = :ln, email = :em,
                        department = :dep, mobile_number = :mo, year_of_study = :yr
                 WHERE id = :sid'
            );
            $upd->execute([
                ':fn' => $firstName, ':ln' => $lastName, ':em' => $email,
                ':dep' => $department, ':mo' => $mobile, ':yr' => $year,
                ':sid' => $sid,
            ]);
            $actionMsg = 'Student record updated.';
        }
    }

    /* ---- Delete student (decrement room occupancy if allocated) ---- */
    if ($action === 'delete_student') {
        $sid = (int) ($_POST['student_id'] ?? 0);
        if ($sid > 0) {
            $roomStmt = $pdo->prepare('SELECT room_id FROM students WHERE id = :sid');
            $roomStmt->execute([':sid' => $sid]);
            $roomId = $roomStmt->fetchColumn();

            $pdo->beginTransaction();
            try {
                if ($roomId) {
                    $pdo->prepare('UPDATE rooms SET occupied = GREATEST(occupied - 1, 0) WHERE id = :rid')
                        ->execute([':rid' => $roomId]);
                }
                $pdo->prepare('DELETE FROM students WHERE id = :sid')->execute([':sid' => $sid]);
                $pdo->commit();
                $actionMsg = 'Student record deleted.';
            } catch (PDOException $ex) {
                $pdo->rollBack();
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
            $cap = $pdo->prepare('SELECT total_capacity, occupied FROM rooms WHERE id = :rid');
            $cap->execute([':rid' => $roomId]);
            $roomData = $cap->fetch();

            if ($roomData && $roomData['occupied'] < $roomData['total_capacity']) {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('UPDATE applications SET status = "Approved", reviewed_at = NOW() WHERE id = :aid')
                        ->execute([':aid' => $appId]);
                    $pdo->prepare('UPDATE students SET room_id = :rid WHERE id = :sid')
                        ->execute([':rid' => $roomId, ':sid' => $stuId]);
                    $pdo->prepare('UPDATE rooms SET occupied = occupied + 1 WHERE id = :rid')
                        ->execute([':rid' => $roomId]);
                    $pdo->commit();
                    $actionMsg = 'Room allocated successfully.';
                } catch (PDOException $ex) {
                    $pdo->rollBack();
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
            $pdo->prepare('UPDATE applications SET status = "Rejected", reviewed_at = NOW() WHERE id = :aid')
                ->execute([':aid' => $appId]);
            $actionMsg = 'Application rejected.';
        }
    }

    /* ---- Approve vacating request ---- */
    if ($action === 'approve_vacating') {
        $vrId  = (int) ($_POST['vacating_id'] ?? 0);
        $stuId = (int) ($_POST['student_id'] ?? 0);
        $roomId = (int) ($_POST['room_id'] ?? 0);

        if ($vrId > 0 && $stuId > 0) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE vacating_requests SET status = "Approved", reviewed_at = NOW() WHERE id = :vid')
                    ->execute([':vid' => $vrId]);
                $pdo->prepare('UPDATE students SET room_id = NULL WHERE id = :sid')
                    ->execute([':sid' => $stuId]);
                if ($roomId > 0) {
                    $pdo->prepare('UPDATE rooms SET occupied = GREATEST(occupied - 1, 0) WHERE id = :rid')
                        ->execute([':rid' => $roomId]);
                }
                $pdo->commit();
                $actionMsg = 'Vacating request approved. Bed is now vacant.';
            } catch (PDOException $ex) {
                $pdo->rollBack();
                $actionErr = 'Failed to approve vacating request.';
            }
        }
    }

    /* ---- Reject vacating request ---- */
    if ($action === 'reject_vacating') {
        $vrId = (int) ($_POST['vacating_id'] ?? 0);
        if ($vrId > 0) {
            $pdo->prepare('UPDATE vacating_requests SET status = "Rejected", reviewed_at = NOW() WHERE id = :vid')
                ->execute([':vid' => $vrId]);
            $actionMsg = 'Vacating request rejected.';
        }
    }

    /* ---- Resolve / update complaint status + remarks ---- */
    if ($action === 'update_complaint') {
        $compId  = (int) ($_POST['complaint_id'] ?? 0);
        $status  = trim($_POST['status'] ?? '');
        $remarks = trim($_POST['admin_remarks'] ?? '');

        $allowed = ['Pending', 'In-Progress', 'Resolved'];
        if ($compId > 0 && in_array($status, $allowed, true)) {
            $upd = $pdo->prepare(
                'UPDATE complaints SET status = :st, admin_remarks = :rm WHERE id = :cid'
            );
            $upd->execute([':st' => $status, ':rm' => $remarks, ':cid' => $compId]);
            $actionMsg = 'Complaint updated.';
        }
    }

    /* ---- Publish notice ---- */
    if ($action === 'publish_notice') {
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if ($title !== '' && $content !== '') {
            $pdo->prepare('INSERT INTO notices (title, content) VALUES (:t, :c)')
                ->execute([':t' => $title, ':c' => $content]);
            $actionMsg = 'Notice published.';
        } else {
            $actionErr = 'Title and content are required.';
        }
    }

    /* ---- Mark visitor out ---- */
    if ($action === 'mark_visitor_out') {
        $vid = (int) ($_POST['visitor_id'] ?? 0);
        if ($vid > 0) {
            $pdo->prepare('UPDATE visitors SET out_time = NOW() WHERE id = :vid AND out_time IS NULL')
                ->execute([':vid' => $vid]);
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
                $pdo->beginTransaction();
                $ins = $pdo->prepare(
                    'INSERT INTO students
                        (sic_number, first_name, last_name, email, password_hash,
                         mobile_number, department, year_of_study, role)
                     VALUES (:sic, :fn, :ln, :em, :ph, :mo, "", 0, "manager")'
                );
                $ins->execute([
                    ':sic' => $sic, ':fn' => $firstName, ':ln' => $lastName,
                    ':em'  => $email, ':ph' => $hash, ':mo' => $mobile,
                ]);
                $newStuId = (int) $pdo->lastInsertId();
                $pdo->prepare(
                    'INSERT INTO administrators (student_id, mobile_number, hostel_id)
                     VALUES (:sid, :mo, :hid)'
                )->execute([
                    ':sid' => $newStuId, ':mo' => $mobile,
                    ':hid' => $hostelId > 0 ? $hostelId : null,
                ]);
                $pdo->commit();
                $actionMsg = "Manager {$sic} added successfully.";
            } catch (PDOException $ex) {
                $pdo->rollBack();
                $actionErr = $ex->getCode() === '23000'
                    ? 'A user with that SIC number already exists.'
                    : 'Failed to add manager.';
            }
        }
    }

    /* ---- Admin-only: delete manager ---- */
    if ($action === 'delete_manager') {
        $sid = (int) ($_POST['student_id'] ?? 0);
        if ($sid > 0) {
            $pdo->prepare('DELETE FROM students WHERE id = :sid AND role = "manager"')
                ->execute([':sid' => $sid]);
            $actionMsg = 'Manager account deleted.';
        }
    }

    /* ---- Admin-only: add hostel ---- */
    if ($action === 'add_hostel') {
        $name  = trim($_POST['hostel_name'] ?? '');
        $rooms = (int) ($_POST['no_of_rooms'] ?? 0);
        if ($name !== '') {
            $pdo->prepare('INSERT INTO hostels (hostel_name, no_of_rooms, no_of_students) VALUES (:n, :r, 0)')
                ->execute([':n' => $name, ':r' => $rooms]);
            $actionMsg = 'Hostel added.';
        }
    }

    /* ---- Admin-only: delete hostel ---- */
    if ($action === 'delete_hostel') {
        $hid = (int) ($_POST['hostel_id'] ?? 0);
        if ($hid > 0) {
            $pdo->prepare('DELETE FROM hostels WHERE id = :hid')->execute([':hid' => $hid]);
            $actionMsg = 'Hostel deleted.';
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
if ($filterDept !== '') {
    $where[] = 's.department = :dept';
    $params[':dept'] = $filterDept;
}
if ($filterYear !== '') {
    $where[] = 's.year_of_study = :year';
    $params[':year'] = (int) $filterYear;
}
if ($where) {
    $studentQuery .= ' WHERE ' . implode(' AND ', $where) . ' AND s.role = "student"';
} else {
    $studentQuery .= ' WHERE s.role = "student"';
}
$studentQuery .= ' ORDER BY s.first_name';
$allStudentsStmt = $pdo->prepare($studentQuery);
$allStudentsStmt->execute($params);
$allStudents = $allStudentsStmt->fetchAll();

$departments = $pdo->query('SELECT DISTINCT department FROM students WHERE department != "" ORDER BY department')->fetchAll(PDO::FETCH_COLUMN);
$years = $pdo->query('SELECT DISTINCT year_of_study FROM students ORDER BY year_of_study')->fetchAll(PDO::FETCH_COLUMN);

/* ---- All applications ---- */
$pendingApps = $pdo->query(
    'SELECT a.id, a.application_no, s.id AS student_id, s.sic_number, s.first_name,
            h.hostel_name, a.academic_year, a.purpose, a.status, a.submitted_at
     FROM applications a
     INNER JOIN students s ON s.id = a.student_id
     INNER JOIN hostels h ON h.id = a.hostel_id
     ORDER BY a.submitted_at DESC'
)->fetchAll();

/* ---- Available rooms for allocation dropdown ---- */
$availableRooms = $pdo->query(
    'SELECT id, room_number, type, sharing_style, total_capacity, occupied
     FROM rooms
     WHERE occupied < total_capacity
     ORDER BY room_number'
)->fetchAll();

/* ---- All complaints ---- */
$allComplaints = $pdo->query(
    'SELECT c.id, c.ticket_id, c.category, c.subject, c.description, c.status,
            c.admin_remarks, c.created_at, s.sic_number, s.first_name, r.room_number
     FROM complaints c
     INNER JOIN students s ON s.id = c.student_id
     LEFT JOIN rooms r ON r.id = s.room_id
     ORDER BY c.created_at DESC'
)->fetchAll();

/* ---- All vacating requests ---- */
$pendingVacating = $pdo->query(
    'SELECT vr.id, s.id AS student_id, s.sic_number, s.first_name, r.id AS room_id,
            r.room_number, vr.reason, vr.vacate_date, vr.status, vr.submitted_at
     FROM vacating_requests vr
     INNER JOIN students s ON s.id = vr.student_id
     LEFT JOIN rooms r ON r.id = vr.room_id
     ORDER BY vr.submitted_at DESC'
)->fetchAll();

/* ---- All visitors ---- */
$allVisitors = $pdo->query(
    'SELECT v.id, v.visitor_name, v.visit_date, v.in_time, v.out_time,
            v.relationship_to_student, s.first_name, s.sic_number, r.room_number
     FROM visitors v
     INNER JOIN students s ON s.id = v.student_id
     LEFT JOIN rooms r ON r.id = s.room_id
     ORDER BY v.in_time DESC'
)->fetchAll();

/* ---- Furniture inventory ---- */
$furnitureList = $pdo->query(
    'SELECT f.id, f.furniture_type, f.quantity, f.condition_note,
            r.room_number, h.hostel_name
     FROM furniture f
     INNER JOIN rooms r ON r.id = f.room_id
     LEFT JOIN hostels h ON h.id = f.hostel_id
     ORDER BY r.room_number'
)->fetchAll();

/* ---- Hostels list ---- */
$hostels = $pdo->query('SELECT id, hostel_name, no_of_rooms, no_of_students FROM hostels ORDER BY hostel_name')->fetchAll();

/* ---- Notices ---- */
$notices = $pdo->query('SELECT id, title, content, created_at FROM notices ORDER BY created_at DESC LIMIT 20')->fetchAll();

/* ---- Metrics ---- */
$metrics = [
    'total_students'         => (int) $pdo->query('SELECT COUNT(*) FROM students WHERE role = "student"')->fetchColumn(),
    'total_capacity'         => (int) $pdo->query('SELECT COALESCE(SUM(total_capacity),0) FROM rooms')->fetchColumn(),
    'available_beds'         => (int) $pdo->query('SELECT COALESCE(SUM(total_capacity - occupied),0) FROM rooms')->fetchColumn(),
    'unresolved_complaints'   => (int) $pdo->query('SELECT COUNT(*) FROM complaints WHERE status IN ("Pending","In-Progress")')->fetchColumn(),
];
