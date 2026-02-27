CREATE TABLE IF NOT EXISTS kb_categories (
    id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    parent_id CHAR(36) DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    icon VARCHAR(50) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    is_published BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_kb_categories_user (user_id)
);

CREATE TABLE IF NOT EXISTS kb_articles (
    id CHAR(36) PRIMARY KEY,
    category_id CHAR(36) DEFAULT NULL,
    user_id CHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    content LONGTEXT DEFAULT NULL,
    excerpt TEXT DEFAULT NULL,
    tags JSON DEFAULT NULL,
    is_published BOOLEAN DEFAULT FALSE,
    view_count INT DEFAULT 0,
    helpful_count INT DEFAULT 0,
    not_helpful_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES kb_categories(id) ON DELETE SET NULL,
    INDEX idx_kb_articles_user (user_id),
    INDEX idx_kb_articles_category (category_id),
    FULLTEXT idx_kb_articles_search (title, content)
);

CREATE TABLE IF NOT EXISTS kb_article_ratings (
    id CHAR(36) PRIMARY KEY,
    article_id CHAR(36) NOT NULL,
    is_helpful BOOLEAN NOT NULL,
    feedback TEXT DEFAULT NULL,
    ip_hash VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_id) REFERENCES kb_articles(id) ON DELETE CASCADE,
    INDEX idx_kb_ratings_article (article_id)
);
