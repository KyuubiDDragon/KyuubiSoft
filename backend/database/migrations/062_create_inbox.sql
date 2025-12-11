-- Quick Capture / Inbox System
-- Allows users to quickly capture thoughts and sort them later

CREATE TABLE IF NOT EXISTS inbox_items (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    content TEXT NOT NULL,
    note TEXT,
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',

    -- Where it was captured from
    source ENUM('quick_capture', 'email', 'api', 'browser_extension', 'mobile') DEFAULT 'quick_capture',
    source_url VARCHAR(500),

    -- Processing status
    status ENUM('inbox', 'processing', 'done', 'archived') DEFAULT 'inbox',
    processed_at TIMESTAMP NULL,

    -- Where it was moved to
    moved_to_type ENUM('list', 'document', 'kanban', 'project', 'calendar', 'trash') NULL,
    moved_to_id VARCHAR(36) NULL,

    -- Metadata
    tags JSON,
    attachments JSON,
    reminder_at TIMESTAMP NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_inbox_user_status (user_id, status),
    INDEX idx_inbox_user_created (user_id, created_at),
    INDEX idx_inbox_reminder (user_id, reminder_at),

    CONSTRAINT fk_inbox_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
