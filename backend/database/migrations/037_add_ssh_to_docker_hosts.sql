-- Add SSH credentials to docker_hosts for remote file access
ALTER TABLE docker_hosts
ADD COLUMN ssh_enabled TINYINT(1) DEFAULT 0 AFTER portainer_only,
ADD COLUMN ssh_host VARCHAR(255) DEFAULT NULL AFTER ssh_enabled,
ADD COLUMN ssh_port INT DEFAULT 22 AFTER ssh_host,
ADD COLUMN ssh_user VARCHAR(255) DEFAULT NULL AFTER ssh_port,
ADD COLUMN ssh_password TEXT DEFAULT NULL AFTER ssh_user,
ADD COLUMN ssh_private_key TEXT DEFAULT NULL AFTER ssh_password;
