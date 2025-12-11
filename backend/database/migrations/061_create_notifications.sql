-- Notification System Migration
-- Supports multiple notification channels (in-app, email, webhook, etc.)

-- User notifications (in-app notifications)
CREATE TABLE IF NOT EXISTS notifications (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    type VARCHAR(50) NOT NULL COMMENT 'task_due, mention, share, system, etc.',
    title VARCHAR(255) NOT NULL,
    message TEXT,
    data JSON COMMENT 'Additional data like entity_id, entity_type, etc.',
    link VARCHAR(500) COMMENT 'Link to related item',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,

    INDEX idx_notifications_user (user_id),
    INDEX idx_notifications_unread (user_id, is_read),
    INDEX idx_notifications_type (user_id, type),
    INDEX idx_notifications_created (created_at),

    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification channels per user
CREATE TABLE IF NOT EXISTS notification_channels (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    channel_type ENUM('in_app', 'email', 'webhook', 'slack', 'telegram') NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    config JSON COMMENT 'Channel-specific config (webhook_url, etc.)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_user_channel (user_id, channel_type),

    CONSTRAINT fk_notification_channels_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification preferences per type
CREATE TABLE IF NOT EXISTS notification_preferences (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    channels JSON COMMENT 'Array of enabled channels for this type',
    is_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_user_notification_type (user_id, notification_type),

    CONSTRAINT fk_notification_preferences_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification delivery log
CREATE TABLE IF NOT EXISTS notification_deliveries (
    id VARCHAR(36) PRIMARY KEY,
    notification_id VARCHAR(36),
    user_id VARCHAR(36) NOT NULL,
    channel_type VARCHAR(20) NOT NULL,
    status ENUM('pending', 'sent', 'failed', 'skipped') DEFAULT 'pending',
    error_message TEXT,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_notification_deliveries_notification (notification_id),
    INDEX idx_notification_deliveries_user (user_id),
    INDEX idx_notification_deliveries_status (status),

    CONSTRAINT fk_notification_deliveries_notification
        FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_deliveries_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default notification channels for existing users
INSERT INTO notification_channels (id, user_id, channel_type, is_enabled, config)
SELECT UUID(), id, 'in_app', TRUE, '{}'
FROM users
WHERE id NOT IN (SELECT user_id FROM notification_channels WHERE channel_type = 'in_app');
