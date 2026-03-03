-- Allow public kanban shares to be editable and optionally without credentials
ALTER TABLE kanban_boards
ADD COLUMN share_can_edit TINYINT(1) DEFAULT 0 AFTER share_view_count;
