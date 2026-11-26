#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_NAME="${DB_NAME:-}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_USER="${DB_USER:-root}"
DB_PORT="${DB_PORT:-3306}"
MIGRATIONS="$ROOT/database/migrations"

if [[ -z "$DB_NAME" ]]; then
    echo "ERROR: DB_NAME must be set explicitly. Refusing to guess a database target." >&2
    echo "For a disposable clone, use: DB_NAME=markaz_result_system_finance_test bash scripts/install-finance.sh" >&2
    exit 2
fi
if [[ "${DB_NAME,,}" == "markaz_result_system" && "${ALLOW_PRODUCTION_INSTALL:-}" != "YES" ]]; then
    echo "ERROR: Refusing to modify the live MARKAZ database." >&2
    echo "Production requires the explicit environment setting ALLOW_PRODUCTION_INSTALL=YES." >&2
    exit 2
fi

if [[ ! "$DB_NAME" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "ERROR: DB_NAME may contain only letters, numbers and underscores." >&2
    exit 2
fi
for command in mariadb mariadb-dump; do
    command -v "$command" >/dev/null 2>&1 || { echo "ERROR: Required command not found: $command" >&2; exit 2; }
done
for file in 20261121_markaz_finance_payroll.sql 20261122_markaz_finance_fee_categories.sql 20261123_markaz_academic_term_fees.sql 20261124_markaz_independent_finance_staff.sql 20261125_teacher_attendance_modes.sql 20261126_markaz_fee_refunds.sql 20261127_payroll_deduction_and_staff_details.sql; do
    [[ -s "$MIGRATIONS/$file" ]] || { echo "ERROR: Required migration is missing or empty: $MIGRATIONS/$file" >&2; exit 2; }
done

# Use the same local TCP connection pattern as the MARKAZ PHP database config.
# Credentials can be supplied through the user's MariaDB option file (~/.my.cnf).
DB_CONNECT=(--protocol=TCP --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER")
db_client() { mariadb "${DB_CONNECT[@]}" "$@"; }
db_dump() { mariadb-dump "${DB_CONNECT[@]}" "$@"; }
query() { db_client --batch --skip-column-names -e "$1"; }
db_exists="$(query "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$DB_NAME'")"
[[ "$db_exists" == "1" ]] || { echo "ERROR: Database '$DB_NAME' does not exist or is not accessible." >&2; exit 2; }

for table in admins students classes; do
    engine="$(query "SELECT COALESCE(ENGINE,'') FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='$table'")"
    id_type="$(query "SELECT COALESCE(COLUMN_TYPE,'') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='$table' AND COLUMN_NAME='id'")"
    [[ "$engine" == "InnoDB" && "$id_type" == "bigint(20) unsigned" ]] || {
        echo "ERROR: '$table' must exist as InnoDB with an unsigned BIGINT id. No changes made." >&2
        exit 2
    }
done

foundation_tables=(
    finance_settings finance_fee_plans finance_fee_invoices finance_fee_payments
    finance_fee_allocations finance_accounts finance_journals finance_journal_lines
    finance_expenses finance_other_income staff_salary_profiles staff_attendance
    finance_payroll_periods finance_payroll_items finance_payroll_deductions
    finance_payroll_payments finance_audit_logs
)
term_tables=(
    finance_academic_sessions finance_term_fee_schedules finance_student_fee_adjustments
    finance_term_fee_invoices finance_term_fee_payments
)
sql_names() {
    local output="" item
    for item in "$@"; do
        [[ -n "$output" ]] && output+=","
        output+="'$item'"
    done
    printf '%s' "$output"
}
table_count() {
    query "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME IN ($1)"
}

foundation_count="$(table_count "$(sql_names "${foundation_tables[@]}")")"
term_count="$(table_count "$(sql_names "${term_tables[@]}")")"
category_columns="$(query "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB_NAME' AND ((TABLE_NAME='finance_fee_plans' AND COLUMN_NAME='fee_code') OR (TABLE_NAME='finance_fee_invoices' AND COLUMN_NAME='fee_code'))")"

if [[ "$foundation_count" != "0" && "$foundation_count" != "${#foundation_tables[@]}" ]]; then
    echo "ERROR: Partial finance foundation detected ($foundation_count/${#foundation_tables[@]} tables). No changes made." >&2
    exit 3
fi
if [[ "$foundation_count" == "0" && ( "$category_columns" != "0" || "$term_count" != "0" ) ]]; then
    echo "ERROR: Finance add-on tables/columns exist without the complete foundation. No changes made." >&2
    exit 3
fi
if [[ "$category_columns" != "0" && "$category_columns" != "2" ]]; then
    echo "ERROR: Partial fee-category migration detected ($category_columns/2 columns). No changes made." >&2
    exit 3
fi
if [[ "$term_count" != "0" && "$term_count" != "${#term_tables[@]}" ]]; then
    echo "ERROR: Partial academic term-fee schema detected ($term_count/${#term_tables[@]} tables). No changes made." >&2
    exit 3
fi
independent_staff_tables="$(query "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='finance_staff'")"
independent_staff_columns="$(query "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB_NAME' AND ((TABLE_NAME='finance_staff' AND COLUMN_NAME='id') OR (TABLE_NAME='staff_salary_profiles' AND COLUMN_NAME='finance_staff_id') OR (TABLE_NAME='staff_attendance' AND COLUMN_NAME='finance_staff_id') OR (TABLE_NAME='finance_payroll_items' AND COLUMN_NAME='finance_staff_id'))")"
attendance_policy_columns="$(query "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB_NAME' AND COLUMN_NAME='attendance_mode' AND TABLE_NAME IN ('staff_salary_profiles','staff_attendance')")"
refund_tables="$(query "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME IN ('finance_fee_refunds','finance_fee_refund_allocations')")"
independent_staff_constraints="$(query "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='$DB_NAME' AND REFERENCED_TABLE_NAME='finance_staff' AND ((TABLE_NAME='staff_salary_profiles' AND COLUMN_NAME='finance_staff_id') OR (TABLE_NAME='staff_attendance' AND COLUMN_NAME='finance_staff_id') OR (TABLE_NAME='finance_payroll_items' AND COLUMN_NAME='finance_staff_id'))")"
if [[ "$independent_staff_tables" == "0" && ( "$independent_staff_columns" != "0" || "$independent_staff_constraints" != "0" ) ]]; then
    echo "ERROR: Partial independent Finance staff migration detected. No changes made." >&2
    exit 3
fi
if [[ "$independent_staff_tables" == "1" && ( "$independent_staff_columns" != "4" || "$independent_staff_constraints" != "3" ) ]]; then
    echo "ERROR: Partial independent Finance staff migration detected ($independent_staff_columns/4 required columns, $independent_staff_constraints/3 required foreign keys). No changes made." >&2
    exit 3
fi
if [[ "$refund_tables" != "0" && "$refund_tables" != "2" ]]; then
    echo "ERROR: Partial student fee refund migration detected ($refund_tables/2 tables). No changes made." >&2
    exit 3
fi
if [[ "$attendance_policy_columns" != "0" && "$attendance_policy_columns" != "2" ]]; then
        echo "ERROR: Partial teacher attendance policy migration detected ($attendance_policy_columns/2 columns). No changes made." >&2
        exit 3
    fi
if [[ "$foundation_count" == "${#foundation_tables[@]}" && "$category_columns" == "2" && "$term_count" == "${#term_tables[@]}" && "$independent_staff_tables" == "1" && "$independent_staff_columns" == "4" && "$independent_staff_constraints" == "3" && "$attendance_policy_columns" == "2" && "$refund_tables" == "2" ]]; then
    echo "MARKAZ Finance is already installed: fee foundations, term fees, independent Finance staff, attendance policies and refund controls are present."
    exit 0
fi

echo "Target database: $DB_NAME"
echo "Finance tables will be added; existing student, class, attendance and result records will not be deleted or rewritten."
if [[ "${DB_NAME,,}" == "markaz_result_system" ]]; then
    read -r -p "LIVE PRODUCTION DATABASE. Type INSTALL FINANCE ON PRODUCTION DATABASE to continue: " confirmation
    [[ "$confirmation" == "INSTALL FINANCE ON PRODUCTION DATABASE" ]] || { echo "Cancelled. No database changes made."; exit 0; }
else
    read -r -p "Type INSTALL FINANCE ON TEST DATABASE to continue (anything else cancels): " confirmation
    [[ "$confirmation" == "INSTALL FINANCE ON TEST DATABASE" ]] || { echo "Cancelled. No database changes made."; exit 0; }
fi

backup="$HOME/markaz-before-finance-$(date +%Y%m%d-%H%M%S).sql"
echo "Creating database backup: $backup"
db_dump --single-transaction --routines --triggers --events "$DB_NAME" > "$backup"
[[ -s "$backup" ]] || { echo "ERROR: Backup is empty. No migrations were run." >&2; exit 4; }
echo "Backup verified ($(wc -c < "$backup") bytes)."

apply_migration() {
    local file="$1"
    echo "Applying $file"
    db_client "$DB_NAME" < "$MIGRATIONS/$file"
}
if [[ "$foundation_count" == "0" ]]; then apply_migration "20261121_markaz_finance_payroll.sql"; fi
foundation_count="$(table_count "$(sql_names "${foundation_tables[@]}")")"
[[ "$foundation_count" == "${#foundation_tables[@]}" ]] || { echo "ERROR: Finance foundation verification failed. Restore from: $backup" >&2; exit 5; }

if [[ "$category_columns" == "0" ]]; then apply_migration "20261122_markaz_finance_fee_categories.sql"; fi
category_columns="$(query "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB_NAME' AND ((TABLE_NAME='finance_fee_plans' AND COLUMN_NAME='fee_code') OR (TABLE_NAME='finance_fee_invoices' AND COLUMN_NAME='fee_code'))")"
[[ "$category_columns" == "2" ]] || { echo "ERROR: Fee-category verification failed. Restore from: $backup" >&2; exit 5; }

if [[ "$term_count" == "0" ]]; then apply_migration "20261123_markaz_academic_term_fees.sql"; fi
term_count="$(table_count "$(sql_names "${term_tables[@]}")")"
[[ "$term_count" == "${#term_tables[@]}" ]] || { echo "ERROR: Academic term-fee verification failed. Restore from: $backup" >&2; exit 5; }

# Apply and verify the independent Finance staff identity migration.
if [[ "${foundation_count}" == "${#foundation_tables[@]}" && "${category_columns}" == "2" && "${term_count}" == "${#term_tables[@]}" ]]; then
    independent_staff_tables="$(query "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='finance_staff'")"
    independent_staff_columns="$(query "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB_NAME' AND ((TABLE_NAME='finance_staff' AND COLUMN_NAME='id') OR (TABLE_NAME='staff_salary_profiles' AND COLUMN_NAME='finance_staff_id') OR (TABLE_NAME='staff_attendance' AND COLUMN_NAME='finance_staff_id') OR (TABLE_NAME='finance_payroll_items' AND COLUMN_NAME='finance_staff_id'))")"
    if [[ "$independent_staff_tables" == "0" && "$independent_staff_columns" == "0" ]]; then
        apply_migration "20261124_markaz_independent_finance_staff.sql"
        independent_staff_tables="$(query "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='finance_staff'")"
        independent_staff_columns="$(query "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB_NAME' AND ((TABLE_NAME='finance_staff' AND COLUMN_NAME='id') OR (TABLE_NAME='staff_salary_profiles' AND COLUMN_NAME='finance_staff_id') OR (TABLE_NAME='staff_attendance' AND COLUMN_NAME='finance_staff_id') OR (TABLE_NAME='finance_payroll_items' AND COLUMN_NAME='finance_staff_id'))")"
        independent_staff_constraints="$(query "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='$DB_NAME' AND REFERENCED_TABLE_NAME='finance_staff' AND ((TABLE_NAME='staff_salary_profiles' AND COLUMN_NAME='finance_staff_id') OR (TABLE_NAME='staff_attendance' AND COLUMN_NAME='finance_staff_id') OR (TABLE_NAME='finance_payroll_items' AND COLUMN_NAME='finance_staff_id'))")"
    fi
    if [[ "$independent_staff_tables" != "1" || "$independent_staff_columns" != "4" || "$independent_staff_constraints" != "3" ]]; then
        echo "ERROR: Independent Finance staff migration is partial ($independent_staff_tables table, $independent_staff_columns/4 required columns, $independent_staff_constraints/3 required foreign keys). Restore from the verified backup: $backup" >&2
        exit 5
    fi
fi

# Apply attendance policy only after the independent Finance staff IDs exist.
if [[ "$attendance_policy_columns" == "0" ]]; then apply_migration "20261125_teacher_attendance_modes.sql"; fi
attendance_policy_columns="$(query "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB_NAME' AND COLUMN_NAME='attendance_mode' AND TABLE_NAME IN ('staff_salary_profiles','staff_attendance')")"
[[ "$attendance_policy_columns" == "2" ]] || { echo "ERROR: Teacher attendance policy verification failed. Restore from: $backup" >&2; exit 5; }

# Refund schema is applied last because it references confirmed fee payments.
if [[ "$refund_tables" == "0" ]]; then apply_migration "20261126_markaz_fee_refunds.sql"; fi
payroll_staff_details="$(query "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='finance_staff' AND COLUMN_NAME IN ('age','bank_account_number','bank_account_name','bank_name','teaching_type')")"
payroll_opt_in="$(query "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='staff_salary_profiles' AND COLUMN_NAME='deduct_lateness'")"
if [[ "$payroll_staff_details" == "0" && "$payroll_opt_in" == "0" ]]; then apply_migration "20261127_payroll_deduction_and_staff_details.sql"; fi
payroll_staff_details="$(query "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='finance_staff' AND COLUMN_NAME IN ('age','bank_account_number','bank_account_name','bank_name','teaching_type')")"
payroll_opt_in="$(query "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='staff_salary_profiles' AND COLUMN_NAME='deduct_lateness'")"
[[ "$payroll_staff_details" == "5" && "$payroll_opt_in" == "1" ]] || { echo "ERROR: Payroll staff detail migration verification failed. Restore from: $backup" >&2; exit 5; }
refund_tables="$(query "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME IN ('finance_fee_refunds','finance_fee_refund_allocations')")"
[[ "$refund_tables" == "2" ]] || { echo "ERROR: Fee refund schema verification failed. Restore from: $backup" >&2; exit 5; }

echo "SUCCESS: MARKAZ Finance schema is installed and verified."
echo "Backup retained at: $backup"
