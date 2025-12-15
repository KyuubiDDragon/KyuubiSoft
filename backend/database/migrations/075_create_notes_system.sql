-- Migration: Create Notes System (Phase 1)
-- Version: 075
-- Description: Hierarchical note-taking system with wiki-links, versions, and favorites

-- =====================================================
-- NOTES: Main table for hierarchical notes
-- =====================================================
CREATE TABLE IF NOT EXISTS notes (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    parent_id CHAR(36) NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    content LONGTEXT NULL,
    icon VARCHAR(50) NULL DEFAULT NULL,
    cover_image VARCHAR(500) NULL DEFAULT NULL,
    is_pinned BOOLEAN NOT NULL DEFAULT FALSE,
    is_archived BOOLEAN NOT NULL DEFAULT FALSE,
    is_template BOOLEAN NOT NULL DEFAULT FALSE,
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_at DATETIME NULL DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    word_count INT NOT NULL DEFAULT 0,
    content_version INT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_notes_user (user_id),
    INDEX idx_notes_parent (parent_id),
    INDEX idx_notes_slug (user_id, slug),
    INDEX idx_notes_pinned (user_id, is_pinned),
    INDEX idx_notes_archived (user_id, is_archived),
    INDEX idx_notes_template (user_id, is_template),
    INDEX idx_notes_deleted (user_id, is_deleted, deleted_at),
    INDEX idx_notes_sort (user_id, parent_id, sort_order),
    FULLTEXT INDEX ft_notes_search (title, content),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES notes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NOTE_TAGS: Many-to-Many relationship with tags
-- =====================================================
CREATE TABLE IF NOT EXISTS note_tags (
    note_id CHAR(36) NOT NULL,
    tag_id CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (note_id, tag_id),
    INDEX idx_note_tags_tag (tag_id),

    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NOTE_LINKS: Wiki-link tracking for backlinks
-- =====================================================
CREATE TABLE IF NOT EXISTS note_links (
    id CHAR(36) NOT NULL PRIMARY KEY,
    source_note_id CHAR(36) NOT NULL,
    target_note_id CHAR(36) NOT NULL,
    link_text VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_note_links_unique (source_note_id, target_note_id),
    INDEX idx_note_links_target (target_note_id),

    FOREIGN KEY (source_note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (target_note_id) REFERENCES notes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NOTE_FAVORITES: User favorites for notes
-- =====================================================
CREATE TABLE IF NOT EXISTS note_favorites (
    user_id CHAR(36) NOT NULL,
    note_id CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id, note_id),
    INDEX idx_note_favorites_note (note_id),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NOTE_RECENT: Recently accessed notes tracking
-- =====================================================
CREATE TABLE IF NOT EXISTS note_recent (
    user_id CHAR(36) NOT NULL,
    note_id CHAR(36) NOT NULL,
    accessed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id, note_id),
    INDEX idx_note_recent_time (user_id, accessed_at DESC),
    INDEX idx_note_recent_note (note_id),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NOTE_VERSIONS: Version history for notes
-- =====================================================
CREATE TABLE IF NOT EXISTS note_versions (
    id CHAR(36) NOT NULL PRIMARY KEY,
    note_id CHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    version_number INT NOT NULL,
    change_summary VARCHAR(500) NULL,
    word_count INT NOT NULL DEFAULT 0,
    created_by CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_note_versions_note (note_id, version_number DESC),
    INDEX idx_note_versions_created (note_id, created_at DESC),

    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NOTE_TEMPLATES: Predefined templates
-- =====================================================
CREATE TABLE IF NOT EXISTS note_templates (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    content LONGTEXT NOT NULL,
    icon VARCHAR(50) NULL DEFAULT NULL,
    category VARCHAR(100) NULL DEFAULT 'custom',
    is_system BOOLEAN NOT NULL DEFAULT FALSE,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_note_templates_user (user_id),
    INDEX idx_note_templates_category (category),
    INDEX idx_note_templates_system (is_system),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Insert default system templates
-- =====================================================
INSERT INTO note_templates (id, user_id, name, description, content, icon, category, is_system, sort_order) VALUES
(UUID(), NULL, 'Meeting Notes', 'Template for meeting documentation', '<h1>Meeting Notes</h1>\n<p><strong>Date:</strong> </p>\n<p><strong>Attendees:</strong> </p>\n<h2>Agenda</h2>\n<ul><li></li></ul>\n<h2>Discussion</h2>\n<p></p>\n<h2>Action Items</h2>\n<ul class=\"task-list\"><li data-checked=\"false\"></li></ul>\n<h2>Next Steps</h2>\n<p></p>', 'calendar', 'meetings', TRUE, 1),
(UUID(), NULL, 'Daily Journal', 'Template for daily journaling', '<h1>Daily Journal</h1>\n<p><strong>Date:</strong> </p>\n<h2>Highlights</h2>\n<ul><li></li></ul>\n<h2>Tasks</h2>\n<ul class=\"task-list\"><li data-checked=\"false\"></li></ul>\n<h2>Reflections</h2>\n<p></p>\n<h2>Tomorrow</h2>\n<p></p>', 'sun', 'personal', TRUE, 2),
(UUID(), NULL, 'Project Brief', 'Template for project documentation', '<h1>Project Brief</h1>\n<h2>Overview</h2>\n<p></p>\n<h2>Goals</h2>\n<ul><li></li></ul>\n<h2>Scope</h2>\n<p></p>\n<h2>Timeline</h2>\n<table><tr><th>Phase</th><th>Description</th><th>Deadline</th></tr><tr><td></td><td></td><td></td></tr></table>\n<h2>Team</h2>\n<ul><li></li></ul>\n<h2>Resources</h2>\n<p></p>', 'briefcase', 'work', TRUE, 3),
(UUID(), NULL, 'Bug Report', 'Template for bug documentation', '<h1>Bug Report</h1>\n<p><strong>Reported:</strong> </p>\n<p><strong>Priority:</strong> </p>\n<h2>Description</h2>\n<p></p>\n<h2>Steps to Reproduce</h2>\n<ol><li></li></ol>\n<h2>Expected Behavior</h2>\n<p></p>\n<h2>Actual Behavior</h2>\n<p></p>\n<h2>Environment</h2>\n<ul><li></li></ul>\n<h2>Screenshots</h2>\n<p></p>', 'bug', 'development', TRUE, 4),
(UUID(), NULL, 'Decision Log', 'Template for documenting decisions', '<h1>Decision Log</h1>\n<p><strong>Date:</strong> </p>\n<p><strong>Decision Makers:</strong> </p>\n<h2>Context</h2>\n<p></p>\n<h2>Options Considered</h2>\n<ol><li><strong>Option 1:</strong> </li><li><strong>Option 2:</strong> </li></ol>\n<h2>Decision</h2>\n<p></p>\n<h2>Rationale</h2>\n<p></p>\n<h2>Consequences</h2>\n<p></p>', 'scale', 'work', TRUE, 5),
(UUID(), NULL, 'Weekly Review', 'Template for weekly reviews', '<h1>Weekly Review</h1>\n<p><strong>Week of:</strong> </p>\n<h2>Achievements</h2>\n<ul><li></li></ul>\n<h2>Challenges</h2>\n<ul><li></li></ul>\n<h2>Lessons Learned</h2>\n<p></p>\n<h2>Next Week Goals</h2>\n<ul class=\"task-list\"><li data-checked=\"false\"></li></ul>\n<h2>Notes</h2>\n<p></p>', 'chart-bar', 'personal', TRUE, 6),
(UUID(), NULL, '1:1 Meeting', 'Template for one-on-one meetings', '<h1>1:1 Meeting</h1>\n<p><strong>Date:</strong> </p>\n<p><strong>With:</strong> </p>\n<h2>Agenda</h2>\n<ul><li></li></ul>\n<h2>Updates</h2>\n<p></p>\n<h2>Feedback</h2>\n<p></p>\n<h2>Blockers</h2>\n<ul><li></li></ul>\n<h2>Action Items</h2>\n<ul class=\"task-list\"><li data-checked=\"false\"></li></ul>', 'users', 'meetings', TRUE, 7),
(UUID(), NULL, 'Brainstorm', 'Template for brainstorming sessions', '<h1>Brainstorm</h1>\n<p><strong>Topic:</strong> </p>\n<p><strong>Date:</strong> </p>\n<h2>Ideas</h2>\n<ul><li></li></ul>\n<h2>Categories</h2>\n<h3>High Priority</h3>\n<ul><li></li></ul>\n<h3>Low Priority</h3>\n<ul><li></li></ul>\n<h2>Next Steps</h2>\n<ul class=\"task-list\"><li data-checked=\"false\"></li></ul>', 'lightbulb', 'creative', TRUE, 8);

-- =====================================================
-- Add notes feature flag
-- =====================================================
INSERT IGNORE INTO feature_flags (id, name, description, is_enabled, created_at) VALUES
(UUID(), 'notes', 'Enable Notes module', TRUE, NOW()),
(UUID(), 'notes.templates', 'Enable Notes templates', TRUE, NOW()),
(UUID(), 'notes.versions', 'Enable Notes version history', TRUE, NOW()),
(UUID(), 'notes.wiki_links', 'Enable Wiki-style linking between notes', TRUE, NOW());
