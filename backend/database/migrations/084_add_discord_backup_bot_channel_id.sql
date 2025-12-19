-- Migration: Add bot_channel_id to discord_backups
-- Version: 084
-- Description: Add bot_channel_id column for bot-based backups

-- Add bot_channel_id column if it doesn't exist
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'discord_backups'
    AND COLUMN_NAME = 'bot_channel_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE discord_backups ADD COLUMN bot_channel_id CHAR(36) NULL AFTER bot_server_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for bot_channel_id
SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'discord_backups'
    AND INDEX_NAME = 'idx_discord_backups_bot_channel'
);

SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_discord_backups_bot_channel ON discord_backups(bot_channel_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key for bot_channel_id
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'discord_backups'
    AND CONSTRAINT_NAME = 'fk_discord_backups_bot_channel'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE discord_backups ADD CONSTRAINT fk_discord_backups_bot_channel FOREIGN KEY (bot_channel_id) REFERENCES discord_bot_channels(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
