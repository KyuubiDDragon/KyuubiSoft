-- Migration: Create mockup_templates and mockup_drafts tables
-- Version: 090

-- Custom mockup templates saved by users
CREATE TABLE IF NOT EXISTS mockup_templates (
    id CHAR(32) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(50) DEFAULT 'custom',
    width INT UNSIGNED DEFAULT 1920,
    height INT UNSIGNED DEFAULT 1080,
    aspect_ratio VARCHAR(20) DEFAULT '16:9',
    elements JSON NOT NULL,
    transparent_bg TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index for faster lookups
CREATE INDEX idx_mockup_templates_user ON mockup_templates(user_id);
CREATE INDEX idx_mockup_templates_category ON mockup_templates(category);

-- Mockup drafts (work in progress)
CREATE TABLE IF NOT EXISTS mockup_drafts (
    id CHAR(32) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    name VARCHAR(255) DEFAULT 'Untitled Draft',
    template_id VARCHAR(100),
    width INT UNSIGNED DEFAULT 1920,
    height INT UNSIGNED DEFAULT 1080,
    elements JSON NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index for faster lookups
CREATE INDEX idx_mockup_drafts_user ON mockup_drafts(user_id);
CREATE INDEX idx_mockup_drafts_updated ON mockup_drafts(updated_at);
