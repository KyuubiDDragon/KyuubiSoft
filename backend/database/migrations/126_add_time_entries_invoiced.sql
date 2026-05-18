-- Migration: Mark time entries as invoiced
-- Version: 126
-- Description: Adds is_invoiced flag and invoiced_at timestamp so users can
--              tick off entries that have already been billed.

ALTER TABLE time_entries
    ADD COLUMN is_invoiced BOOLEAN NOT NULL DEFAULT FALSE AFTER is_billable,
    ADD COLUMN invoiced_at DATETIME NULL AFTER is_invoiced;

CREATE INDEX idx_time_entries_invoiced ON time_entries (user_id, is_invoiced);
