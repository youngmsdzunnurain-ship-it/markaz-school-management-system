# MARKAZ Daily Attendance

Daily attendance is separate from the termly `behavioural_reports.attendance` score shown on student result statements.

## Features

- Database-driven conventional and Arabic class selection.
- Daily register for active students, filtered by date and class.
- Present, Absent, Late and Excused statuses, with an optional note per student.
- Admin and Exam Officer access; teachers see classes assigned to their account.
- Server-side roster and status validation, prepared SQL statements, atomic save transactions, same-origin request header protection, and per-request authorization.
- Attendance summary, progress indicator, search, print layout and CSV export.
- Responsive layout with visible labels and keyboard focus styles.

## Install on an existing MARKAZ database

Back up the database first. From the repository root, run:

```sh
mariadb -u root markaz_result_system < database/migrations/20261028_daily_attendance.sql
```

If your MariaDB root account uses a password, add `-p` and enter it when prompted. The migration creates only `attendance_records`; it does not modify student results or termly behavioural reports. It is intended to be applied once.

## Test

Run the focused validation tests:

```sh
php tests/attendance-validation.test.php
```

Check PHP syntax for the new endpoints:

```sh
php -l api/attendance/_common.php
php -l api/attendance/classes.php
php -l api/attendance/register.php
php -l tests/attendance-validation.test.php
```

Then sign in as an Admin/Exam Officer and a teacher with class assignments. Verify class lists, both class categories, save/reload, incomplete status rejection, roster mismatch rejection, and that an unassigned teacher receives HTTP 403.
