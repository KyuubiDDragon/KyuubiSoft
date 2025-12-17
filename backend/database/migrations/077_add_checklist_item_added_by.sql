-- Migration: Add added_by field to checklist items and categories
-- Version: 077

-- Add added_by to items
ALTER TABLE shared_checklist_items
ADD COLUMN added_by VARCHAR(100) NULL AFTER required_testers;

-- Add added_by to categories
ALTER TABLE shared_checklist_categories
ADD COLUMN added_by VARCHAR(100) NULL AFTER description;
