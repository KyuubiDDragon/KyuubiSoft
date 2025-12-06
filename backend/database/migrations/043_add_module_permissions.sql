-- Migration: Add permissions for all modules
-- Version: 043
-- Description: Adds granular permissions for feature-based access control

-- ═══════════════════════════════════════════════════════════════════════════════
-- DOCKER PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('docker.view', 'Docker-Übersicht anzeigen', 'docker'),
('docker.hosts_manage', 'Docker Hosts verwalten', 'docker'),
('docker.containers', 'Container verwalten', 'docker'),
('docker.images', 'Images verwalten', 'docker'),
('docker.volumes', 'Volumes verwalten', 'docker'),
('docker.networks', 'Networks verwalten', 'docker'),
('docker.system_socket', 'System Docker Socket Zugriff', 'docker'),
('docker.portainer', 'Portainer Integration nutzen', 'docker')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- SERVER PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('server.view', 'Server-Übersicht anzeigen', 'server'),
('server.manage', 'Server verwalten (Crontabs, Services)', 'server'),
('server.terminal', 'Server Terminal nutzen', 'server'),
('server.localhost', 'Localhost/Host-Server Zugriff', 'server')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- TOOLS PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('tools.ping', 'Ping Tool nutzen', 'tools'),
('tools.dns', 'DNS Lookup nutzen', 'tools'),
('tools.whois', 'Whois Abfrage nutzen', 'tools'),
('tools.traceroute', 'Traceroute nutzen', 'tools'),
('tools.ssl_check', 'SSL Check nutzen', 'tools'),
('tools.http_headers', 'HTTP Headers prüfen', 'tools'),
('tools.ip_lookup', 'IP Lookup nutzen', 'tools'),
('tools.security_headers', 'Security Headers prüfen', 'tools'),
('tools.open_graph', 'Open Graph Vorschau', 'tools'),
('tools.port_check', 'Port Scanner nutzen', 'tools'),
('tools.ssh', 'SSH Terminal nutzen', 'tools')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- UPTIME MONITOR PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('uptime.view', 'Uptime Monitors anzeigen', 'uptime'),
('uptime.manage', 'Uptime Monitors verwalten', 'uptime')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- INVOICES PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('invoices.view', 'Rechnungen anzeigen', 'invoices'),
('invoices.create', 'Rechnungen erstellen', 'invoices'),
('invoices.edit', 'Rechnungen bearbeiten', 'invoices'),
('invoices.delete', 'Rechnungen löschen', 'invoices')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- TICKETS PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('tickets.view', 'Tickets anzeigen', 'tickets'),
('tickets.create', 'Tickets erstellen', 'tickets'),
('tickets.manage', 'Tickets verwalten (Status, Zuweisung)', 'tickets')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- API TESTER PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('api_tester.view', 'API Tester anzeigen', 'api_tester'),
('api_tester.execute', 'API Requests ausführen', 'api_tester'),
('api_tester.auth_headers', 'Auth Headers speichern', 'api_tester')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- YOUTUBE PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('youtube.use', 'YouTube Downloader nutzen', 'youtube')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- PASSWORDS PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('passwords.view', 'Passwörter anzeigen', 'passwords'),
('passwords.manage', 'Passwörter verwalten', 'passwords')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- CALENDAR PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('calendar.view', 'Kalender anzeigen', 'calendar'),
('calendar.manage', 'Kalendereinträge verwalten', 'calendar')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- KANBAN PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('kanban.view', 'Kanban Boards anzeigen', 'kanban'),
('kanban.manage', 'Kanban Boards verwalten', 'kanban')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- BOOKMARKS PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('bookmarks.view', 'Bookmarks anzeigen', 'bookmarks'),
('bookmarks.manage', 'Bookmarks verwalten', 'bookmarks')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- SNIPPETS PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('snippets.view', 'Snippets anzeigen', 'snippets'),
('snippets.manage', 'Snippets verwalten', 'snippets')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- CONNECTIONS PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('connections.view', 'Verbindungen anzeigen', 'connections'),
('connections.manage', 'Verbindungen verwalten', 'connections'),
('connections.credentials', 'Zugangsdaten einsehen', 'connections')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- PROJECTS PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('projects.view', 'Projekte anzeigen', 'projects'),
('projects.manage', 'Projekte verwalten', 'projects')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- TIME TRACKING PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('time.view', 'Zeiterfassung anzeigen', 'time'),
('time.manage', 'Zeiterfassung verwalten', 'time')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- WEBHOOKS PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('webhooks.view', 'Webhooks anzeigen', 'webhooks'),
('webhooks.manage', 'Webhooks verwalten', 'webhooks')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- QUICK NOTES PERMISSIONS
-- ═══════════════════════════════════════════════════════════════════════════════
INSERT INTO permissions (name, description, module) VALUES
('notes.view', 'Quick Notes anzeigen', 'notes'),
('notes.manage', 'Quick Notes verwalten', 'notes')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ═══════════════════════════════════════════════════════════════════════════════
-- ASSIGN NEW PERMISSIONS TO ROLES
-- ═══════════════════════════════════════════════════════════════════════════════

-- Owner gets ALL permissions (already handled by wildcard logic in RbacManager)
-- But we add them explicitly for clarity
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'owner'
AND p.module IN ('docker', 'server', 'tools', 'uptime', 'invoices', 'tickets',
                 'api_tester', 'youtube', 'passwords', 'calendar', 'kanban',
                 'bookmarks', 'snippets', 'connections', 'projects', 'time',
                 'webhooks', 'notes')
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- Admin gets most permissions (except system-critical ones)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'admin'
AND p.module IN ('docker', 'server', 'tools', 'uptime', 'invoices', 'tickets',
                 'api_tester', 'youtube', 'passwords', 'calendar', 'kanban',
                 'bookmarks', 'snippets', 'connections', 'projects', 'time',
                 'webhooks', 'notes')
AND p.name NOT IN ('docker.system_socket', 'server.localhost')
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- Editor gets content-related permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'editor'
AND p.module IN ('calendar', 'kanban', 'bookmarks', 'snippets', 'projects',
                 'time', 'notes', 'tickets')
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- User gets basic permissions (view + limited manage for personal content)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'user'
AND (
    (p.module IN ('calendar', 'kanban', 'bookmarks', 'snippets', 'projects',
                  'time', 'notes') AND p.name LIKE '%.view')
    OR
    (p.module IN ('calendar', 'kanban', 'bookmarks', 'snippets', 'notes')
     AND p.name LIKE '%.manage')
    OR
    p.name IN ('tickets.view', 'tickets.create')
)
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- Viewer gets view-only permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'viewer'
AND p.name LIKE '%.view'
AND p.module IN ('calendar', 'kanban', 'bookmarks', 'snippets', 'projects',
                 'time', 'notes', 'tickets')
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;
