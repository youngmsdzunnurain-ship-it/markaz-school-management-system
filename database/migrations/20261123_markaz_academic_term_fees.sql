-- Academic-session and term-based class fees. Additive; preserves existing monthly billing records.
CREATE TABLE finance_academic_sessions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 session_name VARCHAR(30) NOT NULL UNIQUE,
 starts_on DATE NOT NULL,
 ends_on DATE NOT NULL,
 is_current TINYINT(1) NOT NULL DEFAULT 0,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_finance_session_creator FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
 CONSTRAINT chk_finance_session_dates CHECK (ends_on >= starts_on),
 KEY idx_finance_session_current (is_current, starts_on)
) ENGINE=InnoDB;

CREATE TABLE finance_term_fee_schedules (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 session_id BIGINT UNSIGNED NOT NULL,
 term ENUM('first','second','third') NOT NULL,
 class_id BIGINT UNSIGNED NOT NULL,
 amount DECIMAL(14,2) NOT NULL,
 due_on DATE NOT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_term_fee_class (session_id,term,class_id),
 CONSTRAINT fk_term_fee_session FOREIGN KEY (session_id) REFERENCES finance_academic_sessions(id) ON DELETE RESTRICT,
 CONSTRAINT fk_term_fee_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE RESTRICT,
 CONSTRAINT fk_term_fee_creator FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
 CONSTRAINT chk_term_fee_amount CHECK (amount >= 0)
) ENGINE=InnoDB;

CREATE TABLE finance_student_fee_adjustments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 student_id BIGINT UNSIGNED NOT NULL,
 session_id BIGINT UNSIGNED NOT NULL,
 term ENUM('first','second','third') NOT NULL,
 adjustment_type ENUM('fixed_fee','discount_amount','discount_percent','full_scholarship') NOT NULL,
 value DECIMAL(14,2) NOT NULL,
 reason VARCHAR(500) NOT NULL,
 approved_by BIGINT UNSIGNED NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 effective_from DATE NOT NULL,
 effective_until DATE NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_student_fee_adjustment (student_id,session_id,term),
 CONSTRAINT fk_fee_adjust_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
 CONSTRAINT fk_fee_adjust_session FOREIGN KEY (session_id) REFERENCES finance_academic_sessions(id) ON DELETE RESTRICT,
 CONSTRAINT fk_fee_adjust_approver FOREIGN KEY (approved_by) REFERENCES admins(id) ON DELETE RESTRICT,
 CONSTRAINT fk_fee_adjust_creator FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE RESTRICT,
 CONSTRAINT chk_fee_adjust_value CHECK (value >= 0 AND (adjustment_type <> 'discount_percent' OR value <= 100))
) ENGINE=InnoDB;

CREATE TABLE finance_term_fee_invoices (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 invoice_number VARCHAR(60) NOT NULL UNIQUE,
 student_id BIGINT UNSIGNED NOT NULL,
 session_id BIGINT UNSIGNED NOT NULL,
 term ENUM('first','second','third') NOT NULL,
 class_id BIGINT UNSIGNED NOT NULL,
 base_amount DECIMAL(14,2) NOT NULL,
 discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
 amount_due DECIMAL(14,2) NOT NULL,
 due_on DATE NOT NULL,
 status ENUM('unpaid','partial','paid','void') NOT NULL DEFAULT 'unpaid',
 generated_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_term_student_invoice (student_id,session_id,term),
 KEY idx_term_invoice_period (session_id,term,class_id,status),
 CONSTRAINT fk_term_invoice_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
 CONSTRAINT fk_term_invoice_session FOREIGN KEY (session_id) REFERENCES finance_academic_sessions(id) ON DELETE RESTRICT,
 CONSTRAINT fk_term_invoice_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE RESTRICT,
 CONSTRAINT fk_term_invoice_generator FOREIGN KEY (generated_by) REFERENCES admins(id) ON DELETE SET NULL,
 CONSTRAINT chk_term_invoice_amounts CHECK (base_amount >= 0 AND discount_amount >= 0 AND amount_due >= 0)
) ENGINE=InnoDB;

CREATE TABLE finance_term_fee_payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 term_invoice_id BIGINT UNSIGNED NOT NULL,
 payment_id BIGINT UNSIGNED NOT NULL,
 amount DECIMAL(14,2) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_term_invoice_payment (term_invoice_id,payment_id),
 CONSTRAINT fk_term_payment_invoice FOREIGN KEY (term_invoice_id) REFERENCES finance_term_fee_invoices(id) ON DELETE RESTRICT,
 CONSTRAINT fk_term_payment_payment FOREIGN KEY (payment_id) REFERENCES finance_fee_payments(id) ON DELETE RESTRICT,
 CONSTRAINT chk_term_payment_amount CHECK (amount > 0)
) ENGINE=InnoDB;
