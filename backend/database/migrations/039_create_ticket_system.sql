-- Ticket System Tables

-- Ticket Categories (with parent support for nesting)
CREATE TABLE IF NOT EXISTS ticket_categories (
    id CHAR(36) PRIMARY KEY,
    parent_id CHAR(36) DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#6366f1',
    icon VARCHAR(50) DEFAULT 'ticket',
    sla_response_hours INT DEFAULT NULL,
    sla_resolution_hours INT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (parent_id) REFERENCES ticket_categories(id) ON DELETE SET NULL,
    INDEX idx_parent (parent_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tickets
CREATE TABLE IF NOT EXISTS tickets (
    id CHAR(36) PRIMARY KEY,
    ticket_number INT AUTO_INCREMENT UNIQUE,
    access_code VARCHAR(20) DEFAULT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('open', 'in_progress', 'waiting', 'resolved', 'closed') DEFAULT 'open',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    category_id CHAR(36) DEFAULT NULL,
    project_id CHAR(36) DEFAULT NULL,
    user_id CHAR(36) DEFAULT NULL,
    guest_name VARCHAR(100) DEFAULT NULL,
    guest_email VARCHAR(255) DEFAULT NULL,
    assigned_to CHAR(36) DEFAULT NULL,
    assigned_group_id INT DEFAULT NULL,
    due_date DATETIME DEFAULT NULL,
    first_response_at DATETIME DEFAULT NULL,
    resolved_at DATETIME DEFAULT NULL,
    closed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (category_id) REFERENCES ticket_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_user (user_id),
    INDEX idx_assigned (assigned_to),
    INDEX idx_category (category_id),
    INDEX idx_project (project_id),
    INDEX idx_access_code (access_code),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket Comments
CREATE TABLE IF NOT EXISTS ticket_comments (
    id CHAR(36) PRIMARY KEY,
    ticket_id CHAR(36) NOT NULL,
    user_id CHAR(36) DEFAULT NULL,
    guest_name VARCHAR(100) DEFAULT NULL,
    content TEXT NOT NULL,
    is_internal TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_ticket (ticket_id),
    INDEX idx_internal (is_internal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket Attachments
CREATE TABLE IF NOT EXISTS ticket_attachments (
    id CHAR(36) PRIMARY KEY,
    ticket_id CHAR(36) NOT NULL,
    comment_id CHAR(36) DEFAULT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    path VARCHAR(500) NOT NULL,
    size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    uploaded_by CHAR(36) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES ticket_comments(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket Group Permissions
CREATE TABLE IF NOT EXISTS ticket_group_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    category_id CHAR(36) DEFAULT NULL,
    can_view TINYINT(1) DEFAULT 1,
    can_create TINYINT(1) DEFAULT 1,
    can_comment TINYINT(1) DEFAULT 1,
    can_assign TINYINT(1) DEFAULT 0,
    can_close TINYINT(1) DEFAULT 0,
    can_delete TINYINT(1) DEFAULT 0,
    can_view_internal TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (category_id) REFERENCES ticket_categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_category (group_id, category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket Status History
CREATE TABLE IF NOT EXISTS ticket_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id CHAR(36) NOT NULL,
    old_status VARCHAR(20),
    new_status VARCHAR(20) NOT NULL,
    changed_by CHAR(36) DEFAULT NULL,
    comment TEXT,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default categories
INSERT INTO ticket_categories (id, name, description, color, icon, sort_order, created_at, updated_at) VALUES
(UUID(), 'Allgemein', 'Allgemeine Anfragen', '#6366f1', 'chat-bubble-left', 0, NOW(), NOW()),
(UUID(), 'Technischer Support', 'Technische Probleme und Fragen', '#ef4444', 'wrench-screwdriver', 1, NOW(), NOW()),
(UUID(), 'Abrechnung', 'Fragen zu Rechnungen und Zahlungen', '#22c55e', 'currency-euro', 2, NOW(), NOW()),
(UUID(), 'Feature-Anfrage', 'Vorschläge für neue Funktionen', '#f59e0b', 'light-bulb', 3, NOW(), NOW());
