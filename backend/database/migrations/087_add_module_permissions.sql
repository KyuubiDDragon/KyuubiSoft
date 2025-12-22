-- Migration: Add granular module permissions
-- Version: 087

-- Insert new module permissions
INSERT INTO permissions (name, description, module) VALUES
-- Docker
('docker.view', 'Docker Container anzeigen', 'docker'),
('docker.manage', 'Docker Container verwalten (start/stop/restart)', 'docker'),
('docker.hosts', 'Docker Hosts verwalten', 'docker'),

-- Tickets
('tickets.view', 'Tickets anzeigen', 'tickets'),
('tickets.create', 'Tickets erstellen', 'tickets'),
('tickets.manage', 'Tickets verwalten (bearbeiten, löschen, Kategorien)', 'tickets'),

-- Backups
('backups.view', 'Backups anzeigen', 'backups'),
('backups.create', 'Backups erstellen', 'backups'),
('backups.restore', 'Backups wiederherstellen', 'backups'),
('backups.delete', 'Backups löschen', 'backups'),

-- Kanban
('kanban.view', 'Kanban Boards anzeigen', 'kanban'),
('kanban.edit', 'Kanban Boards bearbeiten', 'kanban'),
('kanban.manage', 'Kanban Boards verwalten (erstellen, löschen)', 'kanban'),

-- Projects
('projects.view', 'Projekte anzeigen', 'projects'),
('projects.edit', 'Projekte bearbeiten', 'projects'),
('projects.manage', 'Projekte verwalten (erstellen, löschen)', 'projects'),

-- Calendar
('calendar.view', 'Kalender anzeigen', 'calendar'),
('calendar.edit', 'Kalendereinträge bearbeiten', 'calendar'),
('calendar.manage', 'Externe Kalender verwalten', 'calendar'),

-- Storage
('storage.view', 'Dateien anzeigen', 'storage'),
('storage.upload', 'Dateien hochladen', 'storage'),
('storage.manage', 'Dateien verwalten (löschen, Ordner erstellen)', 'storage'),
('storage.share', 'Dateien teilen', 'storage'),

-- Checklists
('checklists.view', 'Checklisten anzeigen', 'checklists'),
('checklists.edit', 'Checklisten bearbeiten', 'checklists'),
('checklists.manage', 'Checklisten verwalten', 'checklists'),

-- Passwords
('passwords.view', 'Passwörter anzeigen', 'passwords'),
('passwords.edit', 'Passwörter bearbeiten', 'passwords'),
('passwords.manage', 'Passwörter verwalten', 'passwords'),

-- Wiki
('wiki.view', 'Wiki anzeigen', 'wiki'),
('wiki.edit', 'Wiki bearbeiten', 'wiki'),
('wiki.manage', 'Wiki verwalten', 'wiki'),

-- Chat
('chat.view', 'Team Chat anzeigen', 'chat'),
('chat.write', 'Im Team Chat schreiben', 'chat'),

-- Discord
('discord.view', 'Discord Manager anzeigen', 'discord'),
('discord.manage', 'Discord Manager verwalten', 'discord'),

-- Uptime Monitor
('uptime.view', 'Uptime Monitor anzeigen', 'uptime'),
('uptime.manage', 'Uptime Monitor verwalten', 'uptime'),

-- Invoices
('invoices.view', 'Rechnungen anzeigen', 'invoices'),
('invoices.edit', 'Rechnungen bearbeiten', 'invoices'),
('invoices.manage', 'Rechnungen verwalten', 'invoices'),

-- Bookmarks
('bookmarks.view', 'Lesezeichen anzeigen', 'bookmarks'),
('bookmarks.edit', 'Lesezeichen bearbeiten', 'bookmarks'),

-- Snippets
('snippets.view', 'Code Snippets anzeigen', 'snippets'),
('snippets.edit', 'Code Snippets bearbeiten', 'snippets'),

-- Connections (SSH)
('connections.view', 'SSH Verbindungen anzeigen', 'connections'),
('connections.connect', 'SSH Verbindungen nutzen', 'connections'),
('connections.manage', 'SSH Verbindungen verwalten', 'connections'),

-- Links (Shortener)
('links.view', 'Kurzlinks anzeigen', 'links'),
('links.create', 'Kurzlinks erstellen', 'links'),
('links.manage', 'Kurzlinks verwalten', 'links'),

-- Git Repositories
('git.view', 'Git Repositories anzeigen', 'git'),
('git.manage', 'Git Repositories verwalten', 'git'),

-- SSL Certificates
('ssl.view', 'SSL Zertifikate anzeigen', 'ssl'),
('ssl.manage', 'SSL Zertifikate verwalten', 'ssl'),

-- Public Galleries
('galleries.view', 'Öffentliche Galerien anzeigen', 'galleries'),
('galleries.manage', 'Öffentliche Galerien verwalten', 'galleries'),

-- News
('news.view', 'News Feed anzeigen', 'news'),
('news.manage', 'News Feeds verwalten', 'news'),

-- Automation/Workflows
('automation.view', 'Workflows anzeigen', 'automation'),
('automation.manage', 'Workflows verwalten', 'automation'),

-- API Tester
('api-tester.view', 'API Tester nutzen', 'api-tester'),

-- YouTube Downloader
('youtube.view', 'YouTube Downloader nutzen', 'youtube'),

-- Time Tracking
('time.view', 'Zeiterfassung anzeigen', 'time'),
('time.edit', 'Zeiterfassung bearbeiten', 'time'),
('time.manage', 'Zeiterfassung verwalten', 'time'),

-- Inbox
('inbox.view', 'Inbox anzeigen', 'inbox'),
('inbox.manage', 'Inbox verwalten', 'inbox'),

-- Notes
('notes.view', 'Notizen anzeigen', 'notes'),
('notes.edit', 'Notizen bearbeiten', 'notes'),

-- Server Info
('server.view', 'Server-Info anzeigen', 'server'),

-- Recurring Tasks
('recurring.view', 'Wiederkehrende Aufgaben anzeigen', 'recurring'),
('recurring.manage', 'Wiederkehrende Aufgaben verwalten', 'recurring'),

-- Webhooks
('webhooks.view', 'Webhooks anzeigen', 'webhooks'),
('webhooks.manage', 'Webhooks verwalten', 'webhooks'),

-- API Keys
('apikeys.view', 'API Keys anzeigen', 'apikeys'),
('apikeys.manage', 'API Keys verwalten', 'apikeys'),

-- AI Assistant
('ai.view', 'AI Assistant nutzen', 'ai'),
('ai.manage', 'AI Einstellungen verwalten', 'ai'),

-- Templates
('templates.view', 'Vorlagen anzeigen', 'templates'),
('templates.manage', 'Vorlagen verwalten', 'templates'),

-- Dashboard
('dashboard.view', 'Dashboard anzeigen', 'dashboard'),
('dashboard.edit', 'Dashboard Widgets bearbeiten', 'dashboard')

ON DUPLICATE KEY UPDATE description = VALUES(description);

-- =====================================================
-- Assign permissions to roles
-- =====================================================

-- Owner gets ALL permissions (already handled by system)

-- Admin gets almost everything
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'admin' AND p.name IN (
    'docker.view', 'docker.manage', 'docker.hosts',
    'tickets.view', 'tickets.create', 'tickets.manage',
    'backups.view', 'backups.create', 'backups.restore', 'backups.delete',
    'kanban.view', 'kanban.edit', 'kanban.manage',
    'projects.view', 'projects.edit', 'projects.manage',
    'calendar.view', 'calendar.edit', 'calendar.manage',
    'storage.view', 'storage.upload', 'storage.manage', 'storage.share',
    'checklists.view', 'checklists.edit', 'checklists.manage',
    'passwords.view', 'passwords.edit', 'passwords.manage',
    'wiki.view', 'wiki.edit', 'wiki.manage',
    'chat.view', 'chat.write',
    'discord.view', 'discord.manage',
    'uptime.view', 'uptime.manage',
    'invoices.view', 'invoices.edit', 'invoices.manage',
    'bookmarks.view', 'bookmarks.edit',
    'snippets.view', 'snippets.edit',
    'connections.view', 'connections.connect', 'connections.manage',
    'links.view', 'links.create', 'links.manage',
    'git.view', 'git.manage',
    'ssl.view', 'ssl.manage',
    'galleries.view', 'galleries.manage',
    'news.view', 'news.manage',
    'automation.view', 'automation.manage',
    'api-tester.view',
    'youtube.view',
    'time.view', 'time.edit', 'time.manage',
    'inbox.view', 'inbox.manage',
    'notes.view', 'notes.edit',
    'server.view',
    'recurring.view', 'recurring.manage',
    'webhooks.view', 'webhooks.manage',
    'apikeys.view', 'apikeys.manage',
    'ai.view', 'ai.manage',
    'templates.view', 'templates.manage',
    'dashboard.view', 'dashboard.edit'
)
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- Editor gets content editing permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'editor' AND p.name IN (
    'kanban.view', 'kanban.edit',
    'projects.view', 'projects.edit',
    'calendar.view', 'calendar.edit',
    'storage.view', 'storage.upload', 'storage.share',
    'checklists.view', 'checklists.edit',
    'wiki.view', 'wiki.edit',
    'chat.view', 'chat.write',
    'invoices.view', 'invoices.edit',
    'bookmarks.view', 'bookmarks.edit',
    'snippets.view', 'snippets.edit',
    'news.view',
    'time.view', 'time.edit',
    'inbox.view',
    'notes.view', 'notes.edit',
    'templates.view',
    'dashboard.view', 'dashboard.edit'
)
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- User gets basic view and limited edit permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'user' AND p.name IN (
    'kanban.view', 'kanban.edit',
    'projects.view',
    'calendar.view', 'calendar.edit',
    'storage.view', 'storage.upload',
    'checklists.view', 'checklists.edit',
    'wiki.view',
    'chat.view', 'chat.write',
    'bookmarks.view', 'bookmarks.edit',
    'snippets.view', 'snippets.edit',
    'news.view',
    'time.view', 'time.edit',
    'inbox.view',
    'notes.view', 'notes.edit',
    'dashboard.view', 'dashboard.edit'
)
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- Viewer gets only view permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'viewer' AND p.name IN (
    'kanban.view',
    'projects.view',
    'calendar.view',
    'storage.view',
    'checklists.view',
    'wiki.view',
    'chat.view',
    'bookmarks.view',
    'snippets.view',
    'news.view',
    'time.view',
    'notes.view',
    'dashboard.view'
)
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;
