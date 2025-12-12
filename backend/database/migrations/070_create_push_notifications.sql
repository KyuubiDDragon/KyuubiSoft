-- Web Push Notifications Migration
-- Browser push notifications for real-time alerts

-- Push notification subscriptions (browser endpoints)
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    endpoint TEXT NOT NULL COMMENT 'Push service endpoint URL',
    p256dh_key TEXT NOT NULL COMMENT 'Client public key',
    auth_key TEXT NOT NULL COMMENT 'Auth secret',
    user_agent VARCHAR(255),
    device_name VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_push_sub_user (user_id),
    INDEX idx_push_sub_endpoint (endpoint(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification preferences per user
CREATE TABLE IF NOT EXISTS notification_preferences (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL UNIQUE,

    -- Global settings
    push_enabled BOOLEAN DEFAULT TRUE,
    email_enabled BOOLEAN DEFAULT TRUE,
    quiet_hours_start TIME NULL COMMENT 'Start of quiet hours (no notifications)',
    quiet_hours_end TIME NULL,

    -- Module-specific push settings
    notify_tasks BOOLEAN DEFAULT TRUE,
    notify_calendar BOOLEAN DEFAULT TRUE,
    notify_tickets BOOLEAN DEFAULT TRUE,
    notify_uptime BOOLEAN DEFAULT TRUE,
    notify_chat BOOLEAN DEFAULT TRUE,
    notify_inbox BOOLEAN DEFAULT TRUE,
    notify_recurring BOOLEAN DEFAULT TRUE,
    notify_backups BOOLEAN DEFAULT TRUE,
    notify_system BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification log (for history and retry)
CREATE TABLE IF NOT EXISTS notification_log (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    subscription_id VARCHAR(36) NULL,
    type ENUM('push', 'email', 'in_app') NOT NULL,
    category VARCHAR(50) NOT NULL COMMENT 'tasks, calendar, uptime, etc.',
    title VARCHAR(255) NOT NULL,
    body TEXT,
    icon VARCHAR(255),
    url VARCHAR(500) COMMENT 'Click action URL',
    data JSON COMMENT 'Additional payload data',
    status ENUM('pending', 'sent', 'delivered', 'failed', 'clicked') DEFAULT 'pending',
    error_message TEXT,
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    clicked_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES push_subscriptions(id) ON DELETE SET NULL,
    INDEX idx_notif_log_user (user_id),
    INDEX idx_notif_log_status (status),
    INDEX idx_notif_log_created (created_at),
    INDEX idx_notif_log_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- VAPID keys storage (for Web Push)
CREATE TABLE IF NOT EXISTS push_vapid_keys (
    id VARCHAR(36) PRIMARY KEY,
    public_key TEXT NOT NULL,
    private_key TEXT NOT NULL COMMENT 'Encrypted',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add notification permissions
INSERT IGNORE INTO permissions (id, name, description, module, created_at) VALUES
(UUID(), 'notifications.manage', 'Manage notification settings', 'notifications', NOW()),
(UUID(), 'notifications.send', 'Send notifications to users', 'notifications', NOW());

-- Grant notification permissions
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name IN ('owner', 'admin', 'user') AND p.name = 'notifications.manage';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name IN ('owner', 'admin') AND p.name = 'notifications.send';
