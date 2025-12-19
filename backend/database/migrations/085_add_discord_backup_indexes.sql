-- Migration: Add composite indexes for Discord backups
-- Version: 084
-- Description: Add performance indexes for common query patterns

-- ============================================================================
-- Composite index for filtering backups by source and status
-- ============================================================================
SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'discord_backups'
    AND INDEX_NAME = 'idx_discord_backups_source_status'
);

SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_discord_backups_source_status ON discord_backups(source_type, status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- Composite index for bot backups lookup
-- ============================================================================
SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'discord_backups'
    AND INDEX_NAME = 'idx_discord_backups_bot_guild'
);

SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_discord_backups_bot_guild ON discord_backups(bot_id, discord_guild_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- Index for retention policy queries (finding old backups to delete)
-- ============================================================================
SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'discord_backups'
    AND INDEX_NAME = 'idx_discord_backups_retention'
);

SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_discord_backups_retention ON discord_backups(bot_id, discord_guild_id, status, created_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- Index for account backups lookup
-- ============================================================================
SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'discord_backups'
    AND INDEX_NAME = 'idx_discord_backups_account_status'
);

SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_discord_backups_account_status ON discord_backups(account_id, status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
