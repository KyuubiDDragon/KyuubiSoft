-- Add Portainer integration fields to docker_hosts
-- Allows fetching stack configs from Portainer API when files not accessible locally

ALTER TABLE docker_hosts
ADD COLUMN portainer_url VARCHAR(255) NULL AFTER api_version,
ADD COLUMN portainer_api_token TEXT NULL AFTER portainer_url,
ADD COLUMN portainer_endpoint_id INT NULL AFTER portainer_api_token;

-- Also create a table to cache stack-to-portainer mappings
CREATE TABLE IF NOT EXISTS docker_stack_portainer_map (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    docker_host_id VARCHAR(36) NULL,
    stack_name VARCHAR(255) NOT NULL,
    portainer_stack_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (docker_host_id) REFERENCES docker_hosts(id) ON DELETE CASCADE,
    UNIQUE KEY unique_stack_mapping (user_id, docker_host_id, stack_name),
    INDEX idx_stack_map_user (user_id),
    INDEX idx_stack_map_host (docker_host_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
