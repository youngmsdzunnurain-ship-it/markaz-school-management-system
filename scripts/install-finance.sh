#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_NAME="${DB_NAME:-markaz_result_system}"
MIGRATIONS="$ROOT/database/migrations"

if [[ ! "$DB_NAME" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "ERROR: DB_NAME may contain only letters, numbers and underscores." >&2
    exit 2
fi
for command in mariadb mariadb-dump; do
    command -v "$command" >/dev/null 2>&1 || { echo "ERROR: Required command not found: $command" >&2; exit 2; }
done
for file in 20261121_markaz_finance_payroll.sql 20261122_markaz_finance_fee_categories.sql 20261123_markaz_academic_term_fees.sql; do
    [[ -s "$MIGRATIONS/$file" ]] || { echo "ERROR: Required migration is missing or empty: $MIGRATIONS/$file" >&2; exit 2; }
done

query() { mariadb --batch --skip-column-names -e "$1"; }
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
if [[ "$foundation_count" == "${#foundation_tables[@]}" && "$category_columns" == "2" && "$term_count" == "${#term_tables[@]}" ]]; then
    echo "MARKAZ Finance is already installed: foundation, fee categories and term fees are present."
    exit 0
fi

echo "Target database: $DB_NAME"
echo "Finance tables will be added; existing student, class, attendance and result records will not be deleted or rewritten."
read -r -p "Type INSTALL FINANCE to continue (anything else cancels): " confirmation
[[ "$confirmation" == "INSTALL FINANCE" ]] || { echo "Cancelled. No database changes made."; exit 0; }

backup="$HOME/markaz-before-finance-$(date +%Y%m%d-%H%M%S).sql"
echo "Creating database backup: $backup"
mariadb-dump "$DB_NAME" > "$backup"
[[ -s "$backup" ]] || { echo "ERROR: Backup is empty. No migrations were run." >&2; exit 4; }
echo "Backup verified ($(wc -c < "$backup") bytes)."

apply_migration() {
    local file="$1"
    echo "Applying $file"
    mariadb "$DB_NAME" < "$MIGRATIONS/$file"
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

echo "SUCCESS: MARKAZ Finance schema is installed and verified."
echo "Backup retained at: $backup"
