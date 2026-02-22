-- Database Browser Query History
CREATE TABLE IF NOT EXISTS db_query_history (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    connection_id VARCHAR(36) NOT NULL,
    database_name VARCHAR(255) NULL,
    query TEXT NOT NULL,
    duration_ms INT NULL,
    rows_returned INT NULL,
    error TEXT NULL,
    executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_db_query_history_user (user_id),
    INDEX idx_db_query_history_connection (connection_id),
    INDEX idx_db_query_history_executed (executed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
