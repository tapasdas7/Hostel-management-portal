# Hostel Management Portal

A clean, minimal Hostel Management System built with **HTML, CSS, Bootstrap 5, PHP, JS and MySQL**, aligned to the project SRS and SDD.

## Features

### Authentication
- **Login Landing Page** (`index.php`) - SIC Number + Password login with Bootstrap validation and password hashing.
- **Role-aware sessions** - Students and administrators share one login. The session stores `student_id`, `sic_number`, `first_name`, and `role`.
- **Logout** (`logout.php`) - Destroys the session.

### Student Modules (dashboard.php)
1. **Room Tracking** - Grid of room cards (color-coded by availability) + the student's allocated room.
2. **Complaints Desk** - Submit a new complaint + view personal complaint history table.
3. **Notice Board** - Timeline feed of admin notices sorted newest-first.
4. **Digital Gate Log** - Table of check-in / check-out / late-night leave records.
5. **Room Applications** - Apply for a room in a hostel with academic year and purpose; view application status history.
6. **Vacate Room** - Submit a vacating request with date and reason; view request history.
7. **Visitors Log** - View visitor check-in / check-out history for the student.

### Administrator Modules (visible only when `role = admin`)
8. **Student Records** - Table of all students with SIC, name, department, year, mobile, room, and role.
9. **Room Allocation** - Review all room applications submitted by students with status badges.
10. **Vacancy Management** - Review all vacating requests submitted by students with status badges.
11. **Furniture Inventory** - Per-room furniture listing with type, quantity, and condition.

## Project Structure

```
.
├── assets/
│   ├── css/style.css        # Minimal responsive theme
│   └── js/dashboard.js      # Tab switching + form validation
├── config/
│   └── db.php               # PDO MySQL connection
├── includes/
│   └── auth.php             # Session start + auth guard + role helpers
├── database/
│   └── schema.sql           # Database + tables + seed data
├── index.php                # Login page
├── login_process.php        # Login backend (PDO + password_verify + role)
├── logout.php               # Logout
├── dashboard.php            # Protected dashboard (student + admin modules)
└── README.md
```

## Database Schema (SRS/SDD entities)

| Table               | Purpose                                                    |
|---------------------|------------------------------------------------------------|
| `students`          | Login accounts, profile, role (student/admin), room link   |
| `hostels`           | Hostel names, room counts, student counts                  |
| `rooms`             | Room details, capacity, occupancy, hostel link             |
| `complaints`        | Student complaints with ticket IDs and status              |
| `notices`           | Admin-published notices                                    |
| `gate_logs`         | Check-in / check-out / late-night movement logs            |
| `applications`      | Student room applications with approval status             |
| `vacating_requests` | Student room vacating requests with approval status        |
| `visitors`          | Visitor check-in / check-out records per student           |
| `furniture`         | Per-room furniture inventory with condition notes          |
| `administrators`    | Links a student record to admin details and hostel         |

## Setup Instructions

### 1. Create the database

Import `database/schema.sql` into MySQL (via phpMyAdmin or the MySQL CLI):

```bash
mysql -u root -p < database/schema.sql
```

### 2. Configure database credentials

Edit `config/db.php` and set your MySQL host, database name, username, and password.

### 3. Generate hashed passwords

The seed data ships with placeholder password hashes. Generate real ones with PHP:

```bash
php -r "echo password_hash('password123', PASSWORD_DEFAULT);"
```

Replace the `password_hash` values in the `students` table with the generated hash.

### 4. Run with a PHP server

If using PHP's built-in server:

```bash
php -S localhost:8000
```

Then visit `http://localhost:8000` in your browser.

Alternatively, place the project folder in your XAMPP / WAMP / MAMP `htdocs` directory and visit `http://localhost/your-folder-name`.

## Security Notes

- **SQL injection protection**: all queries use PDO prepared statements with bound parameters.
- **Password hashing**: passwords are stored as BCrypt hashes and verified with `password_verify()`.
- **Session security**: `session_regenerate_id(true)` is called on successful login to prevent session fixation.
- **Session guard**: `require_auth()` is called at the top of `dashboard.php` to redirect unauthenticated users.
- **Role guard**: `require_admin()` is available for pages restricted to administrators.
- **XSS protection**: all user-supplied output is escaped with `htmlspecialchars()`.

## Default Login (after seeding)

| SIC Number | Password      | Role    |
|------------|---------------|---------|
| SIC1001    | password123   | student |
| SIC1002    | password123   | student |

> To test the administrator modules, set a student's `role` to `admin` in the `students` table:
> ```sql
> UPDATE students SET role = 'admin' WHERE sic_number = 'SIC1002';
> ```

> Remember to regenerate the password hashes after importing the schema (see step 3).
