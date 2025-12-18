-- Migration: Add recipient_id and last_message_id to discord_channels
-- Version: 079
-- Description: Add fields for better DM sorting and user info display

ALTER TABLE discord_channels
    ADD COLUMN recipient_id VARCHAR(20) NULL AFTER recipient_avatar,
    ADD COLUMN last_message_id VARCHAR(20) NULL AFTER recipient_id;

-- Add index for sorting by last_message_id
CREATE INDEX idx_discord_channels_last_message ON discord_channels (last_message_id);
