-- Add is_completed flag to kanban columns
-- This allows marking a column as "completed" so tasks in it are not shown as open/pending

ALTER TABLE kanban_columns ADD COLUMN is_completed BOOLEAN NOT NULL DEFAULT FALSE AFTER wip_limit;

-- Add index for faster filtering
CREATE INDEX idx_kanban_columns_is_completed ON kanban_columns(is_completed);
