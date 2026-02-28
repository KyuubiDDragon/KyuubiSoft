-- Fix contract_templates.user_id for existing databases where migration 110
-- was already executed with NOT NULL constraint.
-- On fresh databases, 110 already creates user_id as NULL, making this mostly a no-op
-- (but it still normalizes the FK constraint name).

-- Drop the foreign key for user_id first
SET @constraint_name = (
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'contract_templates'
    AND COLUMN_NAME = 'user_id'
    AND REFERENCED_TABLE_NAME = 'users'
    LIMIT 1
);

SET @sql = IF(@constraint_name IS NOT NULL,
    CONCAT('ALTER TABLE contract_templates DROP FOREIGN KEY ', @constraint_name),
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Make user_id nullable (no-op on fresh DBs, fixes existing DBs)
ALTER TABLE contract_templates MODIFY COLUMN user_id VARCHAR(36) NULL;

-- Clean up any old 'system' placeholder values
UPDATE contract_templates SET user_id = NULL WHERE user_id = 'system';

-- Re-add the foreign key with ON DELETE SET NULL
ALTER TABLE contract_templates
    ADD CONSTRAINT fk_contract_templates_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
