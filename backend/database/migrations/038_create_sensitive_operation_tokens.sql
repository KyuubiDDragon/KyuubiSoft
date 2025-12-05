-- Create table for sensitive operation tokens (2FA verification for SSH, etc.)
CREATE TABLE IF NOT EXISTS sensitive_operation_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    token VARCHAR(64) NOT NULL,
    operation VARCHAR(50) NOT NULL DEFAULT 'sensitive',
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_user_operation (user_id, operation),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
