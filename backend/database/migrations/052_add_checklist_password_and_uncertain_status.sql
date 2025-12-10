-- Migration: Add password protection and uncertain status to shared checklists
-- Version: 052

-- Add password_hash column to shared_checklists
ALTER TABLE shared_checklists
ADD COLUMN password_hash VARCHAR(255) NULL AFTER allow_comments;

-- Modify status ENUM to include 'uncertain'
ALTER TABLE shared_checklist_entries
MODIFY COLUMN status ENUM('pending', 'in_progress', 'passed', 'failed', 'blocked', 'uncertain') NOT NULL DEFAULT 'pending';
