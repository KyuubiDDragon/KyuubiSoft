-- Migration: Add bot_server_id and bot_channel_id to discord_backups
-- Version: 083
-- Description: Add bot_server_id and bot_channel_id columns for bot-based backups
-- Bot backups use discord_bot_servers and discord_bot_channels, not the user-token tables

-- ============================================================================
-- PART 1: Add bot_server_id column
-- ============================================================================

SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'discord_backups'
    AND COLUMN_NAME = 'bot_server_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE discord_backups ADD COLUMN bot_server_id CHAR(36) NULL AFTER bot_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for bot_server_id
SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'discord_backups'
    AND INDEX_NAME = 'idx_discord_backups_bot_server'
);

SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_discord_backups_bot_server ON discord_backups(bot_server_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key for bot_server_id
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'discord_backups'
    AND CONSTRAINT_NAME = 'fk_discord_backups_bot_server'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE discord_backups ADD CONSTRAINT fk_discord_backups_bot_server FOREIGN KEY (bot_server_id) REFERENCES discord_bot_servers(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- PART 2: Add bot_channel_id column
-- ============================================================================

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
