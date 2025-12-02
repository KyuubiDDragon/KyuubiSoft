-- Time Tracking
CREATE TABLE IF NOT EXISTS time_entries (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    project_id CHAR(36) NULL,
    task_name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    duration_seconds INT UNSIGNED NULL COMMENT 'Calculated or manual duration',
    is_running BOOLEAN NOT NULL DEFAULT FALSE,
    is_billable BOOLEAN NOT NULL DEFAULT FALSE,
    hourly_rate DECIMAL(10,2) NULL,
    tags JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    INDEX idx_time_entries_user (user_id),
    INDEX idx_time_entries_project (project_id),
    INDEX idx_time_entries_running (user_id, is_running),
    INDEX idx_time_entries_dates (user_id, started_at, ended_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
