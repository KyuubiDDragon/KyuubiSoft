-- Seeder: Default roles and permissions
-- Version: 001

-- Insert default roles
INSERT INTO roles (name, description, is_system, hierarchy_level) VALUES
('owner', 'System owner with full access', TRUE, 100),
('admin', 'Administrator with management access', TRUE, 80),
('moderator', 'Moderator with limited management', TRUE, 60),
('editor', 'Editor with content creation access', TRUE, 40),
('user', 'Standard user', TRUE, 20),
('viewer', 'Read-only access', TRUE, 10)
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Insert default permissions
INSERT INTO permissions (name, description, module) VALUES
-- System permissions
('system.manage', 'Manage system settings', 'system'),
('system.logs', 'View system logs', 'system'),

-- User management
('users.read', 'View users', 'users'),
('users.write', 'Create/edit users', 'users'),
('users.delete', 'Delete users', 'users'),
('users.roles', 'Manage user roles', 'users'),

-- Lists
('lists.read', 'View lists', 'lists'),
('lists.write', 'Create/edit lists', 'lists'),
('lists.delete', 'Delete lists', 'lists'),
('lists.share', 'Share lists', 'lists'),

-- Documents
('documents.read', 'View documents', 'documents'),
('documents.write', 'Create/edit documents', 'documents'),
('documents.delete', 'Delete documents', 'documents'),
('documents.share', 'Share documents', 'documents'),

-- Settings
('settings.user.read', 'View own settings', 'settings'),
('settings.user.write', 'Edit own settings', 'settings'),
('settings.system.read', 'View system settings', 'settings'),
('settings.system.write', 'Edit system settings', 'settings'),

-- Monitoring
('monitoring.read', 'View monitoring data', 'monitoring'),
('monitoring.configure', 'Configure monitoring', 'monitoring')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Assign permissions to roles

-- Owner gets all permissions (handled in code, but let's add basic ones)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p WHERE r.name = 'owner'
ON DUPLICATE KEY UPDATE granted_at = NOW();

-- Admin permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'admin' AND p.name IN (
    'users.read', 'users.write', 'users.roles',
    'lists.read', 'lists.write', 'lists.delete', 'lists.share',
    'documents.read', 'documents.write', 'documents.delete', 'documents.share',
    'settings.user.read', 'settings.user.write', 'settings.system.read',
    'monitoring.read', 'monitoring.configure'
) ON DUPLICATE KEY UPDATE granted_at = NOW();

-- Editor permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'editor' AND p.name IN (
    'lists.read', 'lists.write', 'lists.share',
    'documents.read', 'documents.write', 'documents.share',
    'settings.user.read', 'settings.user.write'
) ON DUPLICATE KEY UPDATE granted_at = NOW();

-- User permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'user' AND p.name IN (
    'lists.read', 'lists.write',
    'documents.read', 'documents.write',
    'settings.user.read', 'settings.user.write'
) ON DUPLICATE KEY UPDATE granted_at = NOW();

-- Viewer permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'viewer' AND p.name IN (
    'lists.read',
    'documents.read',
    'settings.user.read'
) ON DUPLICATE KEY UPDATE granted_at = NOW();
