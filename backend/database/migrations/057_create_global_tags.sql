-- Global Tags System
-- Allows users to create and manage tags that can be applied across different modules

CREATE TABLE IF NOT EXISTS tags (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#6366f1',
    description TEXT,
    icon VARCHAR(50),
    usage_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_tag (user_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Junction table for tagging items across modules
CREATE TABLE IF NOT EXISTS taggables (
    id VARCHAR(36) PRIMARY KEY,
    tag_id VARCHAR(36) NOT NULL,
    taggable_type VARCHAR(50) NOT NULL,
    taggable_id VARCHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    UNIQUE KEY unique_tag_item (tag_id, taggable_type, taggable_id),
    INDEX idx_taggable (taggable_type, taggable_id),
    INDEX idx_tag (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Supported taggable_type values:
-- list, document, snippet, bookmark, connection, password, checklist, kanban_board, kanban_card, project, invoice, calendar_event
