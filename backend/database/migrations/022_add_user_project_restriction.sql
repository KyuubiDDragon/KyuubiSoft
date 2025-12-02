-- Migration: Add project restriction flag to users
-- Version: 022

-- Add restricted_to_projects flag - when TRUE, user can only access shared projects
ALTER TABLE users
ADD COLUMN restricted_to_projects BOOLEAN NOT NULL DEFAULT FALSE AFTER is_verified;

-- Add index for filtering
ALTER TABLE users ADD INDEX idx_users_restricted (restricted_to_projects);
