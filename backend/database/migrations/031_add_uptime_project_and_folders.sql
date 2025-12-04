-- Add project assignment and folder organization to uptime monitors

-- Add project_id to uptime_monitors
ALTER TABLE uptime_monitors
    ADD COLUMN project_id VARCHAR(36) NULL AFTER user_id,
    ADD COLUMN folder_id VARCHAR(36) NULL AFTER project_id,
    ADD INDEX idx_monitors_project (project_id),
    ADD INDEX idx_monitors_folder (folder_id),
    ADD CONSTRAINT fk_monitors_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;

-- Create uptime monitor folders table
CREATE TABLE IF NOT EXISTS uptime_folders (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#6366F1',
    icon VARCHAR(50) DEFAULT 'folder',
    position INT DEFAULT 0,
    is_collapsed TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_folders_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add folder foreign key after table exists
ALTER TABLE uptime_monitors
    ADD CONSTRAINT fk_monitors_folder FOREIGN KEY (folder_id) REFERENCES uptime_folders(id) ON DELETE SET NULL;
