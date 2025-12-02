-- Migration: Add assignee to kanban cards
-- Version: 017

-- Add assigned_to column to kanban_cards
ALTER TABLE kanban_cards
ADD COLUMN assigned_to CHAR(36) NULL AFTER due_date,
ADD INDEX idx_kanban_cards_assignee (assigned_to),
ADD CONSTRAINT fk_kanban_cards_assignee
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL;
