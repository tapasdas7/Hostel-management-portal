-- =============================================================
-- Hostel Management System - Database Schema
-- Run this in phpMyAdmin / MySQL CLI to create the database and tables.
-- =============================================================

CREATE DATABASE IF NOT EXISTS hostel_db
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hostel_db;

-- -------------------------------------------------------------
-- Students table (login accounts)
-- Passwords must be hashed with PHP password_hash() (BCrypt).
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS students (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    sic_number    VARCHAR(20)  NOT NULL UNIQUE,
    first_name    VARCHAR(60)  NOT NULL,
    last_name     VARCHAR(60)  NOT NULL DEFAULT '',
    email         VARCHAR(120) NOT NULL DEFAULT '',
    password_hash VARCHAR(255) NOT NULL,
    room_id       INT          DEFAULT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------------------------------------------
-- Rooms table
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rooms (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    room_number     VARCHAR(10)  NOT NULL UNIQUE,
    type            ENUM('AC','Non-AC')      NOT NULL DEFAULT 'Non-AC',
    sharing_style   ENUM('Single','Shared')  NOT NULL DEFAULT 'Shared',
    total_capacity  INT          NOT NULL DEFAULT 2,
    occupied        INT          NOT NULL DEFAULT 0,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------------------------------------------
-- Complaints table (one student -> many complaints)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS complaints (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT          NOT NULL,
    ticket_id   VARCHAR(20)  NOT NULL UNIQUE,
    category    ENUM('Plumbing','Electrical','Housekeeping','Wi-Fi') NOT NULL,
    subject     VARCHAR(150) NOT NULL,
    description TEXT         NOT NULL,
    status      ENUM('Pending','In-Progress','Resolved') NOT NULL DEFAULT 'Pending',
    admin_remarks TEXT NOT NULL DEFAULT '',
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_complaint_student
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- -------------------------------------------------------------
-- Notices table (admin posts, all students read)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notices (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(180) NOT NULL,
    content     TEXT         NOT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------------------------------------------
-- Gate logs table (check-in / check-out movement)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS gate_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT          NOT NULL,
    log_type    ENUM('Check-in','Check-out','Late-night Leave') NOT NULL,
    log_time    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status_note VARCHAR(200) NOT NULL DEFAULT '',
    CONSTRAINT fk_gate_student
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- =============================================================
-- Sample seed data
-- =============================================================
-- NOTE: The password hashes below correspond to the plain text
-- password "password123" generated with password_hash(PASSWORD_BCRYPT).
-- You can regenerate your own with: php -r "echo password_hash('password123', PASSWORD_DEFAULT);"

INSERT INTO students (sic_number, first_name, last_name, email, password_hash, room_id) VALUES
('SIC1001', 'Aarav',  'Sharma', 'aarav@example.com',  '$2y$10$i.x55rx1NkDcQFP4gq1zwuisNdcqwYMkL34xsxqxCMGFwyeFT6nhG', 1),
('SIC1002', 'Diya',   'Patel',  'diya@example.com',   '$2y$10$i.x55rx1NkDcQFP4gq1zwuisNdcqwYMkL34xsxqxCMGFwyeFT6nhG', 2);

INSERT INTO rooms (room_number, type, sharing_style, total_capacity, occupied) VALUES
('A-101', 'AC',      'Single', 1, 1),
('A-102', 'AC',      'Shared', 2, 1),
('B-201', 'Non-AC',  'Shared', 3, 2),
('B-202', 'Non-AC',  'Single', 1, 0),
('C-301', 'AC',      'Shared', 2, 2),
('C-302', 'Non-AC',  'Shared', 4, 1);

INSERT INTO notices (title, content) VALUES
('Hostel Mess Timing Change','The morning breakfast will now be served from 7:30 AM to 9:00 AM starting Monday.'),
('Water Supply Maintenance','Water supply will be unavailable on Sunday from 10 AM to 2 PM due to tank cleaning.'),
('Wi-Fi Network Upgrade','The hostel Wi-Fi will be upgraded this Friday night. Expect brief downtime between 1-3 AM.');

INSERT INTO gate_logs (student_id, log_type, log_time, status_note) VALUES
(1, 'Check-in',          '2026-09-20 09:15:00', 'Approved by warden'),
(1, 'Check-out',         '2026-09-20 06:30:00', 'Routine outing'),
(1, 'Late-night Leave',  '2026-09-21 11:45:00', 'Pending approval'),
(2, 'Check-in',          '2026-09-19 08:00:00', 'Approved by warden');

INSERT INTO complaints (student_id, ticket_id, category, subject, description, status) VALUES
(1, 'TKT-2026-001', 'Plumbing',      'Leaking tap in washroom', 'The washroom tap is continuously leaking since morning.', 'In-Progress'),
(1, 'TKT-2026-002', 'Wi-Fi',         'Slow internet speed',     'Internet is very slow in room A-101 since last two days.',  'Pending');

-- =============================================================
-- SRS / SDD extensions
-- Adds Hostel, Administrator, Application, Vacating, Visitor,
-- and Furniture entities without removing the original portal data.
-- =============================================================

ALTER TABLE students
    ADD COLUMN IF NOT EXISTS mobile_number VARCHAR(20) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS department VARCHAR(100) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS year_of_study INT NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS role ENUM('student','manager','admin') NOT NULL DEFAULT 'student';

UPDATE students SET role = 'admin'   WHERE sic_number = 'SIC1001';
UPDATE students SET role = 'student' WHERE sic_number = 'SIC1002';

-- Seed a manager account (password: password123)
INSERT INTO students (sic_number, first_name, last_name, email, password_hash, mobile_number, department, year_of_study, role)
SELECT 'SIC2001', 'Rohan', 'Verma', 'rohan@example.com',
       '$2y$10$i.x55rx1NkDcQFP4gq1zwuisNdcqwYMkL34xsxqxCMGFwyeFT6nhG',
       '9876543210', 'Warden', 0, 'manager'
WHERE NOT EXISTS (SELECT 1 FROM students WHERE sic_number = 'SIC2001');

CREATE TABLE IF NOT EXISTS hostels (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    hostel_name   VARCHAR(120) NOT NULL,
    no_of_rooms   INT NOT NULL DEFAULT 0,
    no_of_students INT NOT NULL DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS administrators (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    student_id    INT NOT NULL UNIQUE,
    mobile_number VARCHAR(20) NOT NULL DEFAULT '',
    hostel_id     INT DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_admin_hostel FOREIGN KEY (hostel_id) REFERENCES hostels(id) ON DELETE SET NULL
);

ALTER TABLE rooms
    ADD COLUMN IF NOT EXISTS hostel_id INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS room_id_label VARCHAR(30) NOT NULL DEFAULT '';

CREATE TABLE IF NOT EXISTS applications (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    student_id    INT NOT NULL,
    hostel_id     INT NOT NULL,
    application_no VARCHAR(30) NOT NULL UNIQUE,
    academic_year VARCHAR(20) NOT NULL,
    purpose       VARCHAR(255) NOT NULL,
    status        ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    submitted_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at   TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_application_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_application_hostel FOREIGN KEY (hostel_id) REFERENCES hostels(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS vacating_requests (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    student_id   INT NOT NULL,
    room_id      INT DEFAULT NULL,
    reason       VARCHAR(255) NOT NULL,
    vacate_date  DATE NOT NULL,
    status       ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at  TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_vacating_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_vacating_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS visitors (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT NOT NULL,
    visitor_name VARCHAR(120) NOT NULL,
    visit_date  DATE NOT NULL,
    in_time     DATETIME NOT NULL,
    out_time    DATETIME DEFAULT NULL,
    relationship_to_student VARCHAR(60) NOT NULL DEFAULT '',
    CONSTRAINT fk_visitor_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS furniture (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    room_id        INT NOT NULL,
    hostel_id      INT DEFAULT NULL,
    furniture_type VARCHAR(120) NOT NULL,
    quantity       INT NOT NULL DEFAULT 1,
    condition_note VARCHAR(255) NOT NULL DEFAULT '',
    CONSTRAINT fk_furniture_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    CONSTRAINT fk_furniture_hostel FOREIGN KEY (hostel_id) REFERENCES hostels(id) ON DELETE SET NULL
);

INSERT INTO hostels (hostel_name, no_of_rooms, no_of_students)
SELECT 'Main Hostel', 6, 2
WHERE NOT EXISTS (SELECT 1 FROM hostels);

-- =============================================================
-- Role separation migrations (student / manager / admin)
-- =============================================================
ALTER TABLE students
    MODIFY COLUMN role ENUM('student','manager','admin') NOT NULL DEFAULT 'student';

ALTER TABLE visitors
    ADD COLUMN IF NOT EXISTS relationship_to_student VARCHAR(60) NOT NULL DEFAULT '';

ALTER TABLE complaints
    ADD COLUMN IF NOT EXISTS admin_remarks TEXT NOT NULL DEFAULT '';

-- =============================================================
-- Leave applications, Night Scan fines, Wi-Fi tracking
-- =============================================================
CREATE TABLE IF NOT EXISTS leave_applications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT NOT NULL,
    start_date  DATE NOT NULL,
    end_date    DATE NOT NULL,
    reason      TEXT NOT NULL,
    status      ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_leave_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

ALTER TABLE rooms
    ADD COLUMN IF NOT EXISTS wifi_name VARCHAR(50) NOT NULL DEFAULT 'Hostel_HighSpeed_5G',
    ADD COLUMN IF NOT EXISTS wifi_password VARCHAR(50) NOT NULL DEFAULT 'Hostel@2026';

ALTER TABLE gate_logs
    MODIFY COLUMN log_type ENUM('Check-in','Check-out','Late-night Leave','Night Scan') NOT NULL,
    ADD COLUMN IF NOT EXISTS fine_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS fine_status ENUM('N/A','Unpaid','Paid') NOT NULL DEFAULT 'N/A';
