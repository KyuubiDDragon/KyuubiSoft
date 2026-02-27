CREATE TABLE IF NOT EXISTS status_page_config (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT 'System Status',
    description TEXT DEFAULT NULL,
    is_public BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS status_page_monitors (
    id CHAR(36) PRIMARY KEY,
    config_id CHAR(36) NOT NULL,
    monitor_id CHAR(36) NOT NULL,
    display_name VARCHAR(255) DEFAULT NULL,
    display_order INT DEFAULT 0,
    group_name VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (config_id) REFERENCES status_page_config(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS status_page_incidents (
    id CHAR(36) PRIMARY KEY,
    config_id CHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'investigating',
    message TEXT DEFAULT NULL,
    impact VARCHAR(50) NOT NULL DEFAULT 'minor',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (config_id) REFERENCES status_page_config(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS status_page_incident_updates (
    id CHAR(36) PRIMARY KEY,
    incident_id CHAR(36) NOT NULL,
    status VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES status_page_incidents(id) ON DELETE CASCADE
);
