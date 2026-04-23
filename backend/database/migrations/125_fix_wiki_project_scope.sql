-- Ensures project_id columns on wiki_pages/wiki_categories are present.
-- Migration 124 bundled two ALTER TABLE statements; with this project's PDO
-- multi-statement behaviour the second one can be silently skipped
-- (see migration 119 for prior precedent). This migration is idempotent so
-- it is safe regardless of whether 124 fully or partially applied.

-- wiki_pages.project_id ------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'wiki_pages'
      AND COLUMN_NAME = 'project_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE wiki_pages ADD COLUMN project_id VARCHAR(36) NULL AFTER user_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'wiki_pages'
      AND INDEX_NAME = 'idx_wiki_pages_project'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_wiki_pages_project ON wiki_pages(project_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'wiki_pages'
      AND CONSTRAINT_NAME = 'fk_wiki_pages_project'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE wiki_pages ADD CONSTRAINT fk_wiki_pages_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- wiki_categories.project_id -------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'wiki_categories'
      AND COLUMN_NAME = 'project_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE wiki_categories ADD COLUMN project_id VARCHAR(36) NULL AFTER user_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'wiki_categories'
      AND INDEX_NAME = 'idx_wiki_categories_project'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_wiki_categories_project ON wiki_categories(project_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'wiki_categories'
      AND CONSTRAINT_NAME = 'fk_wiki_categories_project'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE wiki_categories ADD CONSTRAINT fk_wiki_categories_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
