-- Dashboard Widgets Configuration
CREATE TABLE IF NOT EXISTS dashboard_widgets (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    widget_type VARCHAR(50) NOT NULL,
    title VARCHAR(100),
    position_x INT DEFAULT 0,
    position_y INT DEFAULT 0,
    width INT DEFAULT 1,
    height INT DEFAULT 1,
    config JSON,
    is_visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_dashboard_widgets_user (user_id)
);

-- Calendar Events Table (for custom calendar entries)
CREATE TABLE IF NOT EXISTS calendar_events (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATETIME NOT NULL,
    end_date DATETIME,
    all_day BOOLEAN DEFAULT FALSE,
    color VARCHAR(20) DEFAULT 'primary',
    reminder_minutes INT,
    recurrence VARCHAR(20),
    source_type VARCHAR(50),
    source_id CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_calendar_events_user (user_id),
    INDEX idx_calendar_events_date (user_id, start_date, end_date),
    INDEX idx_calendar_events_source (source_type, source_id)
);
