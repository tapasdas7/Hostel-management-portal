# Hostel Management Portal

A clean, minimal Hostel Management System built with **HTML, CSS, Bootstrap 5, PHP, JS and MySQL**, aligned to the project SRS and SDD.

## Role-Based Architecture

The portal is split into three secure, role-separated dashboards:

| File                   | Role    | Access Guard                    |
|------------------------|---------|---------------------------------|
| `student_dashboard.php`| student | `require_role('student')`      |
| `manager_dashboard.php`| manager | `require_role('manager')`       |
| `admin_dashboard.php`  | admin   | `require_role('admin')`        |

Login (`index.php` + `login_process.php`) authenticates against the `students` table and redirects to the correct dashboard based on the `role` column (`student`, `manager`, or `admin`).

## Student Dashboard

The student dashboard adapts its layout based on whether the student has a room allocated:

### State A - Unallocated (`room_id IS NULL`)
- **Notice Board** - chronological feed of announcements.
- **General Room Availability** - read-only room grid (AC/Non-AC, Single/Shared, vacancy).
- **Room Allocation Status** - application tracker with status badges + an "Apply for Hostel Room" form.
- Complaints, Gate Logs, and Visitors are hidden. A "Portal Services" link shows an Access Denied card.

### State B - Allocated (`room_id IS NOT NULL`)
- **My Room & Roommates** - room configuration + roommates table (excludes self).
- **Notice Board** + **Digital Gate Log**.
- **Complaints Desk** - submit complaints + view personal ticket log (including manager remarks).
- **Visitors Registration** - register guests with a mandatory "Relationship to Student" dropdown.

## Manager Dashboard

- **Add Student Profile** - create student accounts (password hashed with `password_hash()`).
- **Student Master Records (CRUD)** - filter, edit (modal), and delete students. Deleting a student with a room decrements room occupancy.
- **Room Allocation Control** - review all applications; manually allocate a room from available rooms dropdown.
- **Complaints Desk & Resolution** - update complaint status + add manager remarks.
- **Notice Broadcaster** - publish notices that stream to student feeds.
- **Visitors Control** + **Vacancy Management**.

## Admin Dashboard

- **Metrics Dashboard** - four analytic cards (Total Students, Total Capacity, Available Beds, Unresolved Complaints).
- **Add Student Profile** - inherited from manager.
- **Student CRUD** - inherited from manager.
- **Manage Hostels & Managers** - create/delete manager accounts and hostels.
- **Full Operational Inheritance** - all manager panels (allocation, complaints, notices, visitors, vacancy, furniture).

## Project Structure

```
.
├── assets/
│   ├── css/style.css
│   └── js/dashboard.js
├── config/
│   └── db.php
├── includes/
│   ├── auth.php             # Session + role guards + role-based redirect
│   ├── helpers.php          # Shared badge/utility helpers
│   └── admin_shared.php     # Shared POST handlers + data fetchers (manager/admin)
├── database/
│   └── schema.sql
├── index.php                # Login page
├── login_process.php        # Login backend (role-aware routing)
├── logout.php
├── student_dashboard.php
├── manager_dashboard.php
├── admin_dashboard.php
└── README.md
```

## Database Schema

| Table               | Purpose                                                    |
|---------------------|------------------------------------------------------------|
| `students`          | Login accounts, role (student/manager/admin), room link   |
| `hostels`           | Hostel names, room counts, student counts                  |
| `rooms`             | Room details, capacity, occupancy, hostel link             |
| `complaints`        | Student complaints with ticket IDs, status, admin_remarks  |
| `notices`           | Manager/admin-published notices                            |
| `gate_logs`         | Check-in / check-out / late-night movement logs             |
| `applications`      | Student room applications with approval status             |
| `vacating_requests` | Student room vacating requests with approval status        |
| `visitors`          | Visitor records with relationship_to_student              |
| `furniture`         | Per-room furniture inventory with condition notes          |
| `administrators`    | Links manager records to hostel assignments                |

## Setup

### 1. Create the database
Run the single master schema file:
```bash
mysql -u root -p < database/schema.sql
```

> `database/migration.sql` is kept only as a legacy note and is no longer required.

### 2. Configure credentials
Edit `config/db.php` and set your MySQL host, database name, username, and password.

### 3. Generate hashed passwords
```bash
php -r "echo password_hash('password123', PASSWORD_DEFAULT);"
```
Replace the `password_hash` values in the seed data.

### 4. Run with PHP's built-in server
```bash
php -S localhost:8000
```

## Default Logins (after seeding)

| SIC Number | Password      | Role    | Dashboard             |
|------------|---------------|---------|-----------------------|
| SIC1001    | password123   | admin   | admin_dashboard.php   |
| SIC1002    | password123   | student | student_dashboard.php|
| SIC2001    | password123   | manager | manager_dashboard.php|

## Security

- **SQL injection**: all queries use PDO prepared statements with bound parameters.
- **Password hashing**: BCrypt via `password_hash()` / `password_verify()`.
- **Session security**: `session_regenerate_id(true)` on login; httponly + samesite cookies.
- **Role guards**: `require_role()` enforces per-page access control and redirects unauthorized users.
- **XSS**: all output escaped with `htmlspecialchars()`.
