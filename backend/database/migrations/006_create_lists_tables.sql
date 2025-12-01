-- Migration: Create lists tables
-- Version: 006

-- Lists table
CREATE TABLE IF NOT EXISTS lists (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    type ENUM('todo', 'shopping', 'project', 'custom') NOT NULL DEFAULT 'todo',
    color VARCHAR(7) NULL DEFAULT '#3B82F6',
    icon VARCHAR(50) NULL,
    is_archived BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_lists_user (user_id),
    INDEX idx_lists_type (type),
    INDEX idx_lists_archived (is_archived),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- List items table
CREATE TABLE IF NOT EXISTS list_items (
    id CHAR(36) NOT NULL PRIMARY KEY,
    list_id CHAR(36) NOT NULL,
    content TEXT NOT NULL,
    is_completed BOOLEAN NOT NULL DEFAULT FALSE,
    position INT NOT NULL DEFAULT 0,
    due_date DATE NULL,
    priority ENUM('low', 'medium', 'high') NULL DEFAULT 'medium',
    metadata JSON NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_list_items_list (list_id),
    INDEX idx_list_items_completed (is_completed),
    INDEX idx_list_items_position (position),
    INDEX idx_list_items_due (due_date),
    FOREIGN KEY (list_id) REFERENCES lists(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- List shares table
CREATE TABLE IF NOT EXISTS list_shares (
    list_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    permission ENUM('view', 'edit') NOT NULL DEFAULT 'view',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (list_id, user_id),
    FOREIGN KEY (list_id) REFERENCES lists(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
