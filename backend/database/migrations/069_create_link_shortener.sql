-- Link Shortener Module
-- Self-hosted URL shortening service with analytics

CREATE TABLE IF NOT EXISTS short_links (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    short_code VARCHAR(20) NOT NULL UNIQUE,
    original_url TEXT NOT NULL,
    title VARCHAR(255),
    description TEXT,
    password_hash VARCHAR(255) COMMENT 'Optional password protection',
    expires_at TIMESTAMP NULL COMMENT 'Optional expiration',
    max_clicks INT NULL COMMENT 'Optional click limit',
    click_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_short_links_code (short_code),
    INDEX idx_short_links_user (user_id),
    INDEX idx_short_links_active (is_active, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS short_link_clicks (
    id VARCHAR(36) PRIMARY KEY,
    link_id VARCHAR(36) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    referrer TEXT,
    country_code VARCHAR(2),
    city VARCHAR(100),
    browser VARCHAR(50),
    os VARCHAR(50),
    device_type ENUM('desktop', 'mobile', 'tablet', 'bot', 'unknown') DEFAULT 'unknown',
    clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (link_id) REFERENCES short_links(id) ON DELETE CASCADE,
    INDEX idx_clicks_link (link_id),
    INDEX idx_clicks_date (clicked_at),
    INDEX idx_clicks_country (country_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add permissions
INSERT IGNORE INTO permissions (id, name, description, module, created_at) VALUES
(UUID(), 'links.view', 'View short links', 'links', NOW()),
(UUID(), 'links.create', 'Create short links', 'links', NOW()),
(UUID(), 'links.delete', 'Delete short links', 'links', NOW());

-- Grant to all authenticated users
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name IN ('owner', 'admin', 'editor', 'viewer') AND p.module = 'links';
