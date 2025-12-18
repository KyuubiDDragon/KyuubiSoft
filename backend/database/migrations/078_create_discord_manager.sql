-- Migration: Create Discord Manager tables
-- Version: 078
-- Description: Tables for Discord account management, chat backups, and message deletion

-- Discord Accounts (User Tokens)
CREATE TABLE IF NOT EXISTS discord_accounts (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    discord_user_id VARCHAR(20) NOT NULL,
    discord_username VARCHAR(100) NULL,
    discord_discriminator VARCHAR(10) NULL,
    discord_avatar VARCHAR(255) NULL,
    discord_email VARCHAR(255) NULL,
    token_encrypted TEXT NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_sync_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_discord_accounts_user (user_id),
    INDEX idx_discord_accounts_discord_user (discord_user_id),
    UNIQUE KEY unique_discord_per_user (user_id, discord_user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cached Discord Servers (Guilds)
CREATE TABLE IF NOT EXISTS discord_servers (
    id CHAR(36) NOT NULL PRIMARY KEY,
    account_id CHAR(36) NOT NULL,
    discord_guild_id VARCHAR(20) NOT NULL,
    name VARCHAR(255) NOT NULL,
    icon VARCHAR(255) NULL,
    owner_id VARCHAR(20) NULL,
    member_count INT UNSIGNED NULL,
    is_favorite BOOLEAN NOT NULL DEFAULT FALSE,
    cached_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_discord_servers_account (account_id),
    INDEX idx_discord_servers_guild (discord_guild_id),
    INDEX idx_discord_servers_favorite (is_favorite),
    UNIQUE KEY unique_server_per_account (account_id, discord_guild_id),
    FOREIGN KEY (account_id) REFERENCES discord_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cached Discord Channels
CREATE TABLE IF NOT EXISTS discord_channels (
    id CHAR(36) NOT NULL PRIMARY KEY,
    server_id CHAR(36) NULL,
    account_id CHAR(36) NOT NULL,
    discord_channel_id VARCHAR(20) NOT NULL,
    discord_guild_id VARCHAR(20) NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('text', 'voice', 'category', 'dm', 'group_dm', 'thread', 'forum', 'announcement') NOT NULL DEFAULT 'text',
    parent_id VARCHAR(20) NULL,
    position INT NOT NULL DEFAULT 0,
    recipient_username VARCHAR(100) NULL,
    recipient_avatar VARCHAR(255) NULL,
    is_favorite BOOLEAN NOT NULL DEFAULT FALSE,
    cached_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_discord_channels_server (server_id),
    INDEX idx_discord_channels_account (account_id),
    INDEX idx_discord_channels_channel (discord_channel_id),
    INDEX idx_discord_channels_type (type),
    UNIQUE KEY unique_channel_per_account (account_id, discord_channel_id),
    FOREIGN KEY (server_id) REFERENCES discord_servers(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES discord_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Discord Backup Jobs
CREATE TABLE IF NOT EXISTS discord_backups (
    id CHAR(36) NOT NULL PRIMARY KEY,
    account_id CHAR(36) NOT NULL,
    server_id CHAR(36) NULL,
    channel_id CHAR(36) NULL,

    -- What to backup
    discord_guild_id VARCHAR(20) NULL,
    discord_channel_id VARCHAR(20) NULL,
    target_name VARCHAR(255) NOT NULL,
    type ENUM('full_server', 'channel', 'dm', 'media_only') NOT NULL DEFAULT 'channel',

    -- Configuration
    include_media BOOLEAN NOT NULL DEFAULT TRUE,
    include_reactions BOOLEAN NOT NULL DEFAULT TRUE,
    include_threads BOOLEAN NOT NULL DEFAULT FALSE,
    include_embeds BOOLEAN NOT NULL DEFAULT TRUE,
    date_from DATETIME NULL,
    date_to DATETIME NULL,

    -- Status
    status ENUM('pending', 'running', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    current_action VARCHAR(255) NULL,

    -- Results
    messages_total INT UNSIGNED NOT NULL DEFAULT 0,
    messages_processed INT UNSIGNED NOT NULL DEFAULT 0,
    media_count INT UNSIGNED NOT NULL DEFAULT 0,
    media_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    file_path TEXT NULL,
    file_size BIGINT UNSIGNED NULL,
    error_message TEXT NULL,

    -- Timing
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_discord_backups_account (account_id),
    INDEX idx_discord_backups_status (status),
    INDEX idx_discord_backups_created (created_at),
    FOREIGN KEY (account_id) REFERENCES discord_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (server_id) REFERENCES discord_servers(id) ON DELETE SET NULL,
    FOREIGN KEY (channel_id) REFERENCES discord_channels(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Downloaded Discord Media
CREATE TABLE IF NOT EXISTS discord_media (
    id CHAR(36) NOT NULL PRIMARY KEY,
    backup_id CHAR(36) NOT NULL,
    discord_message_id VARCHAR(20) NOT NULL,
    discord_attachment_id VARCHAR(20) NULL,
    original_url TEXT NOT NULL,
    local_path TEXT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_size BIGINT UNSIGNED NULL,
    mime_type VARCHAR(100) NULL,
    width INT UNSIGNED NULL,
    height INT UNSIGNED NULL,
    is_spoiler BOOLEAN NOT NULL DEFAULT FALSE,
    downloaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_discord_media_backup (backup_id),
    INDEX idx_discord_media_message (discord_message_id),
    FOREIGN KEY (backup_id) REFERENCES discord_backups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backed up Messages (for search)
CREATE TABLE IF NOT EXISTS discord_messages (
    id CHAR(36) NOT NULL PRIMARY KEY,
    backup_id CHAR(36) NOT NULL,
    discord_message_id VARCHAR(20) NOT NULL,
    discord_channel_id VARCHAR(20) NOT NULL,
    discord_author_id VARCHAR(20) NOT NULL,
    author_username VARCHAR(100) NULL,
    author_avatar VARCHAR(255) NULL,
    content TEXT NULL,
    has_attachments BOOLEAN NOT NULL DEFAULT FALSE,
    has_embeds BOOLEAN NOT NULL DEFAULT FALSE,
    attachment_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    embed_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    reaction_count INT UNSIGNED NOT NULL DEFAULT 0,
    is_pinned BOOLEAN NOT NULL DEFAULT FALSE,
    is_edited BOOLEAN NOT NULL DEFAULT FALSE,
    message_type VARCHAR(50) NOT NULL DEFAULT 'DEFAULT',
    raw_data JSON NULL,
    message_timestamp DATETIME NOT NULL,
    edited_timestamp DATETIME NULL,

    INDEX idx_discord_messages_backup (backup_id),
    INDEX idx_discord_messages_channel (discord_channel_id),
    INDEX idx_discord_messages_author (discord_author_id),
    INDEX idx_discord_messages_timestamp (message_timestamp),
    FULLTEXT INDEX idx_discord_messages_content (content),
    FOREIGN KEY (backup_id) REFERENCES discord_backups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Message Delete Jobs
CREATE TABLE IF NOT EXISTS discord_delete_jobs (
    id CHAR(36) NOT NULL PRIMARY KEY,
    account_id CHAR(36) NOT NULL,
    discord_channel_id VARCHAR(20) NOT NULL,
    channel_name VARCHAR(255) NULL,
    server_name VARCHAR(255) NULL,

    -- Status
    status ENUM('pending', 'running', 'paused', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',

    -- Filter options
    date_from DATETIME NULL,
    date_to DATETIME NULL,
    keyword_filter VARCHAR(255) NULL,
    delete_attachments_only BOOLEAN NOT NULL DEFAULT FALSE,

    -- Progress
    total_messages INT UNSIGNED NOT NULL DEFAULT 0,
    deleted_messages INT UNSIGNED NOT NULL DEFAULT 0,
    failed_messages INT UNSIGNED NOT NULL DEFAULT 0,
    current_message_id VARCHAR(20) NULL,
    error_message TEXT NULL,

    -- Timing
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_discord_delete_jobs_account (account_id),
    INDEX idx_discord_delete_jobs_status (status),
    INDEX idx_discord_delete_jobs_created (created_at),
    FOREIGN KEY (account_id) REFERENCES discord_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Discord Manager Permissions
INSERT INTO permissions (name, description, module) VALUES
('discord.view', 'Discord Manager anzeigen', 'discord'),
('discord.manage_accounts', 'Discord Accounts verwalten', 'discord'),
('discord.create_backups', 'Discord Backups erstellen', 'discord'),
('discord.delete_backups', 'Discord Backups löschen', 'discord'),
('discord.view_messages', 'Gesicherte Nachrichten anzeigen', 'discord'),
('discord.delete_messages', 'Discord Nachrichten löschen', 'discord'),
('discord.download_media', 'Discord Medien herunterladen', 'discord')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Assign permissions to owner role
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'owner' AND p.module = 'discord'
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- Assign permissions to admin role
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'admin' AND p.module = 'discord'
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;
