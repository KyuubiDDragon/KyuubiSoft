CREATE TABLE IF NOT EXISTS cron_jobs (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    connection_id CHAR(36) DEFAULT NULL,
    expression VARCHAR(100) NOT NULL,
    command TEXT NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_run_at TIMESTAMP NULL DEFAULT NULL,
    next_run_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_cron_jobs_user (user_id)
);

CREATE TABLE IF NOT EXISTS cron_job_history (
    id CHAR(36) PRIMARY KEY,
    cron_job_id CHAR(36) NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    finished_at TIMESTAMP NULL DEFAULT NULL,
    exit_code INT DEFAULT NULL,
    stdout TEXT DEFAULT NULL,
    stderr TEXT DEFAULT NULL,
    duration_ms INT DEFAULT NULL,
    FOREIGN KEY (cron_job_id) REFERENCES cron_jobs(id) ON DELETE CASCADE,
    INDEX idx_cron_history_job (cron_job_id)
);
