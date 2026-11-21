# MARKAZ Finance & Payroll — isolated development module

Development branch: `feature/markaz-finance-payroll`

This workspace is separate from the production dashboard. It is not linked in the live navigation and has not been merged into `main`.

## Implemented in this branch
- Monthly fee plans configured by Admin for the whole school, a conventional/Arabic class, or an individual active student.
- Monthly invoice generation with duplicate protection, student admission-number lookup, fee account table, partial payment allocations and printable receipt page.
- Cash receipts and manually recorded bank/POS/online entries with pending verification for non-cash payments.
- Separate other-school-income and expense records, approval/verification queues, balanced journal posting and a basic date-range report.
- Teacher salary profiles attached to existing `admins.id` staff accounts, including effective-date history.
- Separate teacher attendance records, no grace period, configurable per-minute deduction, monthly payroll calculation with calendar-day salary proration for mid-month profile changes, and Admin-approved scheduled release records.
- Finance access limited to Admin and Management accounts with the existing `management_school_fees` permission. Student, teacher and exam-officer accounts do not get the finance workspace.
- The production dashboard, navigation and student attendance tables are not changed by this branch.

## Financial rules
1. Amounts use NGN decimal storage and integer-cent arithmetic for calculations.
2. The default lateness rate is NGN 10 per minute, configurable on each teacher salary profile. No grace period is applied. Five minutes therefore calculates as NGN 50.
3. Fee amounts, due dates, salary amounts, expected arrival times and payment schedules are administrator-configured; no class or student fee amount is embedded in the application.
4. Bank transfer entries stay pending until an authorised staff member verifies the actual receipt. A screenshot alone does not confirm settlement.
5. Cash entries are recorded as received by the staff member entering them.
6. Expenses are posted only after approval. Posted financial entries are retained; corrections should use reversals or adjustments, not destructive edits.
7. Payroll figures are calculated first and require Admin/authorised Finance approval before a scheduled payout can be considered.
8. A payout provider must support stable unique references/idempotency. Unknown transfer outcomes must be reconciled, not blindly retried.
9. Never commit payment-provider secrets or unencrypted teacher bank credentials.

## Integration contracts
- Student references use existing `students.id`; admission number, names and class labels are read from the existing MARKAZ student records.
- Class fee plans use `class_type` plus `class_id` because conventional and Arabic classes are separate tables.
- Staff salary and attendance records use existing `admins.id` teacher accounts.
- Student daily attendance remains untouched. Staff arrival records live in `staff_attendance`.
- Management access reuses the existing `management_school_fees` permission key in `account_permissions`.

## Isolated local preview — port 884

Do not point this branch at the live/working database.

1. Create a disposable database named `markaz_finance_dev`.
2. Import a recent development copy of MARKAZ so the existing students, classes, staff accounts and permissions are available.
3. Back up that development database, then apply `database/migrations/20261121_markaz_finance_payroll.sql` to it only.
4. From the feature branch root, start the PHP server with an explicit database override:

```sh
DB_NAME=markaz_finance_dev php -S 127.0.0.1:884 -t public
```

The connection helper accepts `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` and `DB_PASSWORD` environment overrides. Existing local defaults are retained when overrides are absent. Keep credentials out of Git.

Open `http://127.0.0.1:884/login.html`, sign in with an account from the development database, then open `http://127.0.0.1:884/finance.html`.

## Not yet production-enabled / remaining work
- No automatic bank feed is connected. Incoming bank transfers must be verified manually against the bank statement.
- Online fee checkout and payment webhooks are not yet implemented.
- Scheduled payroll approval is stored, but actual bank payouts are not implemented or enabled. A provider, verified teacher recipient setup, server secrets, a public webhook endpoint and a reliable server scheduler are required before real transfers.
- Bank statement reconciliation, refunds/reversals, discounts/scholarships, fee-plan editing/deactivation, payslip PDF delivery, full audit/trial-balance exports and end-to-end browser testing remain before merge.
- The PHP regression test and PHP syntax checks must be run in the Termux checkout before treating this branch as ready for review.
