CREATE TABLE IF NOT EXISTS notification_rules (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    trigger_event VARCHAR(100) NOT NULL,
    conditions JSON DEFAULT NULL,
    actions JSON NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_triggered_at TIMESTAMP NULL DEFAULT NULL,
    trigger_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notification_rules_user (user_id),
    INDEX idx_notification_rules_trigger (trigger_event)
);

CREATE TABLE IF NOT EXISTS notification_rule_history (
    id CHAR(36) PRIMARY KEY,
    rule_id CHAR(36) NOT NULL,
    triggered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    event_data JSON DEFAULT NULL,
    result VARCHAR(50) NOT NULL DEFAULT 'success',
    error_message TEXT DEFAULT NULL,
    FOREIGN KEY (rule_id) REFERENCES notification_rules(id) ON DELETE CASCADE,
    INDEX idx_rule_history_rule (rule_id)
);
