-- Add public sharing to documents
ALTER TABLE documents
ADD COLUMN is_public TINYINT(1) DEFAULT 0 AFTER is_archived,
ADD COLUMN public_token VARCHAR(64) DEFAULT NULL AFTER is_public,
ADD COLUMN public_password VARCHAR(255) DEFAULT NULL AFTER public_token,
ADD COLUMN public_expires_at DATETIME DEFAULT NULL AFTER public_password,
ADD COLUMN public_view_count INT DEFAULT 0 AFTER public_expires_at;

CREATE UNIQUE INDEX idx_documents_public_token ON documents(public_token);

-- Create SSH command presets table
CREATE TABLE IF NOT EXISTS connection_command_presets (
    id CHAR(36) PRIMARY KEY,
    connection_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    command TEXT NOT NULL,
    description TEXT DEFAULT NULL,
    is_dangerous TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_connection (connection_id),
    FOREIGN KEY (connection_id) REFERENCES connections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create SSH session logs table
CREATE TABLE IF NOT EXISTS connection_ssh_logs (
    id CHAR(36) PRIMARY KEY,
    connection_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    command TEXT NOT NULL,
    output LONGTEXT DEFAULT NULL,
    exit_code INT DEFAULT NULL,
    executed_at DATETIME NOT NULL,
    INDEX idx_connection (connection_id),
    INDEX idx_user (user_id),
    INDEX idx_executed (executed_at),
    FOREIGN KEY (connection_id) REFERENCES connections(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
