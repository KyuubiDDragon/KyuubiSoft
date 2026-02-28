-- Fix contract_templates.user_id for existing databases where migration 110
-- was already executed with NOT NULL constraint.
-- On fresh databases, 110 already creates user_id as NULL, making this mostly a no-op
-- (but it still normalizes the FK constraint name).

-- Look up the current FK constraint name (could be auto-generated or explicit)
SET @constraint_name = (
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'contract_templates'
    AND COLUMN_NAME = 'user_id'
    AND REFERENCED_TABLE_NAME = 'users'
    LIMIT 1
);

-- Build a single ALTER TABLE that drops the old FK, modifies the column, and adds the new FK
-- Using a single ALTER TABLE ensures MySQL processes DROP before ADD atomically
SET @sql = IF(@constraint_name IS NOT NULL,
    CONCAT('ALTER TABLE contract_templates DROP FOREIGN KEY ', @constraint_name,
           ', MODIFY COLUMN user_id VARCHAR(36) NULL'
           ', ADD CONSTRAINT fk_contract_templates_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL'),
    'ALTER TABLE contract_templates MODIFY COLUMN user_id VARCHAR(36) NULL, ADD CONSTRAINT fk_contract_templates_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Clean up any old 'system' placeholder values
UPDATE contract_templates SET user_id = NULL WHERE user_id = 'system';
