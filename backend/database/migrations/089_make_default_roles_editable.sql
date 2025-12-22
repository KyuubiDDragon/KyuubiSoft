-- Make default roles (except owner and pending) editable
-- Only owner and pending should be true system roles

-- Set is_system = 0 for admin, editor, user, viewer
-- This allows them to be edited, have permissions changed, or be deleted
UPDATE roles SET is_system = 0 WHERE name IN ('admin', 'editor', 'user', 'viewer');

-- Ensure owner and pending remain as system roles
UPDATE roles SET is_system = 1 WHERE name IN ('owner', 'pending');
