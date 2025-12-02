-- Clients (for invoicing)
CREATE TABLE IF NOT EXISTS clients (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    company VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    address_line1 VARCHAR(255) NULL,
    address_line2 VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    country VARCHAR(100) DEFAULT 'Deutschland',
    vat_id VARCHAR(50) NULL,
    notes TEXT NULL,
    default_hourly_rate DECIMAL(10,2) NULL,
    color VARCHAR(7) DEFAULT '#6366f1',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_clients_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoices
CREATE TABLE IF NOT EXISTS invoices (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    client_id VARCHAR(36) NULL,
    project_id VARCHAR(36) NULL,
    invoice_number VARCHAR(50) NOT NULL,
    status ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',
    issue_date DATE NOT NULL,
    due_date DATE NULL,
    paid_date DATE NULL,
    subtotal DECIMAL(12,2) DEFAULT 0.00,
    tax_rate DECIMAL(5,2) DEFAULT 19.00,
    tax_amount DECIMAL(12,2) DEFAULT 0.00,
    total DECIMAL(12,2) DEFAULT 0.00,
    currency VARCHAR(3) DEFAULT 'EUR',
    notes TEXT NULL,
    terms TEXT NULL,
    -- Sender info (snapshot at invoice time)
    sender_name VARCHAR(255) NULL,
    sender_company VARCHAR(255) NULL,
    sender_address TEXT NULL,
    sender_email VARCHAR(255) NULL,
    sender_phone VARCHAR(50) NULL,
    sender_vat_id VARCHAR(50) NULL,
    sender_bank_details TEXT NULL,
    -- Client info (snapshot)
    client_name VARCHAR(255) NULL,
    client_company VARCHAR(255) NULL,
    client_address TEXT NULL,
    client_email VARCHAR(255) NULL,
    client_vat_id VARCHAR(50) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    UNIQUE KEY uk_invoice_number (user_id, invoice_number),
    INDEX idx_invoices_user (user_id),
    INDEX idx_invoices_client (client_id),
    INDEX idx_invoices_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoice line items
CREATE TABLE IF NOT EXISTS invoice_items (
    id VARCHAR(36) PRIMARY KEY,
    invoice_id VARCHAR(36) NOT NULL,
    time_entry_id VARCHAR(36) NULL,
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1.00,
    unit VARCHAR(20) DEFAULT 'Stunde',
    unit_price DECIMAL(10,2) NOT NULL,
    total DECIMAL(12,2) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (time_entry_id) REFERENCES time_entries(id) ON DELETE SET NULL,
    INDEX idx_invoice_items_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link time entries to clients
ALTER TABLE time_entries ADD COLUMN client_id VARCHAR(36) NULL AFTER project_id;
ALTER TABLE time_entries ADD COLUMN invoiced TINYINT(1) DEFAULT 0 AFTER is_billable;
ALTER TABLE time_entries ADD COLUMN invoice_id VARCHAR(36) NULL AFTER invoiced;
