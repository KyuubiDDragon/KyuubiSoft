-- Bookmark Groups
CREATE TABLE IF NOT EXISTS bookmark_groups (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    color VARCHAR(7) DEFAULT '#6366f1',
    icon VARCHAR(50) DEFAULT 'folder',
    position INT DEFAULT 0,
    is_collapsed TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_bookmark_groups_user (user_id),
    INDEX idx_bookmark_groups_position (position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add group_id to bookmarks table
ALTER TABLE bookmarks
ADD COLUMN group_id VARCHAR(36) NULL AFTER user_id,
ADD COLUMN position INT DEFAULT 0 AFTER is_favorite,
ADD INDEX idx_bookmarks_group (group_id),
ADD CONSTRAINT fk_bookmarks_group FOREIGN KEY (group_id) REFERENCES bookmark_groups(id) ON DELETE SET NULL;
