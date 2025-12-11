-- Password Manager - Secure credential storage
CREATE TABLE IF NOT EXISTS password_categories (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(50) DEFAULT 'folder',
    color VARCHAR(7) DEFAULT '#6366f1',
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pwd_cat_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS passwords (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    category_id VARCHAR(36) NULL,
    name VARCHAR(255) NOT NULL,
    username VARCHAR(255) NULL,
    -- Encrypted fields (AES-256-GCM)
    password_encrypted TEXT NOT NULL,
    password_iv VARCHAR(32) NOT NULL,
    password_tag VARCHAR(32) NOT NULL,
    url VARCHAR(500) NULL,
    notes_encrypted TEXT NULL,
    notes_iv VARCHAR(32) NULL,
    notes_tag VARCHAR(32) NULL,
    -- TOTP support
    totp_secret_encrypted VARCHAR(500) NULL,
    totp_iv VARCHAR(32) NULL,
    totp_tag VARCHAR(32) NULL,
    -- Metadata
    favicon_url VARCHAR(500) NULL,
    last_used_at TIMESTAMP NULL,
    password_changed_at TIMESTAMP NULL,
    is_favorite BOOLEAN DEFAULT FALSE,
    is_archived BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES password_categories(id) ON DELETE SET NULL,
    INDEX idx_pwd_user (user_id),
    INDEX idx_pwd_category (category_id),
    INDEX idx_pwd_favorite (user_id, is_favorite),
    INDEX idx_pwd_archived (user_id, is_archived),
    FULLTEXT INDEX idx_pwd_search (name, username, url)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password sharing
CREATE TABLE IF NOT EXISTS password_shares (
    id VARCHAR(36) PRIMARY KEY,
    password_id VARCHAR(36) NOT NULL,
    shared_by VARCHAR(36) NOT NULL,
    shared_with VARCHAR(36) NOT NULL,
    can_edit BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (password_id) REFERENCES passwords(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_with) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_pwd_share_unique (password_id, shared_with)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password history for breach detection
CREATE TABLE IF NOT EXISTS password_history (
    id VARCHAR(36) PRIMARY KEY,
    password_id VARCHAR(36) NOT NULL,
    password_hash VARCHAR(64) NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (password_id) REFERENCES passwords(id) ON DELETE CASCADE,
    INDEX idx_pwd_hist (password_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
