-- Add webhook_token column for auto-deploy webhook endpoints
ALTER TABLE deployment_pipelines ADD COLUMN webhook_token VARCHAR(64) DEFAULT NULL UNIQUE AFTER auto_deploy;
