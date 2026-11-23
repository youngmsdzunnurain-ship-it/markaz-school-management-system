-- Auditable, maker-checker student fee refunds with reversible invoice allocations.
CREATE TABLE finance_fee_refunds (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    refund_number VARCHAR(40) NOT NULL,
    payment_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reason VARCHAR(500) NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    recorded_by BIGINT UNSIGNED NOT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    journal_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_finance_fee_refund_number (refund_number),
    KEY idx_finance_fee_refund_payment (payment_id,status),
    KEY idx_finance_fee_refund_review (status,created_at),
    CONSTRAINT fk_finance_fee_refund_payment FOREIGN KEY (payment_id) REFERENCES finance_fee_payments(id),
    CONSTRAINT fk_finance_fee_refund_recorded_by FOREIGN KEY (recorded_by) REFERENCES admins(id),
    CONSTRAINT fk_finance_fee_refund_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES admins(id),
    CONSTRAINT chk_finance_fee_refund_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE finance_fee_refund_allocations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    refund_id BIGINT UNSIGNED NOT NULL,
    allocation_type ENUM('monthly','term') NOT NULL,
    source_allocation_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fee_refund_source (refund_id,allocation_type,source_allocation_id),
    KEY idx_fee_refund_source_lookup (allocation_type,source_allocation_id),
    CONSTRAINT fk_fee_refund_allocation_refund FOREIGN KEY (refund_id) REFERENCES finance_fee_refunds(id),
    CONSTRAINT chk_fee_refund_allocation_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
