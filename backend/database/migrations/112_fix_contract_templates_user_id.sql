-- Make contract_templates.user_id nullable for system templates (idempotent)
-- Follows the note_templates pattern (075_create_notes_system.sql)

-- Drop foreign key if it exists
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_templates'
    AND CONSTRAINT_NAME = 'contract_templates_ibfk_1' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql = IF(@fk_exists > 0,
    'ALTER TABLE contract_templates DROP FOREIGN KEY contract_templates_ibfk_1',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Make user_id nullable
ALTER TABLE contract_templates MODIFY user_id VARCHAR(36) NULL;

-- Convert any 'system' user_id to NULL
UPDATE contract_templates SET user_id = NULL WHERE user_id = 'system';

-- Re-add foreign key with ON DELETE SET NULL (system templates persist when user is deleted)
SET @fk_exists2 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_templates'
    AND CONSTRAINT_NAME = 'contract_templates_ibfk_1' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql2 = IF(@fk_exists2 = 0,
    'ALTER TABLE contract_templates ADD CONSTRAINT contract_templates_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
