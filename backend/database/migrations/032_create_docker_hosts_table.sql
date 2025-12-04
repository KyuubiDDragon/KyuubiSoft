-- Docker hosts for multi-host management
CREATE TABLE IF NOT EXISTS docker_hosts (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    project_id VARCHAR(36) NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    type ENUM('socket', 'tcp') DEFAULT 'socket',
    socket_path VARCHAR(255) DEFAULT '/var/run/docker.sock',
    tcp_host VARCHAR(255) NULL,
    tcp_port INT DEFAULT 2375,
    tls_enabled TINYINT(1) DEFAULT 0,
    tls_ca TEXT NULL,
    tls_cert TEXT NULL,
    tls_key TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_default TINYINT(1) DEFAULT 0,
    last_connected_at DATETIME NULL,
    connection_status ENUM('connected', 'disconnected', 'error', 'unknown') DEFAULT 'unknown',
    last_error TEXT NULL,
    docker_version VARCHAR(50) NULL,
    api_version VARCHAR(20) NULL,
    containers_count INT DEFAULT 0,
    images_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    INDEX idx_docker_hosts_user (user_id),
    INDEX idx_docker_hosts_project (project_id),
    INDEX idx_docker_hosts_active (is_active),
    INDEX idx_docker_hosts_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default local Docker host
-- This will be created per-user when they first access Docker
