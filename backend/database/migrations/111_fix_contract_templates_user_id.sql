-- Fix contract_templates.user_id: make nullable and change FK to ON DELETE SET NULL.
-- Original migration 110 had user_id NOT NULL with ON DELETE CASCADE.
-- This migration is fully idempotent — safe to run multiple times (container restarts).

-- Step 1: Drop any OLD FK on user_id (but NOT fk_ct_user if it already exists from a previous run)
SET @old_fk = (
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'contract_templates'
    AND COLUMN_NAME = 'user_id'
    AND REFERENCED_TABLE_NAME = 'users'
    AND CONSTRAINT_NAME != 'fk_ct_user'
    LIMIT 1
);

SET @drop_sql = IF(@old_fk IS NOT NULL,
    CONCAT('ALTER TABLE contract_templates DROP FOREIGN KEY ', @old_fk),
    'DO 0'
);
PREPARE drop_stmt FROM @drop_sql;
EXECUTE drop_stmt;
DEALLOCATE PREPARE drop_stmt;

-- Step 2: Make column nullable (idempotent)
ALTER TABLE contract_templates MODIFY COLUMN user_id VARCHAR(36) NULL;

-- Step 3: Clean up old placeholder values (idempotent)
UPDATE contract_templates SET user_id = NULL WHERE user_id = 'system';

-- Step 4: Add correct FK only if it doesn't exist yet
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'contract_templates'
    AND CONSTRAINT_NAME = 'fk_ct_user'
);

SET @add_sql = IF(@fk_exists = 0,
    'ALTER TABLE contract_templates ADD CONSTRAINT fk_ct_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL',
    'DO 0'
);
PREPARE add_stmt FROM @add_sql;
EXECUTE add_stmt;
DEALLOCATE PREPARE add_stmt;
