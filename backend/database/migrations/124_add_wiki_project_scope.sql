-- Scope wiki pages and categories to projects

ALTER TABLE wiki_pages
    ADD COLUMN project_id VARCHAR(36) NULL AFTER user_id,
    ADD INDEX idx_wiki_pages_project (project_id),
    ADD CONSTRAINT fk_wiki_pages_project
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE;

ALTER TABLE wiki_categories
    ADD COLUMN project_id VARCHAR(36) NULL AFTER user_id,
    ADD INDEX idx_wiki_categories_project (project_id),
    ADD CONSTRAINT fk_wiki_categories_project
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE;
