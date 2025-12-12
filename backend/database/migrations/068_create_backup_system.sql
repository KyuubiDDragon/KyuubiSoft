-- Backup System Migration
-- Automated backup and recovery for self-hosted instances

-- Backup storage targets (S3, SFTP, Local, WebDAV)
CREATE TABLE IF NOT EXISTS backup_storage_targets (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('local', 's3', 'sftp', 'webdav') NOT NULL,
    config JSON NOT NULL COMMENT 'Encrypted credentials and settings',
    is_default BOOLEAN DEFAULT FALSE,
    is_enabled BOOLEAN DEFAULT TRUE,
    last_test_at TIMESTAMP NULL,
    last_test_status ENUM('success', 'failed') NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_backup_targets_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backup schedules
CREATE TABLE IF NOT EXISTS backup_schedules (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('full', 'database', 'files') NOT NULL DEFAULT 'full',
    target_id VARCHAR(36) NOT NULL,
    cron_expression VARCHAR(100) NOT NULL DEFAULT '0 3 * * *' COMMENT 'Default: 3 AM daily',
    retention_days INT DEFAULT 30,
    retention_count INT DEFAULT 10 COMMENT 'Max backups to keep',
    is_enabled BOOLEAN DEFAULT TRUE,
    include_uploads BOOLEAN DEFAULT TRUE,
    include_logs BOOLEAN DEFAULT FALSE,
    compression ENUM('none', 'gzip', 'zip') DEFAULT 'gzip',
    last_run_at TIMESTAMP NULL,
    next_run_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_id) REFERENCES backup_storage_targets(id) ON DELETE CASCADE,
    INDEX idx_backup_schedules_user (user_id),
    INDEX idx_backup_schedules_next_run (next_run_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backup records
CREATE TABLE IF NOT EXISTS backups (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    schedule_id VARCHAR(36) NULL COMMENT 'NULL if manual backup',
    target_id VARCHAR(36) NOT NULL,
    type ENUM('full', 'database', 'files') NOT NULL,
    status ENUM('pending', 'running', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    file_path TEXT COMMENT 'Path on storage target',
    file_name VARCHAR(255),
    file_size BIGINT COMMENT 'Size in bytes',
    checksum VARCHAR(64) COMMENT 'SHA256 hash',
    compression ENUM('none', 'gzip', 'zip') DEFAULT 'gzip',
    tables_included JSON COMMENT 'List of database tables',
    files_included INT DEFAULT 0 COMMENT 'Number of files',
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    duration_seconds INT,
    error_message TEXT,
    metadata JSON COMMENT 'Additional info like DB version, app version',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES backup_schedules(id) ON DELETE SET NULL,
    FOREIGN KEY (target_id) REFERENCES backup_storage_targets(id) ON DELETE CASCADE,
    INDEX idx_backups_user (user_id),
    INDEX idx_backups_status (status),
    INDEX idx_backups_created (created_at),
    INDEX idx_backups_schedule (schedule_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Restore operations log
CREATE TABLE IF NOT EXISTS backup_restores (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    backup_id VARCHAR(36) NOT NULL,
    status ENUM('pending', 'running', 'completed', 'failed') DEFAULT 'pending',
    restore_type ENUM('full', 'database', 'files', 'selective') NOT NULL,
    tables_restored JSON COMMENT 'For selective restore',
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    duration_seconds INT,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (backup_id) REFERENCES backups(id) ON DELETE CASCADE,
    INDEX idx_restores_user (user_id),
    INDEX idx_restores_backup (backup_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add backup permissions
INSERT IGNORE INTO permissions (id, name, description, module, created_at) VALUES
(UUID(), 'backups.view', 'View backups', 'backups', NOW()),
(UUID(), 'backups.create', 'Create backups', 'backups', NOW()),
(UUID(), 'backups.delete', 'Delete backups', 'backups', NOW()),
(UUID(), 'backups.restore', 'Restore from backup', 'backups', NOW()),
(UUID(), 'backups.manage_schedules', 'Manage backup schedules', 'backups', NOW()),
(UUID(), 'backups.manage_targets', 'Manage storage targets', 'backups', NOW());

-- Grant backup permissions to owner and admin
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name IN ('owner', 'admin') AND p.module = 'backups';
