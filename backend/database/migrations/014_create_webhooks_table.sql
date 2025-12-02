-- Webhooks for Discord and other services
CREATE TABLE IF NOT EXISTS webhooks (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    url TEXT NOT NULL,
    type ENUM('discord', 'slack', 'custom') NOT NULL DEFAULT 'discord',
    events JSON NOT NULL COMMENT 'Array of event types to trigger on',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    secret VARCHAR(255) NULL COMMENT 'Secret for HMAC signature (custom webhooks)',
    last_triggered_at DATETIME NULL,
    last_status VARCHAR(50) NULL,
    failure_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_webhooks_user (user_id),
    INDEX idx_webhooks_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Webhook logs for debugging
CREATE TABLE IF NOT EXISTS webhook_logs (
    id CHAR(36) NOT NULL PRIMARY KEY,
    webhook_id CHAR(36) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    response_status INT NULL,
    response_body TEXT NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE,
    INDEX idx_webhook_logs_webhook (webhook_id),
    INDEX idx_webhook_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
