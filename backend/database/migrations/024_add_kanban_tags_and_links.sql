-- Migration: Add tags and document links to kanban cards
-- Version: 024

-- Kanban tags (board-specific)
CREATE TABLE IF NOT EXISTS kanban_tags (
    id CHAR(36) NOT NULL PRIMARY KEY,
    board_id CHAR(36) NOT NULL,
    name VARCHAR(50) NOT NULL,
    color VARCHAR(7) NOT NULL DEFAULT '#6B7280',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_kanban_tags_board (board_id),
    UNIQUE KEY uk_kanban_tags_board_name (board_id, name),
    FOREIGN KEY (board_id) REFERENCES kanban_boards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Card-Tag junction table
CREATE TABLE IF NOT EXISTS kanban_card_tags (
    card_id CHAR(36) NOT NULL,
    tag_id CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (card_id, tag_id),
    FOREIGN KEY (card_id) REFERENCES kanban_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES kanban_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Card links (to documents, lists, etc.)
CREATE TABLE IF NOT EXISTS kanban_card_links (
    id CHAR(36) NOT NULL PRIMARY KEY,
    card_id CHAR(36) NOT NULL,
    linkable_type VARCHAR(50) NOT NULL,
    linkable_id CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_kanban_card_links_card (card_id),
    INDEX idx_kanban_card_links_linkable (linkable_type, linkable_id),
    UNIQUE KEY uk_kanban_card_link (card_id, linkable_type, linkable_id),
    FOREIGN KEY (card_id) REFERENCES kanban_cards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
