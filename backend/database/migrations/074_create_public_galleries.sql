-- Migration: 074
-- Description: Create Public Link Galleries tables
-- Date: 2024-12-14

-- Public galleries for sharing collections of items
CREATE TABLE IF NOT EXISTS public_galleries (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    project_id VARCHAR(36) NULL,

    -- Gallery info
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    slug VARCHAR(100) NOT NULL,

    -- Display settings
    layout ENUM('grid', 'list', 'masonry', 'carousel') DEFAULT 'grid',
    theme ENUM('light', 'dark', 'auto', 'custom') DEFAULT 'auto',
    custom_css TEXT NULL,
    show_header TINYINT(1) DEFAULT 1,
    show_description TINYINT(1) DEFAULT 1,
    show_item_titles TINYINT(1) DEFAULT 1,
    show_item_descriptions TINYINT(1) DEFAULT 1,
    show_download_button TINYINT(1) DEFAULT 0,
    items_per_row INT DEFAULT 3,
    thumbnail_size ENUM('small', 'medium', 'large') DEFAULT 'medium',

    -- Cover/branding
    cover_image_url VARCHAR(500) NULL,
    logo_url VARCHAR(500) NULL,
    accent_color VARCHAR(7) DEFAULT '#6366f1',

    -- Access control
    is_public TINYINT(1) DEFAULT 1,
    is_password_protected TINYINT(1) DEFAULT 0,
    password_hash VARCHAR(255) NULL,
    require_email TINYINT(1) DEFAULT 0,
    allowed_emails JSON NULL,

    -- Expiration
    expires_at DATETIME NULL,
    max_views INT NULL,
    current_views INT DEFAULT 0,

    -- Tracking
    track_views TINYINT(1) DEFAULT 1,
    track_downloads TINYINT(1) DEFAULT 1,

    -- SEO
    meta_title VARCHAR(255) NULL,
    meta_description TEXT NULL,
    meta_image VARCHAR(500) NULL,
    allow_indexing TINYINT(1) DEFAULT 0,

    -- Status
    is_active TINYINT(1) DEFAULT 1,
    published_at DATETIME NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    UNIQUE KEY uk_gallery_slug (slug),
    INDEX idx_galleries_user (user_id),
    INDEX idx_galleries_project (project_id),
    INDEX idx_galleries_public (is_public),
    INDEX idx_galleries_active (is_active),
    INDEX idx_galleries_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gallery items (polymorphic - can link to documents, files, links, etc.)
CREATE TABLE IF NOT EXISTS public_gallery_items (
    id VARCHAR(36) PRIMARY KEY,
    gallery_id VARCHAR(36) NOT NULL,

    -- Item type and reference
    item_type ENUM('document', 'file', 'link', 'image', 'video', 'embed', 'custom') NOT NULL,
    item_id VARCHAR(36) NULL,

    -- Custom item content (when item_type = 'custom' or 'embed')
    title VARCHAR(255) NULL,
    description TEXT NULL,
    content TEXT NULL,
    url VARCHAR(500) NULL,
    thumbnail_url VARCHAR(500) NULL,

    -- Display options
    display_order INT DEFAULT 0,
    is_featured TINYINT(1) DEFAULT 0,
    is_visible TINYINT(1) DEFAULT 1,
    custom_thumbnail VARCHAR(500) NULL,
    custom_title VARCHAR(255) NULL,
    custom_description TEXT NULL,

    -- Item-specific settings
    allow_download TINYINT(1) DEFAULT 1,
    open_in_new_tab TINYINT(1) DEFAULT 0,
    embed_width VARCHAR(20) NULL,
    embed_height VARCHAR(20) NULL,

    -- Stats
    view_count INT DEFAULT 0,
    download_count INT DEFAULT 0,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (gallery_id) REFERENCES public_galleries(id) ON DELETE CASCADE,
    INDEX idx_gallery_items_gallery (gallery_id),
    INDEX idx_gallery_items_type (item_type),
    INDEX idx_gallery_items_order (display_order),
    INDEX idx_gallery_items_visible (is_visible)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gallery access logs
CREATE TABLE IF NOT EXISTS public_gallery_views (
    id VARCHAR(36) PRIMARY KEY,
    gallery_id VARCHAR(36) NOT NULL,
    item_id VARCHAR(36) NULL,

    -- Visitor info
    visitor_ip VARCHAR(45) NULL,
    visitor_country VARCHAR(2) NULL,
    visitor_city VARCHAR(100) NULL,
    user_agent TEXT NULL,
    referer VARCHAR(500) NULL,

    -- Access info
    access_type ENUM('view', 'download', 'item_view', 'item_download') DEFAULT 'view',
    email_provided VARCHAR(255) NULL,

    viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (gallery_id) REFERENCES public_galleries(id) ON DELETE CASCADE,
    INDEX idx_gallery_views_gallery (gallery_id),
    INDEX idx_gallery_views_item (item_id),
    INDEX idx_gallery_views_date (viewed_at),
    INDEX idx_gallery_views_type (access_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gallery categories/folders
CREATE TABLE IF NOT EXISTS public_gallery_categories (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#6366f1',
    icon VARCHAR(50) NULL,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_gallery_cats_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add category to galleries
ALTER TABLE public_galleries ADD COLUMN category_id VARCHAR(36) NULL AFTER project_id;
ALTER TABLE public_galleries ADD FOREIGN KEY (category_id) REFERENCES public_gallery_categories(id) ON DELETE SET NULL;
ALTER TABLE public_galleries ADD INDEX idx_galleries_category (category_id);
