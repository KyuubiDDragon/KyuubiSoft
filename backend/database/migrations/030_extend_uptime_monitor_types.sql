-- Extend uptime monitor types for game servers and additional protocols

-- Modify type column to support more protocols
ALTER TABLE uptime_monitors
    MODIFY COLUMN type ENUM(
        'http', 'https', 'ping', 'port',
        'tcp', 'udp',
        'minecraft', 'source', 'fivem', 'teamspeak',
        'dns', 'ssl'
    ) DEFAULT 'https';

-- Add columns for extended monitor data
ALTER TABLE uptime_monitors
    ADD COLUMN port INT NULL AFTER url,
    ADD COLUMN hostname VARCHAR(255) NULL AFTER port,
    ADD COLUMN dns_record_type ENUM('A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS') DEFAULT 'A' AFTER hostname,
    ADD COLUMN ssl_expiry_warn_days INT DEFAULT 14 AFTER dns_record_type,
    ADD COLUMN game_server_data JSON NULL AFTER ssl_expiry_warn_days;

-- Add columns to uptime_checks for extended data
ALTER TABLE uptime_checks
    ADD COLUMN check_data JSON NULL AFTER error_message;

-- Create index for faster game server queries
CREATE INDEX idx_monitors_type ON uptime_monitors(type);
