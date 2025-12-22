-- Migration: Add pending role for unapproved users
-- Version: 086

-- Insert pending role with lowest hierarchy (cannot do anything)
INSERT INTO roles (name, description, is_system, hierarchy_level) VALUES
('pending', 'Nicht freigeschaltet - Wartet auf Genehmigung', TRUE, 0)
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Pending role gets NO permissions at all
-- Users with this role cannot access any features until approved by admin
