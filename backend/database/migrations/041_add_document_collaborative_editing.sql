-- Add collaborative editing support to documents
ALTER TABLE documents
ADD COLUMN public_can_edit TINYINT(1) DEFAULT 0 AFTER public_view_count,
ADD COLUMN content_version INT DEFAULT 1 AFTER public_can_edit,
ADD COLUMN last_edited_by CHAR(36) DEFAULT NULL AFTER content_version,
ADD COLUMN last_edited_at DATETIME DEFAULT NULL AFTER last_edited_by;

-- Create document edit sessions table for tracking active editors
CREATE TABLE IF NOT EXISTS document_edit_sessions (
    id CHAR(36) PRIMARY KEY,
    document_id CHAR(36) NOT NULL,
    session_token VARCHAR(64) NOT NULL,
    editor_name VARCHAR(255) DEFAULT 'Anonym',
    is_owner TINYINT(1) DEFAULT 0,
    last_activity DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_document (document_id),
    INDEX idx_session_token (session_token),
    INDEX idx_last_activity (last_activity),
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create document change log for sync
CREATE TABLE IF NOT EXISTS document_changes (
    id CHAR(36) PRIMARY KEY,
    document_id CHAR(36) NOT NULL,
    session_token VARCHAR(64) NOT NULL,
    change_type ENUM('content', 'cursor', 'selection') DEFAULT 'content',
    change_data LONGTEXT NOT NULL,
    version INT NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_document (document_id),
    INDEX idx_version (document_id, version),
    INDEX idx_created (created_at),
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
