-- Migration: Seed default roles and permissions
-- Version: 008

-- Insert default roles
INSERT INTO roles (name, description, is_system, hierarchy_level) VALUES
('owner', 'Vollzugriff auf alles', TRUE, 100),
('admin', 'Administrator', TRUE, 80),
('editor', 'Kann Inhalte bearbeiten', TRUE, 60),
('user', 'Standardbenutzer', TRUE, 40),
('viewer', 'Nur Lesezugriff', TRUE, 20)
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Insert default permissions
INSERT INTO permissions (name, description, module) VALUES
-- User permissions
('users.view', 'Benutzer anzeigen', 'users'),
('users.create', 'Benutzer erstellen', 'users'),
('users.edit', 'Benutzer bearbeiten', 'users'),
('users.delete', 'Benutzer löschen', 'users'),

-- Lists permissions
('lists.view', 'Listen anzeigen', 'lists'),
('lists.create', 'Listen erstellen', 'lists'),
('lists.edit', 'Listen bearbeiten', 'lists'),
('lists.delete', 'Listen löschen', 'lists'),

-- Documents permissions
('documents.view', 'Dokumente anzeigen', 'documents'),
('documents.create', 'Dokumente erstellen', 'documents'),
('documents.edit', 'Dokumente bearbeiten', 'documents'),
('documents.delete', 'Dokumente löschen', 'documents'),

-- Settings permissions
('settings.view', 'Einstellungen anzeigen', 'settings'),
('settings.edit', 'Einstellungen bearbeiten', 'settings'),

-- System permissions
('system.admin', 'Systemadministration', 'system'),
('system.audit', 'Audit-Logs anzeigen', 'system')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Assign permissions to roles
-- Owner gets everything
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p WHERE r.name = 'owner'
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- Admin gets everything except system.admin
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'admin' AND p.name != 'system.admin'
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- Editor gets content permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'editor' AND p.name IN (
    'lists.view', 'lists.create', 'lists.edit', 'lists.delete',
    'documents.view', 'documents.create', 'documents.edit', 'documents.delete',
    'settings.view'
)
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- User gets basic permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'user' AND p.name IN (
    'lists.view', 'lists.create', 'lists.edit',
    'documents.view', 'documents.create', 'documents.edit',
    'settings.view'
)
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- Viewer gets view-only permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'viewer' AND p.name IN (
    'lists.view', 'documents.view', 'settings.view'
)
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;
