# MARKAZ Finance database installation

The Finance workspace requires six migrations, applied in this exact order:

1. 20261121_markaz_finance_payroll.sql — creates the finance foundation, including fee plans, invoices, payments, accounts, ledger, expenses and payroll.
2. 20261122_markaz_finance_fee_categories.sql — adds fee-category support to monthly fee plans and invoices.
3. 20261123_markaz_academic_term_fees.sql — adds academic sessions, class term-fee schedules, student adjustments/scholarships, term invoices and term-payment allocations.
4. 20261124_markaz_independent_finance_staff.sql — creates the independent Finance staff register and changes salary, teacher attendance and payroll records to use Finance staff IDs instead of academic teacher login IDs.
5. 20261125_teacher_attendance_modes.sql — separates time-based staff, whose recorded lateness may affect salary, from attendance-only staff, whose arrival time is not used for payroll. Existing staff remain time-based by default.
6. 20261126_markaz_fee_refunds.sql — adds maker-checker fee refund requests and refund-allocation records so approved refunds can restore monthly or term invoice balances without deleting payment history.

The third migration references finance_fee_payments, which is created by the first migration. The fourth migration is applied only after the complete Finance foundation and fee migrations are present. The fifth migration depends on the independent Finance staff IDs from migration four. The sixth migration depends on the confirmed fee-payment and invoice-allocation tables. It imports only people already referenced by historical Finance payroll records; it never bulk-enrols academic teacher accounts. Running a later migration by itself is not a valid installation.

## Recommended installation

From the repository root, run:

```sh
DB_NAME=markaz_result_system_finance_test bash scripts/install-finance.sh
```

The installer checks the database and required school-table prerequisites, detects complete and partial Finance schemas, refuses to guess when a partial migration is found, asks for the exact confirmation phrase INSTALL FINANCE ON TEST DATABASE, creates and verifies a timestamped SQL backup, applies only missing stages in dependency order, and verifies the expected tables and columns, including the independent Finance staff identity conversion, the two attendance-policy columns and the two refund tables.

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
