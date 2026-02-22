-- Script Vault: stored scripts and execution history
CREATE TABLE IF NOT EXISTS scripts (
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    language ENUM('bash', 'python', 'php', 'node') NOT NULL DEFAULT 'bash',
    content LONGTEXT NOT NULL,
    tags JSON NULL,
    is_favorite TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_scripts_user (user_id),
    INDEX idx_scripts_language (language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS script_executions (
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    script_id VARCHAR(36) NOT NULL,
    user_id INT NOT NULL,
    connection_id VARCHAR(36) NULL COMMENT 'NULL = run locally in backend container',
    stdout LONGTEXT NULL,
    stderr LONGTEXT NULL,
    exit_code INT NULL,
    duration_ms INT NULL,
    executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_script_executions_script (script_id),
    INDEX idx_script_executions_user (user_id),
    FOREIGN KEY (script_id) REFERENCES scripts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
