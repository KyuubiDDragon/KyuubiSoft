-- Automation/Workflow Engine Migration
-- IFTTT-style automation system

-- Workflow definitions
CREATE TABLE IF NOT EXISTS workflows (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_enabled BOOLEAN DEFAULT TRUE,
    trigger_type VARCHAR(50) NOT NULL COMMENT 'schedule, event, webhook',
    trigger_config JSON NOT NULL COMMENT 'Trigger-specific configuration',
    run_count INT DEFAULT 0,
    last_run_at TIMESTAMP NULL,
    last_run_status ENUM('success', 'failed', 'partial') NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_workflows_user (user_id),
    INDEX idx_workflows_enabled (is_enabled),
    INDEX idx_workflows_trigger (trigger_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Workflow actions (ordered steps)
CREATE TABLE IF NOT EXISTS workflow_actions (
    id VARCHAR(36) PRIMARY KEY,
    workflow_id VARCHAR(36) NOT NULL,
    position INT NOT NULL DEFAULT 0 COMMENT 'Order of execution',
    action_type VARCHAR(50) NOT NULL COMMENT 'create_task, send_notification, http_request, etc.',
    action_config JSON NOT NULL COMMENT 'Action-specific configuration',
    continue_on_error BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE,
    INDEX idx_wf_actions_workflow (workflow_id),
    INDEX idx_wf_actions_position (position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Workflow conditions (optional filtering)
CREATE TABLE IF NOT EXISTS workflow_conditions (
    id VARCHAR(36) PRIMARY KEY,
    workflow_id VARCHAR(36) NOT NULL,
    field VARCHAR(100) NOT NULL COMMENT 'Field to check',
    operator ENUM('equals', 'not_equals', 'contains', 'not_contains', 'gt', 'lt', 'gte', 'lte', 'is_empty', 'not_empty') NOT NULL,
    value TEXT COMMENT 'Value to compare against',
    logical_operator ENUM('and', 'or') DEFAULT 'and',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE,
    INDEX idx_wf_conditions_workflow (workflow_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Workflow execution logs
CREATE TABLE IF NOT EXISTS workflow_runs (
    id VARCHAR(36) PRIMARY KEY,
    workflow_id VARCHAR(36) NOT NULL,
    trigger_data JSON COMMENT 'Data that triggered the workflow',
    status ENUM('running', 'success', 'failed', 'partial') NOT NULL DEFAULT 'running',
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    duration_ms INT,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE,
    INDEX idx_wf_runs_workflow (workflow_id),
    INDEX idx_wf_runs_status (status),
    INDEX idx_wf_runs_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Action execution logs within a workflow run
CREATE TABLE IF NOT EXISTS workflow_action_logs (
    id VARCHAR(36) PRIMARY KEY,
    run_id VARCHAR(36) NOT NULL,
    action_id VARCHAR(36) NOT NULL,
    status ENUM('pending', 'running', 'success', 'failed', 'skipped') NOT NULL DEFAULT 'pending',
    input_data JSON,
    output_data JSON,
    error_message TEXT,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    duration_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (run_id) REFERENCES workflow_runs(id) ON DELETE CASCADE,
    FOREIGN KEY (action_id) REFERENCES workflow_actions(id) ON DELETE CASCADE,
    INDEX idx_wf_action_logs_run (run_id),
    INDEX idx_wf_action_logs_action (action_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Workflow templates (pre-built automations)
CREATE TABLE IF NOT EXISTS workflow_templates (
    id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category VARCHAR(50) NOT NULL,
    trigger_type VARCHAR(50) NOT NULL,
    trigger_config JSON NOT NULL,
    actions JSON NOT NULL COMMENT 'Array of action definitions',
    icon VARCHAR(50),
    is_featured BOOLEAN DEFAULT FALSE,
    use_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add some default templates
INSERT IGNORE INTO workflow_templates (id, name, description, category, trigger_type, trigger_config, actions, icon, is_featured) VALUES
(UUID(), 'Task-Erinnerung bei Fälligkeit', 'Sendet eine Benachrichtigung, wenn eine Aufgabe fällig wird', 'productivity', 'event', '{"event": "task.due"}', '[{"action_type": "send_notification", "config": {"title": "Aufgabe fällig!", "body": "{{task.content}} ist jetzt fällig"}}]', 'bell', TRUE),
(UUID(), 'Tägliche Zusammenfassung', 'Sendet täglich um 9 Uhr eine Übersicht', 'productivity', 'schedule', '{"cron": "0 9 * * *"}', '[{"action_type": "send_notification", "config": {"title": "Tägliche Zusammenfassung", "body": "Du hast {{tasks.open_count}} offene Aufgaben"}}]', 'calendar', TRUE),
(UUID(), 'Uptime-Warnung', 'Benachrichtigt bei Server-Ausfällen', 'monitoring', 'event', '{"event": "uptime.down"}', '[{"action_type": "send_notification", "config": {"title": "Server Down!", "body": "{{monitor.name}} ist nicht erreichbar"}}]', 'signal', TRUE),
(UUID(), 'Backup abgeschlossen', 'Benachrichtigung nach erfolgreichem Backup', 'system', 'event', '{"event": "backup.completed"}', '[{"action_type": "send_notification", "config": {"title": "Backup erfolgreich", "body": "{{backup.file_name}} wurde erstellt"}}]', 'archive', FALSE),
(UUID(), 'Wöchentlicher Report', 'Erstellt wöchentlich einen Produktivitäts-Report', 'productivity', 'schedule', '{"cron": "0 18 * * 5"}', '[{"action_type": "create_document", "config": {"title": "Wochenreport KW {{date.week}}", "content": "## Zusammenfassung\\n\\n- Erledigte Aufgaben: {{tasks.completed_week}}\\n- Zeiterfassung: {{time.total_week}}h"}}]', 'chart', FALSE);

-- Add workflow permissions
INSERT IGNORE INTO permissions (id, name, description, module, created_at) VALUES
(UUID(), 'workflows.view', 'View workflows', 'workflows', NOW()),
(UUID(), 'workflows.create', 'Create workflows', 'workflows', NOW()),
(UUID(), 'workflows.edit', 'Edit workflows', 'workflows', NOW()),
(UUID(), 'workflows.delete', 'Delete workflows', 'workflows', NOW()),
(UUID(), 'workflows.execute', 'Execute workflows manually', 'workflows', NOW());

-- Grant workflow permissions
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name IN ('owner', 'admin', 'user') AND p.module = 'workflows';
