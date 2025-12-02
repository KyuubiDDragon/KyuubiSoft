-- Migration: Create connections tables
-- Version: 010

-- Connections table
CREATE TABLE IF NOT EXISTS connections (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    type ENUM('ssh', 'ftp', 'sftp', 'database', 'api', 'other') NOT NULL DEFAULT 'ssh',
    host VARCHAR(255) NOT NULL,
    port INT UNSIGNED NULL,
    username VARCHAR(255) NULL,
    password_encrypted TEXT NULL,
    private_key_encrypted TEXT NULL,
    extra_data JSON NULL,
    color VARCHAR(7) NULL DEFAULT '#6366f1',
    icon VARCHAR(50) NULL,
    is_favorite BOOLEAN NOT NULL DEFAULT FALSE,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_connections_user (user_id),
    INDEX idx_connections_type (type),
    INDEX idx_connections_favorite (is_favorite),
    INDEX idx_connections_last_used (last_used_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Connection groups/tags for organization
CREATE TABLE IF NOT EXISTS connection_tags (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) NULL DEFAULT '#6366f1',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_connection_tags_user (user_id),
    UNIQUE KEY unique_tag_name_per_user (user_id, name),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Connection to tag mapping
CREATE TABLE IF NOT EXISTS connection_tag_mapping (
    connection_id CHAR(36) NOT NULL,
    tag_id CHAR(36) NOT NULL,

    PRIMARY KEY (connection_id, tag_id),
    FOREIGN KEY (connection_id) REFERENCES connections(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES connection_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
