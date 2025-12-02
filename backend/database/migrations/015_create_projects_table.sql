-- Projects Hub
CREATE TABLE IF NOT EXISTS projects (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    color VARCHAR(7) NULL DEFAULT '#6366f1',
    icon VARCHAR(50) NULL DEFAULT 'folder',
    status ENUM('active', 'archived', 'completed') NOT NULL DEFAULT 'active',
    is_favorite BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_projects_user (user_id),
    INDEX idx_projects_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Project links to other resources
CREATE TABLE IF NOT EXISTS project_links (
    id CHAR(36) NOT NULL PRIMARY KEY,
    project_id CHAR(36) NOT NULL,
    linkable_type ENUM('document', 'list', 'kanban_board', 'connection', 'snippet') NOT NULL,
    linkable_id CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE KEY uk_project_links (project_id, linkable_type, linkable_id),
    INDEX idx_project_links_linkable (linkable_type, linkable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Project shares for collaboration
CREATE TABLE IF NOT EXISTS project_shares (
    project_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    permission ENUM('view', 'edit') NOT NULL DEFAULT 'view',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (project_id, user_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
