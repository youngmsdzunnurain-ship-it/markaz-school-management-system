# MARKAZ Finance database installation

The Finance workspace migrations are applied in this exact order:

1. 20261121_markaz_finance_payroll.sql — creates the finance foundation, including fee plans, invoices, payments, accounts, ledger, expenses and payroll.
2. 20261122_markaz_finance_fee_categories.sql — adds fee-category support to monthly fee plans and invoices.
3. 20261123_markaz_academic_term_fees.sql — adds academic sessions, class term-fee schedules, student adjustments/scholarships, term invoices and term-payment allocations.
4. 20261124_markaz_independent_finance_staff.sql — creates the independent Finance staff register and changes salary, teacher attendance and payroll records to use Finance staff IDs instead of academic teacher login IDs.
5. 20261125_teacher_attendance_modes.sql — separates time-based staff, whose recorded lateness may affect salary, from attendance-only staff, whose arrival time is not used for payroll. Existing staff remain time-based by default.
6. 20261126_markaz_fee_refunds.sql — adds maker-checker fee refund requests and refund-allocation records so approved refunds can restore monthly or term invoice balances without deleting payment history.
7. 20261127_payroll_deduction_and_staff_details.sql — adds the approved payroll staff details and lateness deduction settings.
8. 20261128_teacher_lateness_rate_13_naira.sql — sets the approved default lateness deduction rate.
9. 20261129_markaz_section_student_fees.sql — links term dates to the existing academic calendar and adds Nursery, Primary, Junior Secondary and Senior Secondary Day/Boarding/New Intake fee rates.

The third migration references finance_fee_payments, which is created by the first migration. The fourth migration is applied only after the complete Finance foundation and fee migrations are present. The fifth migration depends on the independent Finance staff IDs from migration four. The sixth migration depends on the confirmed fee-payment and invoice-allocation tables. The seventh and eighth migrations extend payroll settings. The ninth migration depends on the core academic-period and term-invoice tables; it preserves existing invoices and payments while adding the new section-based rates. The installer applies all stages in dependency order. Running a later migration by itself is not a valid installation.

## Recommended installation

From the repository root, run:

```sh
DB_NAME=markaz_result_system_finance_test bash scripts/install-finance.sh
```

The installer checks the database and required school-table prerequisites, detects complete and partial Finance schemas, refuses to guess when a partial migration is found, asks for the exact confirmation phrase INSTALL FINANCE ON TEST DATABASE, creates and verifies a timestamped SQL backup, applies only missing stages in dependency order, and verifies the expected tables and columns, including the independent Finance staff identity conversion, attendance policies, refund tables, academic term dates and section-based fee schedule.

The installer requires DB_NAME to be set explicitly and refuses to guess a target. It blocks the live database name `markaz_result_system` unless `ALLOW_PRODUCTION_INSTALL=YES` is explicitly supplied. For a production install, both the environment override and the distinct production confirmation phrase are required. The installer connects over TCP to 127.0.0.1 as root on port 3306 by default. Override with DB_HOST, DB_PORT and DB_USER when needed. For password-protected databases, configure credentials in the user's MariaDB option file (for example ~/.my.cnf with restrictive permissions); do not put passwords in the script, command line or repository.

To target a disposable test clone, set its database name explicitly:

```sh
DB_NAME=markaz_result_system_finance_test bash scripts/install-finance.sh
```

Do not point this at the live database during testing. Create and verify the test clone first; the installer only creates the finance schema inside the named target.

## Safety notes

- Test the complete Finance workflow against a dedicated test database before production use.
- The installer does not seed demo students, fees, receipts or payroll records. Existing active MARKAZ students and classes remain the source records.
- These migrations add Finance tables and change only Finance payroll identity references; they do not replace student attendance, result or class records.
- Teacher attendance is entered from the existing Attendance workspace and is restricted to Admin and Management accounts explicitly assigned full Attendance access. Time-based staff must enter arrival times; attendance-only staff are not timed for salary. Excused and approved-leave records require a reason and never incur lateness deductions. Finance staff membership is independent of academic teacher login accounts.
- Fee refunds are requested by one authorised staff member and must be reviewed by a different authorised staff member. Approved refunds reverse the original monthly/term invoice allocations and post a balanced ledger journal; original receipts and payment history are retained.
- MariaDB DDL statements auto-commit. If a migration fails after creating some objects, the installer stops and reports the backup path. Do not rerun blindly: restore the backup or have the partial schema reviewed first.
- Keep the generated SQL backup outside the public web root until the Finance workflow has been verified.
- A successful schema installation does not prove that production payments, payroll-provider integration or every user workflow is configured. Verify those separately before live use.


## Section-based Student Fees

The Student Fees workspace reads its academic session and term directly from the active MARKAZ `academic_periods` record. Configure the current term's start and end dates in Settings before setting fee rates or generating assessments.

Each academic period has 16 rates: four education sections (Nursery, Primary, Junior Secondary and Senior Secondary), two accommodation types (Day Student and Boarding), and two intake types (Returning and New Intake). New Intake is selected automatically when the student's recorded admission date falls within the configured current-term date range. Other active students use the Returning rate.

Generated invoices keep a snapshot of the rate, section, accommodation and intake classification used at assessment time. Updating a rate affects only new assessments in that academic period; existing invoices, payments and earlier-term balances are not rewritten. Individual scholarships and reductions are approved separately. An issued invoice can be amended only before any payment history exists; once a payment has been recorded, the invoice is locked against silent changes.

The central register shows the current-term payable amount, original normal fee, approved reduction, current-term payments and balance, plus previous-term debt itemised by academic session and term. Total due and total balance include the carried-forward debt. Instalments are allocated to the current term first, then any legacy monthly invoices, and then earlier-term invoices, so a partial current-term payment does not silently consume the separately displayed previous-term debt.
