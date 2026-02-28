-- Contract Templates (reusable templates for generating contracts)
CREATE TABLE IF NOT EXISTS contract_templates (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    contract_type ENUM('license','development','saas','maintenance','nda') NOT NULL,
    language ENUM('de','en') DEFAULT 'de',
    content_html LONGTEXT NOT NULL,
    variables JSON NULL,
    is_default TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ct_user_type (user_id, contract_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contracts
CREATE TABLE IF NOT EXISTS contracts (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    client_id VARCHAR(36) NULL,
    template_id VARCHAR(36) NULL,
    contract_number VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    contract_type ENUM('license','development','saas','maintenance','nda') NOT NULL,
    language ENUM('de','en') DEFAULT 'de',
    status ENUM('draft','sent','signed','active','expired','cancelled','terminated') DEFAULT 'draft',
    content_html LONGTEXT NOT NULL,
    variables_data JSON NULL,
    -- Party A (Licensor / Service Provider / Auftragnehmer)
    party_a_name VARCHAR(255) NULL,
    party_a_company VARCHAR(255) NULL,
    party_a_address TEXT NULL,
    party_a_email VARCHAR(255) NULL,
    party_a_vat_id VARCHAR(50) NULL,
    -- Party B (Licensee / Client / Auftraggeber)
    party_b_name VARCHAR(255) NULL,
    party_b_company VARCHAR(255) NULL,
    party_b_address TEXT NULL,
    party_b_email VARCHAR(255) NULL,
    party_b_vat_id VARCHAR(50) NULL,
    -- Terms
    start_date DATE NULL,
    end_date DATE NULL,
    auto_renewal TINYINT(1) DEFAULT 0,
    renewal_period VARCHAR(20) NULL,
    notice_period_days INT DEFAULT 30,
    total_value DECIMAL(12,2) DEFAULT 0.00,
    currency VARCHAR(3) DEFAULT 'EUR',
    payment_schedule VARCHAR(50) NULL,
    -- Legal
    governing_law VARCHAR(50) DEFAULT 'DE',
    jurisdiction VARCHAR(255) NULL,
    is_b2c TINYINT(1) DEFAULT 0,
    include_nda_clause TINYINT(1) DEFAULT 1,
    -- Signatures
    party_a_signed_at DATETIME NULL,
    party_a_signature_data LONGTEXT NULL,
    party_b_signed_at DATETIME NULL,
    party_b_signature_data LONGTEXT NULL,
    -- Metadata
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (template_id) REFERENCES contract_templates(id) ON DELETE SET NULL,
    UNIQUE KEY uk_contract_number (user_id, contract_number),
    INDEX idx_contracts_user (user_id),
    INDEX idx_contracts_client (client_id),
    INDEX idx_contracts_status (status),
    INDEX idx_contracts_type (contract_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contract-Invoice linking table
CREATE TABLE IF NOT EXISTS contract_invoices (
    id VARCHAR(36) PRIMARY KEY,
    contract_id VARCHAR(36) NOT NULL,
    invoice_id VARCHAR(36) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    UNIQUE KEY uk_contract_invoice (contract_id, invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contract history / audit trail
CREATE TABLE IF NOT EXISTS contract_history (
    id VARCHAR(36) PRIMARY KEY,
    contract_id VARCHAR(36) NOT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT NULL,
    performed_by VARCHAR(36) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    INDEX idx_ch_contract (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
