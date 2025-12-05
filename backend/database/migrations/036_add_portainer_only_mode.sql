-- Add portainer_only mode to docker_hosts
-- When enabled, all Docker data is fetched through Portainer API instead of Docker socket

ALTER TABLE docker_hosts
ADD COLUMN portainer_only TINYINT(1) DEFAULT 0 AFTER portainer_endpoint_id;
