-- Add article-level category for better content-based categorization
ALTER TABLE news_items ADD COLUMN article_category ENUM('tech', 'gaming', 'general', 'dev', 'security', 'hardware', 'software', 'mobile', 'ai', 'science', 'entertainment', 'business', 'other') NULL AFTER feed_id;

CREATE INDEX idx_news_items_article_category ON news_items(article_category);
