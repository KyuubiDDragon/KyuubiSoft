-- Migration 091: Deprecate Discord user token storage
--
-- Storing Discord user tokens violates Discord's Terms of Service (self-botting).
-- This migration:
--   1. Renames the token column to make the deprecation explicit
--   2. Drops it entirely from discord_accounts so tokens cannot be stored
--
-- All Discord integration now goes exclusively through Bot tokens
-- (discord_bots.bot_token_encrypted) which is the officially supported flow.

-- Drop the column - user tokens should no longer be stored
-- (No need to clear first; DROP COLUMN removes data along with the column)
ALTER TABLE discord_accounts DROP COLUMN token_encrypted;

-- Add a note column documenting why accounts exist (for display purposes only)
ALTER TABLE discord_accounts
    ADD COLUMN note VARCHAR(255) NULL DEFAULT NULL
    COMMENT 'Display-only metadata; authentication now uses Bot tokens via discord_bots table';
