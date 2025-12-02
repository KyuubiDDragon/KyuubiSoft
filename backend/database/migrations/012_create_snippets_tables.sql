-- Migration: Create snippets tables
-- Version: 012

-- Snippets table
CREATE TABLE IF NOT EXISTS snippets (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    content TEXT NOT NULL,
    language VARCHAR(50) NULL DEFAULT 'text',
    category VARCHAR(100) NULL,
    tags JSON NULL,
    is_favorite BOOLEAN NOT NULL DEFAULT FALSE,
    use_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_snippets_user (user_id),
    INDEX idx_snippets_language (language),
    INDEX idx_snippets_category (category),
    INDEX idx_snippets_favorite (is_favorite),
    FULLTEXT INDEX idx_snippets_search (title, description, content),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
