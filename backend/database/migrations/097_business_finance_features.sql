-- Migration 097: Business Finance Features
-- Adds receipt linking for expenses, income tracking, and Kleinunternehmer support

-- Link expenses to uploaded receipts (via storage module)
ALTER TABLE expenses
    ADD COLUMN receipt_file_id VARCHAR(36) NULL AFTER notes,
    ADD INDEX idx_expenses_receipt_file (receipt_file_id);

-- Income categories (separate from expense categories)
CREATE TABLE IF NOT EXISTS income_categories (
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) NOT NULL DEFAULT '#10B981',
    icon VARCHAR(50) NOT NULL DEFAULT 'banknotes',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_income_categories_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Income entries (cash payments, direct transfers, etc. not tied to a formal invoice)
CREATE TABLE IF NOT EXISTS income_entries (
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    category_id VARCHAR(36) NULL,
    invoice_id VARCHAR(36) NULL COMMENT 'Optional link to an invoice',
    amount DECIMAL(12, 2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
    description VARCHAR(255) NOT NULL,
    income_date DATE NOT NULL,
    source VARCHAR(100) NULL COMMENT 'e.g. PayPal, Überweisung, Bar',
    notes TEXT NULL,
    receipt_file_id VARCHAR(36) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_income_entries_user_id (user_id),
    INDEX idx_income_entries_category_id (category_id),
    INDEX idx_income_entries_income_date (income_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
