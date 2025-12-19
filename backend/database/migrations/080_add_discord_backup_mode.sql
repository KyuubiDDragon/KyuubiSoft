-- Migration: Add backup_mode to discord_backups
-- Version: 080
-- Description: Add backup_mode field for media-only and links-only backups

ALTER TABLE discord_backups
    ADD COLUMN backup_mode VARCHAR(20) DEFAULT 'full' AFTER type;

-- Update existing backups to have 'full' mode
UPDATE discord_backups SET backup_mode = 'full' WHERE backup_mode IS NULL;
