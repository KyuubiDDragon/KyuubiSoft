-- Add webhook_token column for auto-deploy webhook endpoints (idempotent)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deployment_pipelines' AND COLUMN_NAME = 'webhook_token');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE deployment_pipelines ADD COLUMN webhook_token VARCHAR(64) DEFAULT NULL UNIQUE AFTER auto_deploy',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
