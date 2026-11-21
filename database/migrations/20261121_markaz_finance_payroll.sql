-- MARKAZ Finance & Payroll foundation.
-- Apply only to a dedicated test database first. This migration is additive:
-- it does not modify student, attendance, staff, result or existing finance data.
-- Class references are polymorphic because conventional and Arabic classes
-- live in separate tables. All monetary values are stored in NGN decimal units.

CREATE TABLE finance_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_finance_setting_actor FOREIGN KEY (updated_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE finance_fee_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    description TEXT NULL,
    scope ENUM('school','class','student') NOT NULL,
    class_type ENUM('conventional','arabic') NULL,
    class_id BIGINT UNSIGNED NULL,
    student_id BIGINT UNSIGNED NULL,
    amount DECIMAL(14,2) NOT NULL,
    frequency ENUM('monthly') NOT NULL DEFAULT 'monthly',
    due_day TINYINT UNSIGNED NOT NULL,
    effective_from DATE NOT NULL,
    effective_until DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_fee_plan_scope (scope, class_type, class_id, student_id, is_active),
    KEY idx_fee_plan_effective (effective_from, effective_until),
    CONSTRAINT fk_fee_plan_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
    CONSTRAINT fk_fee_plan_creator FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
    CONSTRAINT chk_fee_plan_amount CHECK (amount >= 0),
    CONSTRAINT chk_fee_plan_scope CHECK (
      (scope='school' AND class_type IS NULL AND class_id IS NULL AND student_id IS NULL) OR
      (scope='class' AND class_type IS NOT NULL AND class_id IS NOT NULL AND student_id IS NULL) OR
      (scope='student' AND class_type IS NULL AND class_id IS NULL AND student_id IS NOT NULL)
    )
) ENGINE=InnoDB;

CREATE TABLE finance_fee_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    student_id BIGINT UNSIGNED NOT NULL,
    fee_plan_id BIGINT UNSIGNED NOT NULL,
    billing_year SMALLINT UNSIGNED NOT NULL,
    billing_month TINYINT UNSIGNED NOT NULL,
    issued_on DATE NOT NULL,
    due_on DATE NOT NULL,
    amount_due DECIMAL(14,2) NOT NULL,
    discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('unpaid','partial','paid','void') NOT NULL DEFAULT 'unpaid',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_plan_month (student_id, fee_plan_id, billing_year, billing_month),
    KEY idx_invoice_student_period (student_id, billing_year, billing_month),
    KEY idx_invoice_due_status (due_on, status),
    CONSTRAINT fk_invoice_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
    CONSTRAINT fk_invoice_plan FOREIGN KEY (fee_plan_id) REFERENCES finance_fee_plans(id) ON DELETE RESTRICT,
    CONSTRAINT fk_invoice_creator FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
    CONSTRAINT chk_invoice_amounts CHECK (amount_due >= 0 AND discount_amount >= 0 AND discount_amount <= amount_due),
    CONSTRAINT chk_invoice_month CHECK (billing_month BETWEEN 1 AND 12)
) ENGINE=InnoDB;

CREATE TABLE finance_fee_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(60) NOT NULL UNIQUE,
    student_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    method ENUM('cash','bank_transfer','pos','online','other') NOT NULL,
    reference VARCHAR(190) NULL,
    payer_name VARCHAR(160) NULL,
    notes TEXT NULL,
    status ENUM('pending','confirmed','rejected','reversed') NOT NULL DEFAULT 'pending',
    paid_at DATETIME NOT NULL,
    recorded_by BIGINT UNSIGNED NULL,
    verified_by BIGINT UNSIGNED NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fee_payment_reference (reference),
    KEY idx_fee_payment_student (student_id, paid_at),
    KEY idx_fee_payment_status (status, paid_at),
    CONSTRAINT fk_fee_payment_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
    CONSTRAINT fk_fee_payment_recorder FOREIGN KEY (recorded_by) REFERENCES admins(id) ON DELETE SET NULL,
    CONSTRAINT fk_fee_payment_verifier FOREIGN KEY (verified_by) REFERENCES admins(id) ON DELETE SET NULL,
    CONSTRAINT chk_fee_payment_amount CHECK (amount > 0)
) ENGINE=InnoDB;

CREATE TABLE finance_fee_allocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id BIGINT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payment_invoice (payment_id, invoice_id),
    KEY idx_allocation_invoice (invoice_id),
    CONSTRAINT fk_allocation_payment FOREIGN KEY (payment_id) REFERENCES finance_fee_payments(id) ON DELETE RESTRICT,
    CONSTRAINT fk_allocation_invoice FOREIGN KEY (invoice_id) REFERENCES finance_fee_invoices(id) ON DELETE RESTRICT,
    CONSTRAINT chk_allocation_amount CHECK (amount > 0)
) ENGINE=InnoDB;

CREATE TABLE finance_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_code VARCHAR(30) NOT NULL UNIQUE,
    account_name VARCHAR(160) NOT NULL,
    account_type ENUM('asset','liability','equity','income','expense') NOT NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO finance_accounts (account_code, account_name, account_type, is_system) VALUES
('1000','Cash on hand','asset',1),
('1010','School bank account','asset',1),
('2100','Payroll payable','liability',1),
('4000','Student fee income','income',1),
('4100','Other school income','income',1),
('5000','School operating expenses','expense',1),
('5100','Teacher salary expenses','expense',1);

CREATE TABLE finance_journals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    journal_number VARCHAR(60) NOT NULL UNIQUE,
    transaction_date DATE NOT NULL,
    source_type ENUM('fee_payment','expense','payroll_accrual','payroll_payment','adjustment','opening_balance') NOT NULL,
    source_id BIGINT UNSIGNED NULL,
    memo VARCHAR(500) NOT NULL,
    status ENUM('draft','posted','reversed') NOT NULL DEFAULT 'draft',
    created_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    posted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_journal_date (transaction_date, status),
    KEY idx_journal_source (source_type, source_id),
    CONSTRAINT fk_journal_creator FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
    CONSTRAINT fk_journal_approver FOREIGN KEY (approved_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE finance_journal_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    journal_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    description VARCHAR(255) NULL,
    debit DECIMAL(14,2) NOT NULL DEFAULT 0,
    credit DECIMAL(14,2) NOT NULL DEFAULT 0,
    KEY idx_journal_line_account (account_id, journal_id),
    CONSTRAINT fk_journal_line_journal FOREIGN KEY (journal_id) REFERENCES finance_journals(id) ON DELETE RESTRICT,
    CONSTRAINT fk_journal_line_account FOREIGN KEY (account_id) REFERENCES finance_accounts(id) ON DELETE RESTRICT,
    CONSTRAINT chk_journal_line_nonnegative CHECK (debit >= 0 AND credit >= 0 AND NOT (debit > 0 AND credit > 0))
) ENGINE=InnoDB;

CREATE TABLE finance_expenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expense_number VARCHAR(60) NOT NULL UNIQUE,
    category VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    vendor_name VARCHAR(180) NULL,
    amount DECIMAL(14,2) NOT NULL,
    payment_account_id BIGINT UNSIGNED NULL,
    payment_method ENUM('cash','bank_transfer','pos','other') NOT NULL,
    reference VARCHAR(190) NULL,
    expense_date DATE NOT NULL,
    evidence_path VARCHAR(500) NULL,
    status ENUM('pending','approved','rejected','paid','void') NOT NULL DEFAULT 'pending',
    recorded_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    journal_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_expense_date_status (expense_date, status),
    CONSTRAINT fk_expense_account FOREIGN KEY (payment_account_id) REFERENCES finance_accounts(id) ON DELETE SET NULL,
    CONSTRAINT fk_expense_recorder FOREIGN KEY (recorded_by) REFERENCES admins(id) ON DELETE SET NULL,
    CONSTRAINT fk_expense_approver FOREIGN KEY (approved_by) REFERENCES admins(id) ON DELETE SET NULL,
    CONSTRAINT fk_expense_journal FOREIGN KEY (journal_id) REFERENCES finance_journals(id) ON DELETE SET NULL,
    CONSTRAINT chk_expense_amount CHECK (amount > 0)
) ENGINE=InnoDB;

CREATE TABLE finance_other_income (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    income_number VARCHAR(60) NOT NULL UNIQUE,
    category VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    payer_name VARCHAR(180) NULL,
    amount DECIMAL(14,2) NOT NULL,
    payment_method ENUM('cash','bank_transfer','pos','other') NOT NULL,
    reference VARCHAR(190) NULL,
    received_date DATE NOT NULL,
    status ENUM('pending','confirmed','rejected','reversed') NOT NULL DEFAULT 'pending',
    recorded_by BIGINT UNSIGNED NULL,
    verified_by BIGINT UNSIGNED NULL,
    verified_at DATETIME NULL,
    journal_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_other_income_date_status (received_date,status),
    CONSTRAINT fk_other_income_recorder FOREIGN KEY (recorded_by) REFERENCES admins(id) ON DELETE SET NULL,
    CONSTRAINT fk_other_income_verifier FOREIGN KEY (verified_by) REFERENCES admins(id) ON DELETE SET NULL,
    CONSTRAINT fk_other_income_journal FOREIGN KEY (journal_id) REFERENCES finance_journals(id) ON DELETE SET NULL,
    CONSTRAINT chk_other_income_amount CHECK (amount > 0)
) ENGINE=InnoDB;

CREATE TABLE staff_salary_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    base_salary DECIMAL(14,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'NGN',
    expected_arrival TIME NOT NULL,
    lateness_rate_per_minute DECIMAL(10,2) NOT NULL DEFAULT 10.00,
    effective_from DATE NOT NULL,
    effective_until DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_salary_profile_account (account_id, is_active, effective_from),
    CONSTRAINT fk_salary_profile_account FOREIGN KEY (account_id) REFERENCES admins(id) ON DELETE RESTRICT,
    CONSTRAINT fk_salary_profile_creator FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
    CONSTRAINT chk_salary_profile_values CHECK (base_salary >= 0 AND lateness_rate_per_minute >= 0)
) ENGINE=InnoDB;

CREATE TABLE staff_attendance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    expected_arrival TIME NOT NULL,
    actual_arrival TIME NULL,
    actual_departure TIME NULL,
    status ENUM('present','absent','approved_leave','excused') NOT NULL DEFAULT 'present',
    late_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    lateness_deduction DECIMAL(14,2) NOT NULL DEFAULT 0,
    notes VARCHAR(500) NULL,
    recorded_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_staff_attendance_day (account_id, attendance_date),
    KEY idx_staff_attendance_date (attendance_date, status),
    CONSTRAINT fk_staff_attendance_account FOREIGN KEY (account_id) REFERENCES admins(id) ON DELETE RESTRICT,
    CONSTRAINT fk_staff_attendance_recorder FOREIGN KEY (recorded_by) REFERENCES admins(id) ON DELETE SET NULL,
    CONSTRAINT fk_staff_attendance_approver FOREIGN KEY (approved_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE finance_payroll_periods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_year SMALLINT UNSIGNED NOT NULL,
    period_month TINYINT UNSIGNED NOT NULL,
    scheduled_at DATETIME NULL,
    status ENUM('draft','calculated','approved','processing','completed','failed','cancelled') NOT NULL DEFAULT 'draft',
    total_gross DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_deductions DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_net DECIMAL(14,2) NOT NULL DEFAULT 0,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    processed_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payroll_period (period_year, period_month),
    KEY idx_payroll_schedule (status, scheduled_at),
    CONSTRAINT fk_payroll_period_approver FOREIGN KEY (approved_by) REFERENCES admins(id) ON DELETE SET NULL,
    CONSTRAINT fk_payroll_period_creator FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
    CONSTRAINT chk_payroll_month CHECK (period_month BETWEEN 1 AND 12)
) ENGINE=InnoDB;

CREATE TABLE finance_payroll_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payroll_period_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    base_salary DECIMAL(14,2) NOT NULL,
    lateness_deductions DECIMAL(14,2) NOT NULL DEFAULT 0,
    late_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    other_deductions DECIMAL(14,2) NOT NULL DEFAULT 0,
    allowances DECIMAL(14,2) NOT NULL DEFAULT 0,
    net_salary DECIMAL(14,2) NOT NULL,
    status ENUM('pending','approved','paid','failed') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payroll_item_staff (payroll_period_id, account_id),
    CONSTRAINT fk_payroll_item_period FOREIGN KEY (payroll_period_id) REFERENCES finance_payroll_periods(id) ON DELETE RESTRICT,
    CONSTRAINT fk_payroll_item_account FOREIGN KEY (account_id) REFERENCES admins(id) ON DELETE RESTRICT,
    CONSTRAINT chk_payroll_item_values CHECK (base_salary >= 0 AND lateness_deductions >= 0 AND other_deductions >= 0 AND allowances >= 0 AND net_salary >= 0)
) ENGINE=InnoDB;

CREATE TABLE finance_payroll_deductions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payroll_item_id BIGINT UNSIGNED NOT NULL,
    staff_attendance_id BIGINT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_payroll_deduction_item (payroll_item_id),
    CONSTRAINT fk_payroll_deduction_item FOREIGN KEY (payroll_item_id) REFERENCES finance_payroll_items(id) ON DELETE RESTRICT,
    CONSTRAINT fk_payroll_deduction_attendance FOREIGN KEY (staff_attendance_id) REFERENCES staff_attendance(id) ON DELETE RESTRICT,
    CONSTRAINT chk_payroll_deduction_amount CHECK (amount > 0)
) ENGINE=InnoDB;

CREATE TABLE finance_payroll_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payroll_item_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(60) NOT NULL,
    idempotency_key VARCHAR(120) NOT NULL UNIQUE,
    provider_reference VARCHAR(190) NULL UNIQUE,
    amount DECIMAL(14,2) NOT NULL,
    status ENUM('queued','processing','successful','failed','unknown') NOT NULL DEFAULT 'queued',
    failure_reason TEXT NULL,
    initiated_at DATETIME NULL,
    confirmed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payroll_payment_item (payroll_item_id),
    CONSTRAINT fk_payroll_payment_item FOREIGN KEY (payroll_item_id) REFERENCES finance_payroll_items(id) ON DELETE RESTRICT,
    CONSTRAINT chk_payroll_payment_amount CHECK (amount > 0)
) ENGINE=InnoDB;

CREATE TABLE finance_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    details_json JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_finance_audit_entity (entity_type, entity_id, created_at),
    KEY idx_finance_audit_actor (actor_id, created_at),
    CONSTRAINT fk_finance_audit_actor FOREIGN KEY (actor_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;
