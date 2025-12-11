-- Global favorites for quick access
CREATE TABLE IF NOT EXISTS favorites (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    item_type VARCHAR(50) NOT NULL,
    item_id VARCHAR(36) NOT NULL,
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_favorite_unique (user_id, item_type, item_id),
    INDEX idx_favorite_user (user_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
