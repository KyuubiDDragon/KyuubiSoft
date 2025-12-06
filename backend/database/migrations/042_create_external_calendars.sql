-- External Calendars (iCal/CalDAV sync)
CREATE TABLE IF NOT EXISTS external_calendars (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'ical', -- ical, caldav
    url TEXT NOT NULL,
    username VARCHAR(255) NULL,
    password_encrypted TEXT NULL,
    color VARCHAR(20) DEFAULT 'blue',
    is_visible BOOLEAN DEFAULT TRUE,
    sync_interval_minutes INT DEFAULT 60,
    last_synced_at TIMESTAMP NULL,
    last_sync_error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_external_calendars_user (user_id)
);

-- External Calendar Events (cached from sync)
CREATE TABLE IF NOT EXISTS external_calendar_events (
    id CHAR(36) NOT NULL PRIMARY KEY,
    calendar_id CHAR(36) NOT NULL,
    external_uid VARCHAR(500) NOT NULL, -- UID from iCal
    title VARCHAR(255) NOT NULL,
    description TEXT,
    location VARCHAR(500),
    start_date DATETIME NOT NULL,
    end_date DATETIME,
    all_day BOOLEAN DEFAULT FALSE,
    recurrence_rule TEXT, -- RRULE from iCal
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (calendar_id) REFERENCES external_calendars(id) ON DELETE CASCADE,
    UNIQUE KEY idx_external_events_uid (calendar_id, external_uid),
    INDEX idx_external_events_date (calendar_id, start_date, end_date)
);
