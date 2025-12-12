-- Quick Access Navigation Pins
-- Allows users to pin navigation menu items for quick access in the header bar

CREATE TABLE IF NOT EXISTS user_quick_access (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    nav_id VARCHAR(50) NOT NULL COMMENT 'Navigation item identifier (e.g., dashboard, kanban, chat)',
    nav_name VARCHAR(100) NOT NULL COMMENT 'Display name of the item',
    nav_href VARCHAR(255) NOT NULL COMMENT 'Route/URL path',
    nav_icon VARCHAR(50) NOT NULL COMMENT 'Icon component name',
    position INT DEFAULT 0 COMMENT 'Order position in quick access bar',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_user_nav (user_id, nav_id),
    INDEX idx_quick_access_user (user_id, position),

    CONSTRAINT fk_quick_access_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User setting for max visible quick access icons
-- This will be stored in user_settings table with key 'quick_access_max_visible'
-- Default value: 5
