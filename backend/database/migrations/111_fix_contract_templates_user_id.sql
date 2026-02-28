-- Fix contract_templates.user_id: make nullable so system templates can have user_id = NULL.
-- Both statements are plain SQL and fully idempotent.

ALTER TABLE contract_templates MODIFY COLUMN user_id VARCHAR(36) NULL;

UPDATE contract_templates SET user_id = NULL WHERE user_id = 'system';
