# MARKAZ Result Management System

**A school administration, academic results, attendance and learning-portal application for Markazu Ta’lim Wa Tahfizil Qur’anil Kareem.**

MARKAZ brings the school's conventional and Arabic academic operations into one web application. It supports student and staff administration, class and subject setup, term-based assessment, result entry and publication, daily attendance, account permissions, a public school website, and the separate MARKAZ Online Class learning and reporting area.

> **Project status:** This repository contains the application source, database schema and incremental migrations, browser assets, and PHP regression tests. Features described below are based on the code and documentation in this repository. Production credentials, live student data and server-specific settings are not included.

## Overview

The system is designed around the school's academic periods (academic session + term). Results, memorization records, behavioural assessments and attendance history are organized so that staff work against the appropriate class and academic period.

The application has two related but distinct areas:

1. **Core MARKAZ school management** — conventional and Arabic classes, student records, staff access, result entry and printing, daily attendance, academic settings and the public-facing school website.
2. **MARKAZ Online Class** — a separate learning/assessment portal with its own student access, class sections, results, printable reports, archives and device-activity controls.

The Online Class module is not the same thing as the school's daily attendance register. The daily attendance feature documented below is the conventional/Arabic school attendance module at `public/attendance.html`.

## Features

### School and academic administration

- Manage conventional classes and Arabic classes as separate class categories.
- Maintain student records, admission numbers, names, class assignments and active/inactive status.
- Maintain subjects, subject groups/categories and class-to-subject assignments.
- Configure academic sessions, terms and the current academic period.
- Finalize academic periods to protect completed work and use the controlled academic-entry clearing workflow when a fresh period needs to be prepared.
- Configure memorization portions and other academic settings.
- Track result-entry completion and result-release state.

### Assessment and result management

- Enter and update student subject scores within the selected academic period.
- Support continuous assessment and examination score fields, with subject-specific maximum scores.
- Validate result inputs and class/student relationships through server-side PHP endpoints.
- Manage behavioural/character assessment data separately from daily attendance records.
- Review class completion and dashboard progress indicators.
- Release results through the configured result-release controls.
- View student results and produce dedicated printable result statements.
- Configure result footer/signature information and print-related settings.
- Support conventional and Arabic result/class flows without treating matching class names as the same class.

The exact scoring limits and grading/remark rules should be confirmed in the live Settings and subject configuration; do not assume every school uses identical values merely from this README.

### Daily school attendance

The daily attendance module is available at `public/attendance.html` and uses the `api/attendance/` endpoints.

- Select a date, academic session/term, class category and class.
- Work with conventional classes or Arabic classes; their IDs and records remain category-aware.
- Load the active class roster and mark each student as **Present**, **Absent**, **Late** or **Excused**.
- Add an optional note for an individual student.
- Save attendance, reload existing records and correct records where the signed-in role has permission.
- See the class summary, status breakdown and marking progress.
- Use the student selector to jump to a student in a long register.
- Review historical attendance by week, month or full academic term, including weekday/date columns and per-student totals.
- View date-range attendance reports for a class or the school, subject to role access.
- Print the loaded daily register or download a spreadsheet-compatible CSV file.
- Teachers are restricted to their assigned classes and their configured attendance permission.

**Attendance data is not the same as the attendance/behaviour field on a result statement.** Daily marks are stored in `attendance_records`; termly behavioural assessment data is stored separately. The attendance academic-period migration deliberately leaves older attendance rows unassigned rather than guessing which term they belong to.

The print action is designed for the currently loaded daily register. The historical weekly/monthly/term view and school summary report are separate on-screen reporting views.

### User accounts, roles and security

The application uses server-side PHP sessions and role/permission checks. The repository includes workflows for:

- Admin and Exam Officer access to school-management operations.
- Management access to permitted dashboard and management functions.
- Teacher access limited by assigned classes, subjects and individual account permissions.
- Student sign-in and student result-portal access.
- Separate Online Class student authentication and portal access.
- Account creation and management, profile updates, account activation/freezing and access-control settings.
- Login lockout handling, account device activity and device-block controls.
- Password recovery for eligible active Admin accounts using one-time, expiring reset tokens and SMTP email.

A hidden button or front-end route is not an authorization boundary. The PHP API endpoints enforce the permissions for protected operations.

### MARKAZ Online Class

The Online Class module is a distinct set of pages, scripts and API endpoints. Its repository implementation includes:

- Online Class login and student portal pages.
- Class/level and section configuration.
- Student result and assessment views.
- Printable student and staff reports, including report layout and signature/footer support.
- Online Class student logout and supporting footer/contact information.
- Archived Online Class records and archive-related device activity.
- Device activity/security support for the portal.

Online Class APIs live under `api/online-class/`; browser pages and scripts include `public/online-class.html`, `public/online-class-login.html`, `public/online-class-portal.html`, and their related assets. Do not confuse these reports or archive records with the main school's daily attendance register.

### Public school website

The public website is served from the `public/` directory and includes pages for:

- Home, About, Academics, Admissions and Facilities.
- Gallery, FAQ, Contact and School Fees.
- Public navigation, shared footer, responsive styling and interaction/motion enhancements.
- Public SEO metadata and resilience/fallback behavior.
- Settings-managed website images and social links.

The public image manager and website image-storage helper handle managed website imagery. Keep original managed image files and backups safe when updating or deploying the project.

### Dashboard and operational settings

The management dashboard provides role-aware statistics and progress views, including:

- School-wide student, class and subject totals, plus academic result-entry completion/progress by class and student.
- A daily attendance overview for authorised Admin and Management users, showing the selected day's date and a donut breakdown of Present, Absent, Late and Excused records.
- Separate Conventional and Arabic attendance chart panels, each showing class-level attendance counts and present-rate visualization; the category panels remain visible when there are no saved marks yet.
- Automatic attendance refresh every 60 seconds and a refresh when the dashboard becomes visible again; chart layout is redrawn when the viewport changes.
- Role-aware dashboard content: teachers see their permitted workspace, Management sees assigned shortcuts and attendance only when its attendance permission is enabled, and administrative statistics are restricted by role.

The daily dashboard attendance figures come from the school-wide date-range report in `api/attendance/reports.php`; they are not a separate attendance data store. Settings APIs cover areas such as academic periods, result completion/release, account permissions, admin profile, memorization, result footer/signatures, social links, account devices, account freezes and login lockouts.

The dashboard and settings UI are separate from the public website and from the Online Class student portal.

## Technology stack

| Area | Technology |
|---|---|
| Server-side application | PHP 8.1 or newer |
| Database | MariaDB / MySQL with InnoDB and PDO |
| Browser interface | HTML5, CSS and vanilla JavaScript |
| Database access | PHP PDO with prepared statements |
| Email | PHPMailer with authenticated SMTP for password recovery |
| Dependency management | Composer |
| Automated checks | PHP CLI tests, PHP syntax checks, Node.js syntax checks and GitHub Actions |
| Version control | Git and GitHub |

The repository's Composer manifest declares PHP `^8.1` and `phpmailer/phpmailer` `^7.0`. Use a MariaDB/MySQL version compatible with the SQL features and InnoDB constraints used by the schema and migrations.

## Architecture and repository layout

```text
.
├── api/                    # Protected PHP API endpoints
│   ├── arabic-classes/     # Arabic class management
│   ├── attendance/         # Daily register, history, reports and periods
│   ├── auth/               # Login, session, logout and password recovery
│   ├── class-subjects/     # Class-to-subject assignments
│   ├── classes/            # Conventional class management
│   ├── dashboard/          # Dashboard statistics and progress
│   ├── online-class/       # Separate Online Class API
│   ├── results/            # Result entry, display and release workflows
│   ├── settings/           # Settings, accounts and permissions
│   ├── students/           # Student CRUD and status
│   └── subjects/           # Subject management
├── app/
│   ├── config/             # Environment, application and database setup
│   ├── helpers/            # Shared academic, security and domain helpers
│   └── middleware/         # Session and authorization middleware
├── database/
│   ├── migrations/         # Incremental database changes
│   ├── schema.sql          # Base database schema
│   └── seed.sql            # Initial/reference seed data
├── docs/                   # Focused operational setup notes
├── public/
│   ├── api/                # Public-path API entry-point proxies
│   ├── assets/             # Images and other public assets
│   ├── css/                # Page and shared stylesheets
│   ├── js/                 # Browser-side application scripts
│   └── *.html              # School, staff, student and portal pages
├── tests/                  # PHP regression and validation tests
├── composer.json           # PHP dependency manifest
├── .env.example            # SMTP and public application URL template
└── README.md
```

### Important entry points

| Purpose | Entry point |
|---|---|
| Public website | `public/index.php`, `public/home.html` |
| Staff login | `public/login.html` |
| Admin/Exam Officer login variants | `public/login.html`, `public/exam-officer-login.html` |
| Management login | `public/management-login.html` |
| Teacher login | `public/teacher-login.html` |
| Student login and result portal | `public/student-login.html`, `public/student-result.html` |
| Management dashboard | `public/dashboard.html` |
| Settings | `public/settings.html` |
| Daily attendance | `public/attendance.html` |
| Result entry/workspace | `public/student-result.html` |
| Result printing | `public/student-result-print.html`, `public/print-results.html` |
| Online Class | `public/online-class-login.html`, `public/online-class-portal.html` |

## Database and migrations

### Database creation

The starting schema is `database/schema.sql`. It creates the `markaz_result_system` database and the core tables. `database/seed.sql` contains initial/reference data for a new database.

The schema is a starting point, not a substitute for applying later migrations. The application has evolved through incremental migrations in `database/migrations/`, including changes for:

- Nullable result scores and role/account permissions.
- Arabic result/class support.
- Daily attendance and attendance-to-academic-period linking.
- Senior Secondary subject setup and subject-group codes.
- Current academic-period selection and academic-period finalization.
- Account device activity and device blocks.
- Online Class tables, sections, defaults, archive and device-activity support.

### Apply migrations safely

1. Back up the target database before any schema change.
2. Check which migrations have already been applied; the project does not provide an automatic migration runner in this repository.
3. Apply only migrations that are not yet present in the target database, in chronological filename order unless a migration's own instructions specify otherwise.
4. Review the SQL and verify the resulting schema before using the updated application.
5. Never re-run a non-idempotent `ALTER TABLE` migration against a database where it has already been applied.

Example for a migration that has not yet been applied:

```sh
mariadb -u root -p markaz_result_system < database/migrations/20261107_attendance_academic_period.sql
```

Enter the database password at the prompt. Omit `-p` only when the local database account intentionally has no password. Replace the filename with the specific unapplied migration; do not blindly run the example repeatedly.

### Database connection

The current repository version defines the database host, port, database name, username and password directly in `app/config/database.php`; the `.env` loader currently supplies application and SMTP settings, not the database connection. Treat these checked-in database values as local-development configuration only. **Before production deployment, move database credentials to private environment configuration (or an equivalent secret store), update the connection helper to read them, and use a dedicated least-privilege database account rather than the MariaDB/MySQL root account.** Never commit production credentials or paste them into chat.

## Local development setup

### Requirements

- PHP 8.1+ with PDO and the PDO MySQL driver.
- MariaDB/MySQL with the application database created and migrations applied.
- Composer.
- Node.js for JavaScript syntax checks.
- A modern browser.

### Install dependencies

From the repository root:

```sh
composer install
```

For production:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader
```

PHPMailer is used by the password-reset email workflow. The generated `vendor/` directory is a local dependency directory and should not be committed.

### Configure application URL and SMTP

Copy `.env.example` to `.env` and set the public application URL and SMTP sender details. The example file documents the expected keys:

```dotenv
APP_URL=https://your-markaz-domain.example
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=your-markaz-gmail@gmail.com
SMTP_PASSWORD=your-google-app-password
MAIL_FROM=your-markaz-gmail@gmail.com
MAIL_FROM_NAME=MARKAZ
```

For Gmail, use a Google App Password with 2-Step Verification enabled; do not use the normal Google account password. The authenticated sender and `MAIL_FROM` must match, or the From address must be an allowed alias. Keep `.env` private and never commit it.

Password-reset links must use the public URL that the receiving device can open. Do not set `APP_URL` to `localhost` or `127.0.0.1` for email links opened on another device.

See [docs/password-reset-email-setup.md](docs/password-reset-email-setup.md) for the detailed mail setup and security behavior.

### Start the local PHP server

Run from the repository root:

```sh
php -S 127.0.0.1:8083 -t public
```

Then open:

```text
http://127.0.0.1:8083
```

Use the same port consistently when testing the local MARKAZ instance. Ensure MariaDB is running before opening database-backed pages or APIs.

## Testing and quality checks

The project uses focused PHP regression tests rather than a single Composer test command. Run commands from the repository root.

### Attendance

```sh
php -l api/attendance/_common.php
php -l api/attendance/classes.php
php -l api/attendance/register.php
php -l api/attendance/history.php
php -l api/attendance/reports.php
php -l tests/attendance-validation.test.php
php -l tests/attendance-academic-history.test.php
php -l tests/attendance-one-page-print-readme.test.php
php tests/attendance-validation.test.php
php tests/attendance-academic-history.test.php
php tests/attendance-one-page-print-readme.test.php
node --check public/js/attendance.js
```

### Academic periods and results

```sh
php tests/academic-period-selection.test.php
php tests/academic-result-archive.test.php
php tests/result-print-current-period.test.php
php tests/result-print-flow-isolation.test.php
php tests/print-results-validation.test.php
php tests/print-threshold-profile-image.test.php
php tests/result-footer-signatures.test.php
php tests/student-result-display-regression.test.php
```

### Accounts, portals and permissions

```sh
php tests/admin-password-reset.test.php
php tests/account-security-regression.test.php
php tests/account-device-activity-regression.test.php
php tests/login-lockout-coverage.test.php
php tests/teacher-document-permissions.test.php
php tests/teacher-portal-dashboard.test.php
php tests/student-portal-flow.test.php
php tests/shared-book-auth-portals.test.php
```

### Online Class

```sh
php tests/online-class-regression.test.php
php tests/online-class-module-regression.test.php
php tests/online-class-architecture-regression.test.php
php tests/online-class-archive-crypto.test.php
php tests/online-class-archives-device-activity.test.php
php tests/online-class-level-settings-ui.test.php
php tests/online-class-portal-ui-regression.test.php
node --check public/js/online-class.js
node --check public/js/online-class-portal.js
node --check public/js/online-class-report.js
```

### Public website and shared UI

```sh
php tests/public-seo.test.php
php tests/public-network-resilience.test.php
php tests/public-visual-hierarchy.test.php
php tests/public-interaction-motion.test.php
php tests/shared-public-footer.test.php
php tests/website-image-storage.test.php
php tests/website-image-manager-fit.test.php
php tests/website-image-manager-persistence.test.php
php tests/website-image-manager-save.test.php
node --check public/js/public-resilience.js
node --check public/sw.js
```

GitHub Actions workflows under `.github/workflows/` run selected PHP and JavaScript checks for the areas they cover. A passing static regression test does not replace a real browser print preview or a production database backup/migration check.

## Deployment checklist

1. Provision PHP 8.1+ with PDO MySQL and the required PHP extensions.
2. Provision MariaDB/MySQL and create or restore the `markaz_result_system` database.
3. Back up the database and apply only the unapplied migrations.
4. Configure `app/config/database.php` for the production database.
5. Install Composer dependencies with the production command.
6. Configure private `.env` values for `APP_URL` and SMTP.
7. Point the web server document root to the repository's `public/` directory.
8. Enable HTTPS and ensure the PHP session cookie is served securely.
9. Ensure writable storage directories have appropriate least-privilege permissions.
10. Confirm MariaDB is running and test login, role permissions, student/class loading, attendance save/reload, result entry/printing, password recovery and Online Class access.
11. Confirm that website-managed images and any required uploaded assets are present on the server.
12. Keep database backups and application file backups outside the public web root.

Do not expose `.env`, database backups, logs, `vendor/`, or private storage through the public document root. Configure the web server to deny access to hidden files and non-public project directories.

## Security and data-handling notes

The codebase includes server-side role/permission checks, password-hash verification, persistent failed-login lockouts, account-freeze checks, device-block checks, prepared database statements in the documented attendance paths, and protected staff session cookies. These controls are useful safeguards, but they do not replace production hardening and a full security review.

Before production:

- Move database credentials out of `app/config/database.php`; use a dedicated database user with only the privileges the application needs.
- Serve the application over HTTPS and configure the staff and student session cookies with `Secure`, `HttpOnly` and an appropriate `SameSite` policy. The current session helper sets `HttpOnly` and `SameSite=Lax`, but hard-codes `secure=false`; correct this before HTTPS deployment.
- Review CSRF protection for every state-changing authenticated API, not only the attendance write flow. Validate same-origin requests or use per-session CSRF tokens where appropriate.
- Audit every API endpoint for server-side authentication, role, record ownership and class/subject assignment checks. Hiding a link or page in JavaScript is not authorization.
- Confirm password-reset flows, SMTP credentials, account recovery and administrator access are configured securely; use strong unique passwords and multi-factor authentication for the hosting, database, email and source-control accounts where available.
- Configure web-server rules to deny access to `.env`, source/configuration files, logs, session storage, backups, SQL dumps and other non-public directories.
- Validate uploaded file size, actual MIME type, extension, generated filename and storage location; prevent executable uploads and direct public access to private student files.
- Add and verify suitable security headers (for example, content-type sniffing protection, clickjacking protection and a carefully tested Content Security Policy).
- Keep PHP, MariaDB/MySQL, Composer dependencies and the hosting operating system patched; review dependency advisories and remove unused packages.
- Enable reliable off-server database and file backups, restrict backup access, and test restoration periodically.
- Keep audit trails for sensitive administrative actions and monitor authentication failures, unexpected server errors and privileged account changes.
- Back up the database before migrations, period finalization or bulk academic-data clearing; test migrations and recovery before production changes.
- Never commit `.env`, database dumps, credentials, student data, generated backup files or private uploads.

The current staff session helper uses `HttpOnly` and `SameSite=Lax`, but sets the session cookie `secure` flag to `false`. The production deployment must correct this rather than assuming HTTPS alone changes the cookie setting. The general CSRF coverage, security headers, web-server deny rules, upload hardening and production secret handling still require explicit verification; do not treat them as complete based only on this README.

## Operational documentation

- [Daily Attendance](docs/DAILY_ATTENDANCE.md) — attendance behavior, migration and focused checks.
- [Password-reset email setup](docs/password-reset-email-setup.md) — PHPMailer/Gmail SMTP configuration and reset-link requirements.
- [Finance installation](docs/finance-installation.md) — safe, ordered installation of the Finance/payroll, fee-category and academic term-fee database migrations.

## Contributing and maintenance

- Keep changes focused on the relevant module.
- Add or update a regression test for behavioral changes.
- Run the focused PHP and Node checks before opening a pull request.
- Keep migrations incremental and document any manual deployment step.
- Update this README and the relevant module guide when adding a new user-facing workflow or operational requirement.
- Preserve local backups and untracked user-managed assets during Git updates.

## License and ownership

This repository is maintained for the MARKAZ school management project. No open-source license is declared in the repository at the time this README was written; obtain project-owner approval before redistributing or reusing the software.


## MARKAZ AI assistant

The MARKAZ staff assistant is available to authenticated Admin, Exam Officer, Teacher and Management accounts. It uses server-verified role/permission scopes, private conversation history and an Admin activity feed. In no-cost mode, typed answers are deterministic and limited to supported questions grounded in permitted MARKAZ records; this is not an unrestricted generative language model. The result-sheet scanner uses browser-side Tesseract.js OCR to extract raw text locally for manual review; images are not uploaded to MARKAZ or an AI provider. OCR does not identify students or save marks. Voice transcription and student-facing AI are disabled. See [docs/markaz-ai-setup.md](docs/markaz-ai-setup.md) for the exact limits, privacy behavior and setup requirements.
