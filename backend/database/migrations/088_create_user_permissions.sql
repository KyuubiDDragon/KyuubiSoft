-- Migration: Create user_permissions table for direct user permissions
-- Version: 088

-- Direct user permissions (in addition to role-based permissions)
CREATE TABLE IF NOT EXISTS user_permissions (
    user_id CHAR(36) NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    granted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    granted_by CHAR(36) NULL,
    PRIMARY KEY (user_id, permission_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index for faster lookups
CREATE INDEX idx_user_permissions_user ON user_permissions(user_id);
