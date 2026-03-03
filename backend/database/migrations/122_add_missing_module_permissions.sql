-- Migration: Add missing module permissions
-- Version: 122
-- Description: Adds permissions for modules that were missing permission checks
--              (habits, finance, contacts, email, contracts, settings, mockup)

-- ═══════════════════════════════════════════════════════════════════════════════
-- HABIT TRACKER PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('habits.view', 'Habit Tracker anzeigen', 'habits'),
('habits.manage', 'Habit Tracker verwalten', 'habits')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- FINANCE / EXPENSES PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('finance.view', 'Finanzen und Ausgaben anzeigen', 'finance'),
('finance.manage', 'Finanzen und Ausgaben verwalten', 'finance')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- CONTACTS PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('contacts.view', 'Kontakte anzeigen', 'contacts'),
('contacts.manage', 'Kontakte verwalten', 'contacts')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- EMAIL PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('email.view', 'E-Mail anzeigen', 'email'),
('email.manage', 'E-Mail verwalten', 'email')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- CONTRACTS PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('contracts.view', 'Verträge anzeigen', 'contracts'),
('contracts.manage', 'Verträge verwalten', 'contracts')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- SETTINGS PERMISSIONS (if not already present)
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('settings.view', 'Einstellungen anzeigen', 'settings'),
('settings.edit', 'Einstellungen bearbeiten', 'settings')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- MOCKUP EDITOR PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('mockup.view', 'Mockup Editor anzeigen', 'mockup'),
('mockup.manage', 'Mockup Editor verwalten', 'mockup')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- ASSIGN NEW PERMISSIONS TO ROLES
-- ═══════════════════════════════════════════════════════════════════════════════

-- Owner gets ALL permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'owner'
AND p.module IN ('habits', 'finance', 'contacts', 'email', 'contracts', 'settings', 'mockup')
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- Admin gets all new permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'admin'
AND p.module IN ('habits', 'finance', 'contacts', 'email', 'contracts', 'settings', 'mockup')
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- Editor gets content-related permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'editor'
AND p.name IN (
    'habits.view', 'habits.manage',
    'finance.view',
    'contacts.view', 'contacts.manage',
    'email.view',
    'contracts.view',
    'settings.view',
    'mockup.view', 'mockup.manage'
)
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- User gets basic view permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'user'
AND p.name IN (
    'habits.view', 'habits.manage',
    'contacts.view',
    'settings.view',
    'mockup.view'
)
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- Viewer gets only view permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'viewer'
AND p.name IN (
    'habits.view',
    'contacts.view',
    'settings.view'
)
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;
