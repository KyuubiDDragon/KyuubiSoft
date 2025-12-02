-- Uptime monitors
CREATE TABLE IF NOT EXISTS uptime_monitors (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    url TEXT NOT NULL,
    type ENUM('http', 'https', 'ping', 'port') DEFAULT 'https',
    check_interval INT DEFAULT 300,  -- seconds (default 5 min)
    timeout INT DEFAULT 30,  -- seconds
    expected_status_code INT DEFAULT 200,
    expected_keyword VARCHAR(255) NULL,
    notify_on_down TINYINT(1) DEFAULT 1,
    notify_on_recovery TINYINT(1) DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    is_paused TINYINT(1) DEFAULT 0,
    current_status ENUM('up', 'down', 'pending') DEFAULT 'pending',
    last_check_at DATETIME NULL,
    last_up_at DATETIME NULL,
    last_down_at DATETIME NULL,
    uptime_percentage DECIMAL(5,2) DEFAULT 100.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_monitors_user (user_id),
    INDEX idx_monitors_status (current_status),
    INDEX idx_monitors_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Uptime check history
CREATE TABLE IF NOT EXISTS uptime_checks (
    id VARCHAR(36) PRIMARY KEY,
    monitor_id VARCHAR(36) NOT NULL,
    status ENUM('up', 'down') NOT NULL,
    response_time INT NULL,  -- milliseconds
    status_code INT NULL,
    error_message TEXT NULL,
    checked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (monitor_id) REFERENCES uptime_monitors(id) ON DELETE CASCADE,
    INDEX idx_checks_monitor (monitor_id),
    INDEX idx_checks_checked_at (checked_at),
    INDEX idx_checks_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Uptime incidents
CREATE TABLE IF NOT EXISTS uptime_incidents (
    id VARCHAR(36) PRIMARY KEY,
    monitor_id VARCHAR(36) NOT NULL,
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    duration_seconds INT NULL,
    cause TEXT NULL,
    is_resolved TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (monitor_id) REFERENCES uptime_monitors(id) ON DELETE CASCADE,
    INDEX idx_incidents_monitor (monitor_id),
    INDEX idx_incidents_resolved (is_resolved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
