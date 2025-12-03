-- Migration: Add attachments to kanban cards
-- Version: 023

-- Add attachments JSON column to kanban_cards
ALTER TABLE kanban_cards
ADD COLUMN attachments JSON NULL AFTER labels;
