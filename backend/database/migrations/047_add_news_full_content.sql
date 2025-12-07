-- Add full_content column for caching extracted article content
ALTER TABLE news_items ADD COLUMN full_content MEDIUMTEXT NULL AFTER content;
