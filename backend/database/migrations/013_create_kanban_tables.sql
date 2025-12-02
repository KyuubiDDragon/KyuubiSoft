-- Migration: Create kanban board tables
-- Version: 013

-- Kanban boards table
CREATE TABLE IF NOT EXISTS kanban_boards (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    color VARCHAR(7) NULL DEFAULT '#6366f1',
    is_archived BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_kanban_boards_user (user_id),
    INDEX idx_kanban_boards_archived (is_archived),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kanban columns table
CREATE TABLE IF NOT EXISTS kanban_columns (
    id CHAR(36) NOT NULL PRIMARY KEY,
    board_id CHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    color VARCHAR(7) NULL DEFAULT '#3B82F6',
    position INT UNSIGNED NOT NULL DEFAULT 0,
    wip_limit INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_kanban_columns_board (board_id),
    INDEX idx_kanban_columns_position (position),
    FOREIGN KEY (board_id) REFERENCES kanban_boards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kanban cards table
CREATE TABLE IF NOT EXISTS kanban_cards (
    id CHAR(36) NOT NULL PRIMARY KEY,
    column_id CHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    color VARCHAR(7) NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    priority ENUM('low', 'medium', 'high', 'urgent') NULL DEFAULT 'medium',
    due_date DATE NULL,
    labels JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_kanban_cards_column (column_id),
    INDEX idx_kanban_cards_position (position),
    INDEX idx_kanban_cards_priority (priority),
    INDEX idx_kanban_cards_due (due_date),
    FOREIGN KEY (column_id) REFERENCES kanban_columns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Board shares table
CREATE TABLE IF NOT EXISTS kanban_board_shares (
    board_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    permission ENUM('view', 'edit') NOT NULL DEFAULT 'view',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (board_id, user_id),
    FOREIGN KEY (board_id) REFERENCES kanban_boards(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
