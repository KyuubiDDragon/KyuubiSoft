-- Migration: Create shared checklists for collaborative testing
-- Version: 051

-- Shared checklists table (the main list)
CREATE TABLE IF NOT EXISTS shared_checklists (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    share_token VARCHAR(64) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    allow_anonymous TINYINT(1) NOT NULL DEFAULT 1,
    require_name TINYINT(1) NOT NULL DEFAULT 1,
    allow_add_items TINYINT(1) NOT NULL DEFAULT 0,
    allow_comments TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_shared_checklists_token (share_token),
    INDEX idx_shared_checklists_user (user_id),
    INDEX idx_shared_checklists_active (is_active),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Checklist categories/sections (for grouping items)
CREATE TABLE IF NOT EXISTS shared_checklist_categories (
    id CHAR(36) NOT NULL PRIMARY KEY,
    checklist_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_checklist_categories_list (checklist_id),
    FOREIGN KEY (checklist_id) REFERENCES shared_checklists(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Checklist items (individual test points)
CREATE TABLE IF NOT EXISTS shared_checklist_items (
    id CHAR(36) NOT NULL PRIMARY KEY,
    checklist_id CHAR(36) NOT NULL,
    category_id CHAR(36) NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    required_testers INT NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_checklist_items_list (checklist_id),
    INDEX idx_checklist_items_category (category_id),
    FOREIGN KEY (checklist_id) REFERENCES shared_checklists(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES shared_checklist_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Test entries (who tested what with what result)
CREATE TABLE IF NOT EXISTS shared_checklist_entries (
    id CHAR(36) NOT NULL PRIMARY KEY,
    item_id CHAR(36) NOT NULL,
    tester_name VARCHAR(100) NOT NULL,
    tester_email VARCHAR(255) NULL,
    status ENUM('pending', 'in_progress', 'passed', 'failed', 'blocked') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    tested_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_checklist_entries_item (item_id),
    INDEX idx_checklist_entries_status (status),
    INDEX idx_checklist_entries_tester (tester_name),
    FOREIGN KEY (item_id) REFERENCES shared_checklist_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity log for tracking all changes
CREATE TABLE IF NOT EXISTS shared_checklist_activity (
    id CHAR(36) NOT NULL PRIMARY KEY,
    checklist_id CHAR(36) NOT NULL,
    item_id CHAR(36) NULL,
    entry_id CHAR(36) NULL,
    actor_name VARCHAR(100) NOT NULL,
    action VARCHAR(50) NOT NULL,
    details JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_checklist_activity_list (checklist_id),
    INDEX idx_checklist_activity_item (item_id),
    INDEX idx_checklist_activity_created (created_at),
    FOREIGN KEY (checklist_id) REFERENCES shared_checklists(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES shared_checklist_items(id) ON DELETE SET NULL,
    FOREIGN KEY (entry_id) REFERENCES shared_checklist_entries(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add checklist permissions
INSERT INTO permissions (name, description, module) VALUES
('checklists.view', 'Checklisten anzeigen', 'checklists'),
('checklists.create', 'Checklisten erstellen', 'checklists'),
('checklists.edit', 'Checklisten bearbeiten', 'checklists'),
('checklists.delete', 'Checklisten löschen', 'checklists'),
('checklists.share', 'Checklisten freigeben', 'checklists')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Assign permissions to owner role
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'owner' AND p.module = 'checklists'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Assign permissions to admin role
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'admin' AND p.module = 'checklists'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Assign permissions to editor role
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'editor' AND p.module = 'checklists'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Assign view permission to user role
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'user' AND p.module = 'checklists' AND p.name = 'checklists.view'
ON DUPLICATE KEY UPDATE role_id = role_id;
