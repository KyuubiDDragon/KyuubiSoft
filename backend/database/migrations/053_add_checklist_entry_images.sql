-- Migration: Add image support for checklist entries
-- Version: 053

-- Add image_path column to shared_checklist_entries
ALTER TABLE shared_checklist_entries
ADD COLUMN image_path VARCHAR(255) NULL AFTER notes;
