-- Migration: Create cloud storage tables
-- Version: 049

-- Storage files table (flat structure, no folders)
CREATE TABLE IF NOT EXISTS storage_files (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size BIGINT UNSIGNED NOT NULL,
    extension VARCHAR(20) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_storage_files_user (user_id),
    INDEX idx_storage_files_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Storage shares table (link sharing with configuration)
CREATE TABLE IF NOT EXISTS storage_shares (
    id CHAR(36) NOT NULL PRIMARY KEY,
    file_id CHAR(36) NOT NULL,
    share_token VARCHAR(64) NOT NULL,
    password_hash VARCHAR(255) NULL,
    max_downloads INT UNSIGNED NULL,
    download_count INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_storage_shares_token (share_token),
    INDEX idx_storage_shares_file (file_id),
    INDEX idx_storage_shares_active (is_active),
    FOREIGN KEY (file_id) REFERENCES storage_files(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add storage permissions
INSERT INTO permissions (name, description, module) VALUES
('storage.view', 'Cloud Storage anzeigen', 'storage'),
('storage.upload', 'Dateien hochladen', 'storage'),
('storage.download', 'Dateien herunterladen', 'storage'),
('storage.delete', 'Dateien löschen', 'storage'),
('storage.share', 'Dateien freigeben', 'storage')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Assign permissions to owner role
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'owner' AND p.module = 'storage'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Assign permissions to admin role
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'admin' AND p.module = 'storage'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Assign permissions to editor role (all except delete)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'editor' AND p.module = 'storage' AND p.name IN ('storage.view', 'storage.upload', 'storage.download', 'storage.share')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Assign view and download to user role
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'user' AND p.module = 'storage' AND p.name IN ('storage.view', 'storage.download')
ON DUPLICATE KEY UPDATE role_id = role_id;
