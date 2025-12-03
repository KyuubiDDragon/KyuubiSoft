-- Quick Notes Table
CREATE TABLE IF NOT EXISTS quick_notes (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    content TEXT,
    is_pinned BOOLEAN DEFAULT FALSE,
    color VARCHAR(20) DEFAULT 'default',
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_quick_notes_user (user_id),
    INDEX idx_quick_notes_pinned (user_id, is_pinned)
);
