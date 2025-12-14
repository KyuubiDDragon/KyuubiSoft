-- Migration: 073
-- Description: Create SSL Certificate Monitor tables
-- Date: 2024-12-14

-- SSL Certificate monitoring
CREATE TABLE IF NOT EXISTS ssl_certificates (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    project_id VARCHAR(36) NULL,
    folder_id VARCHAR(36) NULL,

    -- Domain information
    name VARCHAR(255) NOT NULL,
    hostname VARCHAR(255) NOT NULL,
    port INT DEFAULT 443,

    -- Certificate details (cached from last check)
    issuer VARCHAR(255) NULL,
    subject VARCHAR(255) NULL,
    serial_number VARCHAR(255) NULL,
    valid_from DATETIME NULL,
    valid_until DATETIME NULL,
    days_until_expiry INT NULL,
    fingerprint_sha256 VARCHAR(128) NULL,
    fingerprint_sha1 VARCHAR(64) NULL,

    -- Certificate chain info
    chain_valid TINYINT(1) NULL,
    chain_depth INT NULL,
    chain_info JSON NULL,

    -- SAN (Subject Alternative Names)
    san_domains JSON NULL,

    -- Status
    current_status ENUM('valid', 'expiring_soon', 'expired', 'invalid', 'error', 'pending') DEFAULT 'pending',
    last_error TEXT NULL,

    -- Monitoring settings
    is_active TINYINT(1) DEFAULT 1,
    check_interval INT DEFAULT 86400,
    warn_days_before INT DEFAULT 30,
    critical_days_before INT DEFAULT 7,

    -- Notifications
    notify_on_expiry_warning TINYINT(1) DEFAULT 1,
    notify_on_expiry_critical TINYINT(1) DEFAULT 1,
    notify_on_expired TINYINT(1) DEFAULT 1,
    notify_on_renewed TINYINT(1) DEFAULT 1,
    notify_on_chain_error TINYINT(1) DEFAULT 1,

    -- Timestamps
    last_check_at DATETIME NULL,
    next_check_at DATETIME NULL,
    last_notification_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    INDEX idx_ssl_certs_user (user_id),
    INDEX idx_ssl_certs_project (project_id),
    INDEX idx_ssl_certs_status (current_status),
    INDEX idx_ssl_certs_expiry (valid_until),
    INDEX idx_ssl_certs_days (days_until_expiry),
    INDEX idx_ssl_certs_next_check (next_check_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SSL check history
CREATE TABLE IF NOT EXISTS ssl_certificate_checks (
    id VARCHAR(36) PRIMARY KEY,
    certificate_id VARCHAR(36) NOT NULL,
    status ENUM('valid', 'expiring_soon', 'expired', 'invalid', 'error') NOT NULL,

    -- Certificate snapshot at check time
    issuer VARCHAR(255) NULL,
    subject VARCHAR(255) NULL,
    valid_from DATETIME NULL,
    valid_until DATETIME NULL,
    days_until_expiry INT NULL,

    -- Check results
    response_time_ms INT NULL,
    chain_valid TINYINT(1) NULL,
    error_message TEXT NULL,
    check_data JSON NULL,

    checked_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (certificate_id) REFERENCES ssl_certificates(id) ON DELETE CASCADE,
    INDEX idx_ssl_checks_cert (certificate_id),
    INDEX idx_ssl_checks_status (status),
    INDEX idx_ssl_checks_date (checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SSL certificate folders
CREATE TABLE IF NOT EXISTS ssl_certificate_folders (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#6366f1',
    icon VARCHAR(50) NULL,
    sort_order INT DEFAULT 0,
    is_collapsed TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ssl_folders_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add folder FK
ALTER TABLE ssl_certificates ADD FOREIGN KEY (folder_id) REFERENCES ssl_certificate_folders(id) ON DELETE SET NULL;
ALTER TABLE ssl_certificates ADD INDEX idx_ssl_certs_folder (folder_id);

-- Expiry notifications log (prevent duplicate notifications)
CREATE TABLE IF NOT EXISTS ssl_notifications (
    id VARCHAR(36) PRIMARY KEY,
    certificate_id VARCHAR(36) NOT NULL,
    notification_type ENUM('warning', 'critical', 'expired', 'renewed', 'chain_error') NOT NULL,
    days_until_expiry INT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (certificate_id) REFERENCES ssl_certificates(id) ON DELETE CASCADE,
    INDEX idx_ssl_notif_cert (certificate_id),
    INDEX idx_ssl_notif_type (notification_type),
    INDEX idx_ssl_notif_date (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
