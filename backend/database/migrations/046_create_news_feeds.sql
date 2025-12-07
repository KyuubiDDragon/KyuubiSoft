-- News/RSS Feed system

-- Available RSS feeds (predefined + user-added)
CREATE TABLE IF NOT EXISTS news_feeds (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    url VARCHAR(500) NOT NULL,
    category ENUM('tech', 'gaming', 'general', 'dev', 'security', 'other') NOT NULL DEFAULT 'other',
    language VARCHAR(5) DEFAULT 'de',
    icon_url VARCHAR(500) NULL,
    is_system BOOLEAN NOT NULL DEFAULT FALSE,  -- System feeds can't be deleted
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_fetched_at DATETIME NULL,
    fetch_error VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_news_feeds_url (url)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User feed subscriptions
CREATE TABLE IF NOT EXISTS user_feed_subscriptions (
    user_id CHAR(36) NOT NULL,
    feed_id CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, feed_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (feed_id) REFERENCES news_feeds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cached feed items (articles)
CREATE TABLE IF NOT EXISTS news_items (
    id CHAR(36) PRIMARY KEY,
    feed_id CHAR(36) NOT NULL,
    guid VARCHAR(500) NOT NULL,  -- Unique identifier from RSS
    title VARCHAR(500) NOT NULL,
    description TEXT NULL,
    content TEXT NULL,
    url VARCHAR(1000) NOT NULL,
    image_url VARCHAR(1000) NULL,
    author VARCHAR(255) NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (feed_id) REFERENCES news_feeds(id) ON DELETE CASCADE,
    UNIQUE KEY idx_news_items_guid (feed_id, guid),
    INDEX idx_news_items_published (published_at DESC),
    INDEX idx_news_items_feed_published (feed_id, published_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User read/saved articles
CREATE TABLE IF NOT EXISTS user_news_interactions (
    user_id CHAR(36) NOT NULL,
    item_id CHAR(36) NOT NULL,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    is_saved BOOLEAN NOT NULL DEFAULT FALSE,
    read_at DATETIME NULL,
    saved_at DATETIME NULL,
    PRIMARY KEY (user_id, item_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES news_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default system feeds
INSERT INTO news_feeds (id, name, url, category, language, is_system) VALUES
-- Tech (DE)
(UUID(), 'Heise Online', 'https://www.heise.de/rss/heise.rdf', 'tech', 'de', TRUE),
(UUID(), 'Golem.de', 'https://rss.golem.de/rss.php?feed=ATOM1.0', 'tech', 'de', TRUE),
(UUID(), 't3n', 'https://t3n.de/rss.xml', 'tech', 'de', TRUE),

-- Tech (EN)
(UUID(), 'Hacker News', 'https://news.ycombinator.com/rss', 'tech', 'en', TRUE),
(UUID(), 'The Verge', 'https://www.theverge.com/rss/index.xml', 'tech', 'en', TRUE),
(UUID(), 'Ars Technica', 'https://feeds.arstechnica.com/arstechnica/index', 'tech', 'en', TRUE),
(UUID(), 'TechCrunch', 'https://techcrunch.com/feed/', 'tech', 'en', TRUE),

-- General News (DE)
(UUID(), 'Tagesschau', 'https://www.tagesschau.de/xml/rss2/', 'general', 'de', TRUE),
(UUID(), 'Spiegel Online', 'https://www.spiegel.de/schlagzeilen/index.rss', 'general', 'de', TRUE),

-- Dev/Programming
(UUID(), 'DEV.to', 'https://dev.to/feed', 'dev', 'en', TRUE),
(UUID(), 'CSS-Tricks', 'https://css-tricks.com/feed/', 'dev', 'en', TRUE),
(UUID(), 'Smashing Magazine', 'https://www.smashingmagazine.com/feed/', 'dev', 'en', TRUE),
(UUID(), 'JavaScript Weekly', 'https://javascriptweekly.com/rss/', 'dev', 'en', TRUE),

-- Gaming
(UUID(), 'GameStar', 'https://www.gamestar.de/rss/gamestar.rss', 'gaming', 'de', TRUE),
(UUID(), 'PC Games', 'https://www.pcgames.de/feed.cfm?menu_alias=pcgames', 'gaming', 'de', TRUE),
(UUID(), 'IGN', 'https://feeds.feedburner.com/ign/all', 'gaming', 'en', TRUE),
(UUID(), 'Kotaku', 'https://kotaku.com/rss', 'gaming', 'en', TRUE),

-- Security
(UUID(), 'Krebs on Security', 'https://krebsonsecurity.com/feed/', 'security', 'en', TRUE),
(UUID(), 'The Hacker News', 'https://feeds.feedburner.com/TheHackersNews', 'security', 'en', TRUE),
(UUID(), 'Naked Security', 'https://nakedsecurity.sophos.com/feed/', 'security', 'en', TRUE);
