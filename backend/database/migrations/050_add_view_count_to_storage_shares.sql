-- Migration: Add view_count to storage_shares table
-- Version: 050

ALTER TABLE storage_shares ADD COLUMN view_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER download_count;
