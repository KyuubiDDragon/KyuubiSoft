-- Migration: Create Note Databases System (Phase 2)
-- Version: 076
-- Description: Inline databases for notes (Notion-style)

-- =====================================================
-- NOTE_DATABASES: Database definitions within notes
-- =====================================================
CREATE TABLE IF NOT EXISTS note_databases (
    id CHAR(36) NOT NULL PRIMARY KEY,
    note_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL DEFAULT 'Datenbank',
    description TEXT NULL,
    icon VARCHAR(50) NULL DEFAULT NULL,

    -- View settings
    default_view ENUM('table', 'board', 'list', 'calendar', 'gallery') NOT NULL DEFAULT 'table',
    board_group_by CHAR(36) NULL,
    calendar_date_property CHAR(36) NULL,
    gallery_cover_property CHAR(36) NULL,

    -- Sorting and filtering (JSON)
    sort_config JSON NULL,
    filter_config JSON NULL,

    -- Display settings
    show_title BOOLEAN NOT NULL DEFAULT TRUE,
    full_width BOOLEAN NOT NULL DEFAULT FALSE,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_note_databases_note (note_id),
    INDEX idx_note_databases_user (user_id),

    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NOTE_DATABASE_PROPERTIES: Column/property definitions
-- =====================================================
CREATE TABLE IF NOT EXISTS note_database_properties (
    id CHAR(36) NOT NULL PRIMARY KEY,
    database_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM(
        'text',
        'number',
        'select',
        'multi_select',
        'date',
        'checkbox',
        'url',
        'email',
        'phone',
        'person',
        'relation',
        'formula',
        'rollup',
        'created_time',
        'updated_time',
        'created_by',
        'updated_by'
    ) NOT NULL DEFAULT 'text',

    -- Property configuration (JSON for flexibility)
    config JSON NULL,
    -- For select/multi_select: { "options": [{ "id": "uuid", "name": "Option", "color": "red" }] }
    -- For number: { "format": "number|currency|percent", "precision": 2 }
    -- For date: { "format": "date|datetime", "include_time": false }
    -- For relation: { "database_id": "uuid", "synced_property_id": "uuid" }
    -- For formula: { "expression": "prop('Price') * prop('Quantity')" }
    -- For rollup: { "relation_property_id": "uuid", "rollup_property_id": "uuid", "function": "sum|count|avg" }

    -- Display settings
    width INT NULL DEFAULT 200,
    is_visible BOOLEAN NOT NULL DEFAULT TRUE,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    sort_order INT NOT NULL DEFAULT 0,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_database_properties_db (database_id),
    INDEX idx_database_properties_order (database_id, sort_order),

    FOREIGN KEY (database_id) REFERENCES note_databases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NOTE_DATABASE_ROWS: Row entries in databases
-- =====================================================
CREATE TABLE IF NOT EXISTS note_database_rows (
    id CHAR(36) NOT NULL PRIMARY KEY,
    database_id CHAR(36) NOT NULL,

    -- Optional link to a note (row can be a page)
    linked_note_id CHAR(36) NULL,

    -- Row metadata
    sort_order INT NOT NULL DEFAULT 0,
    is_archived BOOLEAN NOT NULL DEFAULT FALSE,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by CHAR(36) NOT NULL,
    updated_by CHAR(36) NOT NULL,

    INDEX idx_database_rows_db (database_id),
    INDEX idx_database_rows_order (database_id, sort_order),
    INDEX idx_database_rows_archived (database_id, is_archived),
    INDEX idx_database_rows_linked (linked_note_id),

    FOREIGN KEY (database_id) REFERENCES note_databases(id) ON DELETE CASCADE,
    FOREIGN KEY (linked_note_id) REFERENCES notes(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NOTE_DATABASE_CELLS: Cell values for each row/property
-- =====================================================
CREATE TABLE IF NOT EXISTS note_database_cells (
    id CHAR(36) NOT NULL PRIMARY KEY,
    row_id CHAR(36) NOT NULL,
    property_id CHAR(36) NOT NULL,

    -- Value storage (use appropriate column based on property type)
    value_text TEXT NULL,
    value_number DECIMAL(20, 6) NULL,
    value_boolean BOOLEAN NULL,
    value_date DATETIME NULL,
    value_date_end DATETIME NULL,  -- For date ranges
    value_json JSON NULL,  -- For multi_select, relation, person arrays

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_database_cells_unique (row_id, property_id),
    INDEX idx_database_cells_property (property_id),
    INDEX idx_database_cells_text (value_text(100)),
    INDEX idx_database_cells_number (value_number),
    INDEX idx_database_cells_date (value_date),
    INDEX idx_database_cells_boolean (value_boolean),

    FOREIGN KEY (row_id) REFERENCES note_database_rows(id) ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES note_database_properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NOTE_DATABASE_VIEWS: Saved views for databases
-- =====================================================
CREATE TABLE IF NOT EXISTS note_database_views (
    id CHAR(36) NOT NULL PRIMARY KEY,
    database_id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('table', 'board', 'list', 'calendar', 'gallery') NOT NULL DEFAULT 'table',

    -- View-specific settings
    config JSON NOT NULL,
    -- Table: { "visible_properties": [], "column_widths": {}, "row_height": "small|medium|large" }
    -- Board: { "group_by": "property_id", "hide_empty_groups": false }
    -- Calendar: { "date_property": "property_id", "show_weekends": true }
    -- Gallery: { "cover_property": "property_id", "card_size": "small|medium|large" }

    -- Sorting and filtering
    sort_config JSON NULL,
    filter_config JSON NULL,

    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    sort_order INT NOT NULL DEFAULT 0,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_database_views_db (database_id),
    INDEX idx_database_views_order (database_id, sort_order),

    FOREIGN KEY (database_id) REFERENCES note_databases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Add feature flags for databases
-- =====================================================
INSERT IGNORE INTO feature_flags (id, name, description, is_enabled, created_at) VALUES
(UUID(), 'notes.databases', 'Enable inline databases in notes', TRUE, NOW()),
(UUID(), 'notes.databases.board_view', 'Enable Kanban board view', TRUE, NOW()),
(UUID(), 'notes.databases.calendar_view', 'Enable calendar view', TRUE, NOW()),
(UUID(), 'notes.databases.gallery_view', 'Enable gallery view', TRUE, NOW());
