-- Migration: Fix Discord Backup constraints for bot-based backups
-- Version: 082
-- Description: Make account_id nullable and add bot_server_id for bot-based backups
-- Bot backups don't have an associated user account - they use bot_id instead
-- Bot backups also use discord_bot_servers instead of discord_servers

-- ============================================================================
-- PART 1: Fix account_id to be nullable
-- ============================================================================

-- Drop the foreign key for account_id first
SET @constraint_name = (
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'discord_backups'
    AND COLUMN_NAME = 'account_id'
    AND REFERENCED_TABLE_NAME = 'discord_accounts'
    LIMIT 1
);

SET @sql = IF(@constraint_name IS NOT NULL,
    CONCAT('ALTER TABLE discord_backups DROP FOREIGN KEY ', @constraint_name),
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Make account_id nullable
ALTER TABLE discord_backups MODIFY COLUMN account_id CHAR(36) NULL;

-- Re-add the foreign key with ON DELETE SET NULL
ALTER TABLE discord_backups
    ADD CONSTRAINT fk_discord_backups_account
    FOREIGN KEY (account_id) REFERENCES discord_accounts(id) ON DELETE SET NULL;

-- ============================================================================
-- PART 2: Add bot_server_id column for bot-based backups
-- ============================================================================

-- Add bot_server_id column (references discord_bot_servers instead of discord_servers)
ALTER TABLE discord_backups
    ADD COLUMN bot_server_id CHAR(36) NULL AFTER bot_id;

-- Add index for bot_server_id
CREATE INDEX idx_discord_backups_bot_server ON discord_backups(bot_server_id);

-- Add foreign key to discord_bot_servers
ALTER TABLE discord_backups
    ADD CONSTRAINT fk_discord_backups_bot_server
    FOREIGN KEY (bot_server_id) REFERENCES discord_bot_servers(id) ON DELETE SET NULL;

-- Note: For bot backups, use bot_server_id (NOT server_id)
-- server_id references discord_servers (for user token backups)
-- bot_server_id references discord_bot_servers (for bot backups)
