-- Add context and tools settings to AI settings
ALTER TABLE ai_settings
    ADD COLUMN context_enabled BOOLEAN DEFAULT TRUE COMMENT 'Include user data (projects, tasks, etc.) in AI context',
    ADD COLUMN tools_enabled BOOLEAN DEFAULT TRUE COMMENT 'Enable system tools (Docker, Server info, etc.)';
