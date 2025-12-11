-- Recurring Tasks System
-- Allows users to create tasks that repeat on a schedule

CREATE TABLE IF NOT EXISTS recurring_tasks (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,

    -- Recurrence settings
    frequency ENUM('daily', 'weekly', 'biweekly', 'monthly', 'yearly', 'custom') NOT NULL DEFAULT 'weekly',
    interval_value INT DEFAULT 1,

    -- For weekly: comma-separated days (0=Sunday, 1=Monday, etc.)
    days_of_week VARCHAR(50),

    -- For monthly: day of month (1-31) or 'last'
    day_of_month VARCHAR(10),

    -- For monthly: week of month (1-5) and day for "first Monday", etc.
    week_of_month INT,

    -- Start and end dates
    start_date DATE NOT NULL,
    end_date DATE,

    -- Time for the task
    due_time TIME,

    -- Task properties
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    estimated_duration INT,
    category_id VARCHAR(36),

    -- Target module to create tasks in
    target_type ENUM('list', 'checklist', 'kanban', 'project') DEFAULT 'list',
    target_id VARCHAR(36),

    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    last_generated_date DATE,
    next_occurrence DATE,

    -- Metadata
    color VARCHAR(7),
    icon VARCHAR(50),
    tags JSON,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_next_occurrence (next_occurrence)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories for recurring tasks
CREATE TABLE IF NOT EXISTS recurring_task_categories (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(50),
    color VARCHAR(7),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_category (user_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log of generated task instances
CREATE TABLE IF NOT EXISTS recurring_task_instances (
    id VARCHAR(36) PRIMARY KEY,
    recurring_task_id VARCHAR(36) NOT NULL,
    scheduled_date DATE NOT NULL,
    created_item_type VARCHAR(50),
    created_item_id VARCHAR(36),
    status ENUM('pending', 'created', 'skipped', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recurring_task_id) REFERENCES recurring_tasks(id) ON DELETE CASCADE,
    INDEX idx_task_date (recurring_task_id, scheduled_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
