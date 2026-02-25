-- Migration 091: Deprecate Discord user token storage
--
-- Storing Discord user tokens violates Discord's Terms of Service (self-botting).
-- All Discord integration now goes exclusively through Bot tokens
-- (discord_bots.bot_token_encrypted) which is the officially supported flow.

-- Conditionally drop token_encrypted if it exists (MySQL 5.7 compatible workaround)
SET @dbname = DATABASE();
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'discord_accounts' AND COLUMN_NAME = 'token_encrypted') > 0,
    'ALTER TABLE discord_accounts DROP COLUMN token_encrypted',
    'SELECT 1'
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Conditionally add note column if it doesn't exist yet
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'discord_accounts' AND COLUMN_NAME = 'note') > 0,
    'SELECT 1',
    'ALTER TABLE discord_accounts ADD COLUMN note VARCHAR(255) NULL DEFAULT NULL COMMENT ''Display-only metadata; authentication now uses Bot tokens via discord_bots table'''
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
