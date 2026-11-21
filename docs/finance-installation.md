# MARKAZ Finance database installation

The Finance workspace requires three migrations, applied in this exact order:

1. 20261121_markaz_finance_payroll.sql — creates the finance foundation, including fee plans, invoices, payments, accounts, ledger, expenses and payroll.
2. 20261122_markaz_finance_fee_categories.sql — adds fee-category support to monthly fee plans and invoices.
3. 20261123_markaz_academic_term_fees.sql — adds academic sessions, class term-fee schedules, student adjustments/scholarships, term invoices and term-payment allocations.

The third migration references finance_fee_payments, which is created by the first migration. Running only the third migration is not a valid installation.

## Recommended installation

From the repository root, run:

```sh
bash scripts/install-finance.sh
```

The installer checks the database and required school-table prerequisites, detects complete and partial Finance schemas, refuses to guess when a partial migration is found, asks for the exact confirmation phrase INSTALL FINANCE, creates and verifies a timestamped SQL backup, applies only missing stages in dependency order, and verifies the expected tables and columns.

It uses the normal MariaDB client credentials/configuration for the current shell. Configure access through the local MariaDB option file or deployment environment; do not put passwords in the script or commit credentials.

To target another database, set DB_NAME explicitly:

```sh
DB_NAME=markaz_result_system bash scripts/install-finance.sh
```

## Safety notes

- Test the complete Finance workflow against a dedicated test database before production use.
- The installer does not seed demo students, fees, receipts or payroll records. Existing active MARKAZ students and classes remain the source records.
- These migrations add tables and columns; they do not replace existing student, attendance, result or class records.
- MariaDB DDL statements auto-commit. If a migration fails after creating some objects, the installer stops and reports the backup path. Do not rerun blindly: restore the backup or have the partial schema reviewed first.
- Keep the generated SQL backup outside the public web root until the Finance workflow has been verified.
- A successful schema installation does not prove that production payments, payroll-provider integration or every user workflow is configured. Verify those separately before live use.
