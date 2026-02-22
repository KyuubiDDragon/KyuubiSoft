-- Terminal Sessions for WebSocket SSH PTY
CREATE TABLE IF NOT EXISTS terminal_sessions (
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    user_id INT NOT NULL,
    connection_id VARCHAR(36) NOT NULL,
    status ENUM('pending', 'active', 'closed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_terminal_sessions_user (user_id),
    INDEX idx_terminal_sessions_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
