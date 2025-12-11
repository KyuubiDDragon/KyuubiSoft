-- AI Assistant Settings
-- Users must provide their own API keys - no default keys

CREATE TABLE IF NOT EXISTS ai_settings (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL UNIQUE,

    -- Provider settings (user must configure their own)
    provider ENUM('openai', 'anthropic', 'openrouter', 'ollama', 'custom') DEFAULT 'openai',
    api_key_encrypted TEXT COMMENT 'Encrypted API key',
    api_base_url VARCHAR(500) COMMENT 'Custom API URL for ollama/custom',
    model VARCHAR(100) DEFAULT 'gpt-4o-mini',

    -- Usage settings
    is_enabled BOOLEAN DEFAULT FALSE COMMENT 'Only enabled when API key is set',
    max_tokens INT DEFAULT 2000,
    temperature DECIMAL(3,2) DEFAULT 0.7,

    -- Usage tracking
    total_requests INT DEFAULT 0,
    total_tokens_used BIGINT DEFAULT 0,
    last_used_at TIMESTAMP NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_ai_settings_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI Chat History
CREATE TABLE IF NOT EXISTS ai_conversations (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    title VARCHAR(255),
    context_type VARCHAR(50) COMMENT 'document, list, kanban, etc.',
    context_id VARCHAR(36) COMMENT 'ID of the related item',
    model_used VARCHAR(100),
    is_archived BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_ai_conversations_user (user_id),
    INDEX idx_ai_conversations_context (context_type, context_id),

    CONSTRAINT fk_ai_conversations_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI Messages
CREATE TABLE IF NOT EXISTS ai_messages (
    id VARCHAR(36) PRIMARY KEY,
    conversation_id VARCHAR(36) NOT NULL,
    role ENUM('user', 'assistant', 'system') NOT NULL,
    content TEXT NOT NULL,
    tokens_used INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_ai_messages_conversation (conversation_id),

    CONSTRAINT fk_ai_messages_conversation
        FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI Prompts Library (user-defined prompts)
CREATE TABLE IF NOT EXISTS ai_prompts (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    prompt_template TEXT NOT NULL,
    category VARCHAR(50) DEFAULT 'general',
    is_default BOOLEAN DEFAULT FALSE,
    use_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_ai_prompts_user (user_id),
    INDEX idx_ai_prompts_category (user_id, category),

    CONSTRAINT fk_ai_prompts_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
