-- Migration: Fix Discord Backup account_id nullable
-- Version: 082
-- Description: Make account_id nullable in discord_backups for bot-based backups
-- Bot backups don't have an associated user account - they use bot_id instead

-- Drop the foreign key first (name may vary, try common patterns)
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

-- Re-add the foreign key with ON DELETE SET NULL for bot backups
ALTER TABLE discord_backups
    ADD CONSTRAINT fk_discord_backups_account
    FOREIGN KEY (account_id) REFERENCES discord_accounts(id) ON DELETE SET NULL;

-- Add a check constraint comment (MySQL 8.0+ would support CHECK constraint)
-- For now, we rely on application logic to ensure either account_id OR bot_id is set
