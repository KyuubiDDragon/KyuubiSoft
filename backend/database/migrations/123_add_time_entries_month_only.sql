-- Migration: Support month-only time entries
-- Version: 123
-- Description: Allows time entries without a specific date, using only a month (YYYY-MM)
--              and a duration. Makes started_at nullable and adds entry_month column.

ALTER TABLE time_entries
    MODIFY COLUMN started_at DATETIME NULL;

ALTER TABLE time_entries
    ADD COLUMN entry_month CHAR(7) NULL AFTER ended_at;

CREATE INDEX idx_time_entries_entry_month ON time_entries (user_id, entry_month);
