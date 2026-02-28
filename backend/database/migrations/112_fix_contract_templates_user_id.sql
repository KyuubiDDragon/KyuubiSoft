-- Fix contract_templates.user_id for existing databases where migration 110
-- was already executed with NOT NULL constraint.
-- On fresh databases, 110 already creates user_id as NULL, making this a no-op.

ALTER TABLE contract_templates DROP FOREIGN KEY contract_templates_ibfk_1;
ALTER TABLE contract_templates MODIFY user_id VARCHAR(36) NULL;
UPDATE contract_templates SET user_id = NULL WHERE user_id = 'system';
ALTER TABLE contract_templates ADD CONSTRAINT contract_templates_ibfk_1
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
