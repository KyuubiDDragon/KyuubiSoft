-- Migration: Update documents format ENUM to support new document types
-- Version: 009

-- Update the format ENUM to include richtext, code, and spreadsheet
ALTER TABLE documents
MODIFY COLUMN format ENUM('markdown', 'html', 'plain', 'richtext', 'code', 'spreadsheet') NOT NULL DEFAULT 'markdown';
