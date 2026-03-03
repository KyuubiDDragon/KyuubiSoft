-- Add public sharing columns to kanban_boards
ALTER TABLE kanban_boards
ADD COLUMN share_token VARCHAR(64) DEFAULT NULL AFTER is_archived,
ADD COLUMN share_username VARCHAR(100) DEFAULT NULL AFTER share_token,
ADD COLUMN share_password VARCHAR(255) DEFAULT NULL AFTER share_username,
ADD COLUMN share_expires_at DATETIME DEFAULT NULL AFTER share_password,
ADD COLUMN share_view_count INT DEFAULT 0 AFTER share_expires_at;

CREATE UNIQUE INDEX idx_kanban_boards_share_token ON kanban_boards(share_token);
