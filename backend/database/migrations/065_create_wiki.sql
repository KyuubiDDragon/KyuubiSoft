-- Markdown Wiki / Knowledge Base
-- Interconnected notes with wiki-style linking

-- Wiki pages
CREATE TABLE IF NOT EXISTS wiki_pages (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT,
    excerpt TEXT COMMENT 'Auto-generated excerpt',
    icon VARCHAR(50),
    cover_image VARCHAR(500),
    is_published BOOLEAN DEFAULT FALSE,
    is_pinned BOOLEAN DEFAULT FALSE,
    parent_id VARCHAR(36) COMMENT 'For hierarchical structure',
    category_id VARCHAR(36),
    view_count INT DEFAULT 0,
    word_count INT DEFAULT 0,
    reading_time INT DEFAULT 0 COMMENT 'Estimated minutes',
    last_edited_by VARCHAR(36),
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_user_slug (user_id, slug),
    INDEX idx_wiki_pages_user (user_id),
    INDEX idx_wiki_pages_category (category_id),
    INDEX idx_wiki_pages_parent (parent_id),
    INDEX idx_wiki_pages_published (user_id, is_published),
    FULLTEXT INDEX ft_wiki_pages (title, content),

    CONSTRAINT fk_wiki_pages_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_wiki_pages_parent
        FOREIGN KEY (parent_id) REFERENCES wiki_pages(id) ON DELETE SET NULL,
    CONSTRAINT fk_wiki_pages_editor
        FOREIGN KEY (last_edited_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wiki categories
CREATE TABLE IF NOT EXISTS wiki_categories (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(20) DEFAULT '#6366f1',
    icon VARCHAR(50),
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_user_slug (user_id, slug),
    INDEX idx_wiki_categories_user (user_id),

    CONSTRAINT fk_wiki_categories_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add category foreign key after category table exists
ALTER TABLE wiki_pages ADD CONSTRAINT fk_wiki_pages_category
    FOREIGN KEY (category_id) REFERENCES wiki_categories(id) ON DELETE SET NULL;

-- Wiki links (for backlinks and graph view)
CREATE TABLE IF NOT EXISTS wiki_links (
    id VARCHAR(36) PRIMARY KEY,
    source_page_id VARCHAR(36) NOT NULL,
    target_page_id VARCHAR(36) NOT NULL,
    link_text VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_source_target (source_page_id, target_page_id),
    INDEX idx_wiki_links_source (source_page_id),
    INDEX idx_wiki_links_target (target_page_id),

    CONSTRAINT fk_wiki_links_source
        FOREIGN KEY (source_page_id) REFERENCES wiki_pages(id) ON DELETE CASCADE,
    CONSTRAINT fk_wiki_links_target
        FOREIGN KEY (target_page_id) REFERENCES wiki_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wiki page tags
CREATE TABLE IF NOT EXISTS wiki_page_tags (
    page_id VARCHAR(36) NOT NULL,
    tag_id VARCHAR(36) NOT NULL,

    PRIMARY KEY (page_id, tag_id),

    CONSTRAINT fk_wiki_page_tags_page
        FOREIGN KEY (page_id) REFERENCES wiki_pages(id) ON DELETE CASCADE,
    CONSTRAINT fk_wiki_page_tags_tag
        FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wiki page history (for version control)
CREATE TABLE IF NOT EXISTS wiki_page_history (
    id VARCHAR(36) PRIMARY KEY,
    page_id VARCHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT,
    changed_by VARCHAR(36) NOT NULL,
    change_note TEXT,
    version_number INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_wiki_page_history (page_id, created_at),

    CONSTRAINT fk_wiki_page_history_page
        FOREIGN KEY (page_id) REFERENCES wiki_pages(id) ON DELETE CASCADE,
    CONSTRAINT fk_wiki_page_history_user
        FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
