CREATE TABLE IF NOT EXISTS deployment_pipelines (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    connection_id CHAR(36) DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    repository VARCHAR(500) DEFAULT NULL,
    branch VARCHAR(255) DEFAULT 'main',
    steps JSON NOT NULL,
    environment VARCHAR(50) DEFAULT 'production',
    auto_deploy BOOLEAN DEFAULT FALSE,
    notify_on_success BOOLEAN DEFAULT TRUE,
    notify_on_failure BOOLEAN DEFAULT TRUE,
    last_deployed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pipelines_user (user_id)
);

CREATE TABLE IF NOT EXISTS deployments (
    id CHAR(36) PRIMARY KEY,
    pipeline_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    status ENUM('pending', 'running', 'success', 'failed', 'cancelled', 'rolled_back') DEFAULT 'pending',
    commit_hash VARCHAR(40) DEFAULT NULL,
    commit_message TEXT DEFAULT NULL,
    steps_log JSON DEFAULT NULL,
    started_at TIMESTAMP NULL DEFAULT NULL,
    finished_at TIMESTAMP NULL DEFAULT NULL,
    duration_ms INT DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    rollback_of CHAR(36) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pipeline_id) REFERENCES deployment_pipelines(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_deployments_pipeline (pipeline_id),
    INDEX idx_deployments_user (user_id)
);
