-- Add project_id to calendar_events for project-based filtering
ALTER TABLE calendar_events
ADD COLUMN project_id CHAR(36) NULL AFTER user_id,
ADD INDEX idx_calendar_events_project (project_id),
ADD CONSTRAINT fk_calendar_events_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;
