-- File Versioning Migration
-- Stores versions of files for version control

CREATE TABLE IF NOT EXISTS file_versions (
    id VARCHAR(36) PRIMARY KEY,
    file_id VARCHAR(36) NOT NULL,
    version_number INT NOT NULL DEFAULT 1,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    path VARCHAR(500) NOT NULL,
    size BIGINT NOT NULL DEFAULT 0,
    mime_type VARCHAR(100),
    hash VARCHAR(64) COMMENT 'SHA-256 hash for deduplication',
    created_by VARCHAR(36) NOT NULL,
    change_note TEXT,
    is_current BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_file_versions_file (file_id),
    INDEX idx_file_versions_current (file_id, is_current),
    INDEX idx_file_versions_version (file_id, version_number),
    INDEX idx_file_versions_created (created_at),

    CONSTRAINT fk_file_versions_file
        FOREIGN KEY (file_id) REFERENCES storage_files(id) ON DELETE CASCADE,
    CONSTRAINT fk_file_versions_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add versioning columns to storage_files
-- Note: These will fail silently if columns already exist (migration runs once)
ALTER TABLE storage_files ADD COLUMN is_versioned BOOLEAN DEFAULT FALSE;
ALTER TABLE storage_files ADD COLUMN current_version INT DEFAULT 1;
ALTER TABLE storage_files ADD COLUMN max_versions INT DEFAULT 10;

-- File version settings per user
CREATE TABLE IF NOT EXISTS file_version_settings (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL UNIQUE,
    auto_version BOOLEAN DEFAULT TRUE COMMENT 'Auto-create versions on update',
    max_versions_per_file INT DEFAULT 10,
    keep_days INT DEFAULT 90 COMMENT 'Days to keep old versions',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_file_version_settings_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
