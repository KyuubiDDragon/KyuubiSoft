-- Migration: Create sharing tables for documents
-- Version: 011

-- Document shares table (similar to list_shares)
CREATE TABLE IF NOT EXISTS document_shares (
    document_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    permission ENUM('view', 'edit') NOT NULL DEFAULT 'view',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (document_id, user_id),
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add shared_with_count to documents for quick access
-- This is denormalized for performance
ALTER TABLE documents ADD COLUMN shared_count INT UNSIGNED NOT NULL DEFAULT 0;
