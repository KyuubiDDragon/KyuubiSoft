-- Migration 097: Business Finance Features
-- Adds receipt linking for expenses, income tracking, and Kleinunternehmer support

-- Link expenses to uploaded receipts (via storage module)
-- IF NOT EXISTS makes this migration idempotent (safe to re-run)
ALTER TABLE expenses
    ADD COLUMN IF NOT EXISTS receipt_file_id VARCHAR(36) NULL AFTER notes;

-- Add index conditionally to stay idempotent
SET @dbname = DATABASE();
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'expenses' AND INDEX_NAME = 'idx_expenses_receipt_file') > 0,
    'SELECT 1',
    'ALTER TABLE `expenses` ADD INDEX `idx_expenses_receipt_file` (`receipt_file_id`)'
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add Steuernummer snapshot to invoices (Kleinunternehmer §14 UStG requirement)
ALTER TABLE invoices
    ADD COLUMN IF NOT EXISTS sender_steuernummer VARCHAR(50) NULL AFTER sender_vat_id;

-- Add logo snapshot (invoice_logo_file_id stored in user_settings, snapshotted here)
ALTER TABLE invoices
    ADD COLUMN IF NOT EXISTS sender_logo_file_id VARCHAR(36) NULL AFTER sender_steuernummer;

-- Add Leistungsdatum (§14 UStG Pflichtangabe: date goods/services were delivered)
ALTER TABLE invoices
    ADD COLUMN IF NOT EXISTS service_date DATE NULL AFTER due_date;

-- Add Zahlungsziel / payment terms text field
ALTER TABLE invoices
    ADD COLUMN IF NOT EXISTS payment_terms VARCHAR(255) NULL DEFAULT 'Zahlbar innerhalb von 30 Tagen nach Rechnungsdatum.' AFTER terms;

-- Add document type: invoice (default), proforma, quote (Angebot), credit_note (Gutschrift)
ALTER TABLE invoices
    ADD COLUMN IF NOT EXISTS document_type ENUM('invoice','proforma','quote','credit_note') NOT NULL DEFAULT 'invoice' AFTER invoice_number;

-- ─── Fahrtkosten & Bewirtungskosten support ──────────────────────────────────

-- expense_type differentiates regular / mileage / entertainment
ALTER TABLE expenses
    ADD COLUMN IF NOT EXISTS expense_type ENUM('general','mileage','entertainment') NOT NULL DEFAULT 'general' AFTER receipt_file_id;

-- Fahrtkosten: km and route description (amount auto-calculated from 0.30€/km)
ALTER TABLE expenses
    ADD COLUMN IF NOT EXISTS mileage_km DECIMAL(8,2) NULL AFTER expense_type;

ALTER TABLE expenses
    ADD COLUMN IF NOT EXISTS mileage_route VARCHAR(255) NULL AFTER mileage_km;

-- Bewirtungskosten: only 70% deductible (§4 Abs.5 Nr.2 EStG); defaults to 100 for all other types
ALTER TABLE expenses
    ADD COLUMN IF NOT EXISTS deductible_percent TINYINT UNSIGNED NOT NULL DEFAULT 100 AFTER mileage_route;

-- ─── Mahnwesen ────────────────────────────────────────────────────────────────

-- Extend document_type to include payment reminder (Mahnung)
ALTER TABLE invoices
    MODIFY COLUMN document_type ENUM('invoice','proforma','quote','credit_note','reminder') NOT NULL DEFAULT 'invoice';

-- Mahnstufe: 0 = Zahlungserinnerung, 1 = 1. Mahnung, 2 = 2. Mahnung, 3 = 3. Mahnung
ALTER TABLE invoices
    ADD COLUMN IF NOT EXISTS mahnung_level TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER payment_terms;

-- Mahngebühr added on top of outstanding amount
ALTER TABLE invoices
    ADD COLUMN IF NOT EXISTS mahnung_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER mahnung_level;

-- ─── Income categories (separate from expense categories) ────────────────────

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
