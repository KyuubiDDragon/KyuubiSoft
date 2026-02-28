-- Fix contract_templates.user_id for existing databases where migration 110
-- was already executed with NOT NULL constraint.
-- On fresh databases, 110 already creates user_id as NULL, making this mostly a no-op
-- (but it still normalizes the FK constraint name).

-- Dynamically look up the FK constraint name (works regardless of auto-generated name)
SET @fk_name = (SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'contract_templates'
    AND COLUMN_NAME = 'user_id'
    AND REFERENCED_TABLE_NAME = 'users'
    LIMIT 1);

SET @drop_fk = IF(@fk_name IS NOT NULL,
    CONCAT('ALTER TABLE contract_templates DROP FOREIGN KEY `', @fk_name, '`'),
    'SELECT 1');
PREPARE stmt FROM @drop_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Make user_id nullable (no-op on fresh DBs, fixes existing DBs)
ALTER TABLE contract_templates MODIFY user_id VARCHAR(36) NULL;

-- Clean up any old 'system' placeholder values
UPDATE contract_templates SET user_id = NULL WHERE user_id = 'system';

-- Re-add FK with explicit name
ALTER TABLE contract_templates ADD CONSTRAINT fk_contract_templates_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
