-- Migration: Add 2FA requirement and project selection fields to users
-- Version: 044

-- Add require_2fa flag - when TRUE, user must set up 2FA on next login
ALTER TABLE users
ADD COLUMN require_2fa BOOLEAN NOT NULL DEFAULT FALSE AFTER restricted_to_projects;

-- Add allowed_project_ids - JSON array of project IDs user can access when restricted
-- If NULL or empty, user can't access any projects when restricted_to_projects is TRUE
ALTER TABLE users
ADD COLUMN allowed_project_ids JSON NULL AFTER require_2fa;

-- Add index for 2FA requirement lookups
ALTER TABLE users ADD INDEX idx_users_require_2fa (require_2fa);
