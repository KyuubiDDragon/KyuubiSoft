-- Allow checklists to be linked to projects via the polymorphic project_links table
ALTER TABLE project_links
    MODIFY COLUMN linkable_type ENUM('document', 'list', 'kanban_board', 'connection', 'snippet', 'checklist') NOT NULL;
