-- Migration: Add Discord Bot Support
-- Version: 081
-- Description: Tables for Discord Bot integration as alternative to user tokens

-- Discord Bots
CREATE TABLE IF NOT EXISTS discord_bots (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,

    -- Bot Identification
    client_id VARCHAR(20) NOT NULL,
    client_secret_encrypted TEXT NULL,
    bot_token_encrypted TEXT NOT NULL,

    -- Bot Info (from Discord API)
    bot_user_id VARCHAR(20) NULL,
    bot_username VARCHAR(100) NULL,
    bot_discriminator VARCHAR(10) NULL,
    bot_avatar VARCHAR(255) NULL,

    -- Status
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    is_public BOOLEAN NOT NULL DEFAULT FALSE,
    last_sync_at DATETIME NULL,

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_discord_bots_user (user_id),
    INDEX idx_discord_bots_client (client_id),
    UNIQUE KEY unique_bot_per_user (user_id, client_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Discord Bot Servers (Guilds where bot is invited)
CREATE TABLE IF NOT EXISTS discord_bot_servers (
    id CHAR(36) NOT NULL PRIMARY KEY,
    bot_id CHAR(36) NOT NULL,

    -- Discord Guild Info
    discord_guild_id VARCHAR(20) NOT NULL,
    name VARCHAR(255) NOT NULL,
    icon VARCHAR(255) NULL,
    owner_id VARCHAR(20) NULL,
    member_count INT UNSIGNED NULL,

    -- Bot Permissions in this server
    permissions BIGINT UNSIGNED NOT NULL DEFAULT 0,

    -- Features
    is_favorite BOOLEAN NOT NULL DEFAULT FALSE,
    auto_backup_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    auto_backup_interval ENUM('daily', 'weekly', 'monthly') NULL,
    last_backup_at DATETIME NULL,

    -- Status
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cached_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_bot_servers_bot (bot_id),
    INDEX idx_bot_servers_guild (discord_guild_id),
    INDEX idx_bot_servers_favorite (is_favorite),
    UNIQUE KEY unique_server_per_bot (bot_id, discord_guild_id),
    FOREIGN KEY (bot_id) REFERENCES discord_bots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Discord Bot Channels (cached channels for bot servers)
CREATE TABLE IF NOT EXISTS discord_bot_channels (
    id CHAR(36) NOT NULL PRIMARY KEY,
    bot_server_id CHAR(36) NOT NULL,
    bot_id CHAR(36) NOT NULL,

    discord_channel_id VARCHAR(20) NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('text', 'voice', 'category', 'thread', 'forum', 'announcement', 'stage') NOT NULL DEFAULT 'text',
    parent_id VARCHAR(20) NULL,
    position INT NOT NULL DEFAULT 0,
    topic TEXT NULL,

    -- Permissions
    permission_overwrites JSON NULL,

    cached_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_bot_channels_server (bot_server_id),
    INDEX idx_bot_channels_bot (bot_id),
    INDEX idx_bot_channels_discord (discord_channel_id),
    UNIQUE KEY unique_channel_per_bot (bot_id, discord_channel_id),
    FOREIGN KEY (bot_server_id) REFERENCES discord_bot_servers(id) ON DELETE CASCADE,
    FOREIGN KEY (bot_id) REFERENCES discord_bots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backup Schedules
CREATE TABLE IF NOT EXISTS discord_backup_schedules (
    id CHAR(36) NOT NULL PRIMARY KEY,
    bot_id CHAR(36) NOT NULL,
    bot_server_id CHAR(36) NOT NULL,

    -- Schedule
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    interval_type ENUM('daily', 'weekly', 'monthly') NOT NULL DEFAULT 'weekly',
    day_of_week TINYINT UNSIGNED NULL,
    day_of_month TINYINT UNSIGNED NULL,
    time_of_day TIME NOT NULL DEFAULT '03:00:00',

    -- Backup Settings
    include_media BOOLEAN NOT NULL DEFAULT TRUE,
    include_threads BOOLEAN NOT NULL DEFAULT TRUE,
    include_roles BOOLEAN NOT NULL DEFAULT TRUE,
    include_emojis BOOLEAN NOT NULL DEFAULT TRUE,

    -- Retention
    keep_last_n INT UNSIGNED NOT NULL DEFAULT 7,

    -- Tracking
    last_run_at DATETIME NULL,
    next_run_at DATETIME NULL,
    last_backup_id CHAR(36) NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_schedules_bot (bot_id),
    INDEX idx_schedules_next_run (next_run_at),
    INDEX idx_schedules_active (is_active),
    FOREIGN KEY (bot_id) REFERENCES discord_bots(id) ON DELETE CASCADE,
    FOREIGN KEY (bot_server_id) REFERENCES discord_bot_servers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Server Settings Backup (for restore)
CREATE TABLE IF NOT EXISTS discord_server_settings (
    id CHAR(36) NOT NULL PRIMARY KEY,
    backup_id CHAR(36) NOT NULL,

    -- Server Settings
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    icon_hash VARCHAR(255) NULL,
    icon_local_path TEXT NULL,
    splash_hash VARCHAR(255) NULL,
    splash_local_path TEXT NULL,
    banner_hash VARCHAR(255) NULL,
    banner_local_path TEXT NULL,

    -- Features & Settings
    features JSON NULL,
    verification_level TINYINT UNSIGNED NULL,
    default_notifications TINYINT UNSIGNED NULL,
    explicit_content_filter TINYINT UNSIGNED NULL,
    afk_channel_id VARCHAR(20) NULL,
    afk_timeout INT UNSIGNED NULL,
    system_channel_id VARCHAR(20) NULL,
    rules_channel_id VARCHAR(20) NULL,

    -- Full raw data
    raw_data JSON NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_server_settings_backup (backup_id),
    FOREIGN KEY (backup_id) REFERENCES discord_backups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roles Backup
CREATE TABLE IF NOT EXISTS discord_roles (
    id CHAR(36) NOT NULL PRIMARY KEY,
    backup_id CHAR(36) NOT NULL,

    discord_role_id VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    color INT UNSIGNED NOT NULL DEFAULT 0,
    hoist BOOLEAN NOT NULL DEFAULT FALSE,
    icon VARCHAR(255) NULL,
    unicode_emoji VARCHAR(50) NULL,
    position INT NOT NULL DEFAULT 0,
    permissions BIGINT UNSIGNED NOT NULL DEFAULT 0,
    managed BOOLEAN NOT NULL DEFAULT FALSE,
    mentionable BOOLEAN NOT NULL DEFAULT FALSE,

    raw_data JSON NULL,

    INDEX idx_roles_backup (backup_id),
    INDEX idx_roles_discord_id (discord_role_id),
    FOREIGN KEY (backup_id) REFERENCES discord_backups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Emojis Backup
CREATE TABLE IF NOT EXISTS discord_emojis (
    id CHAR(36) NOT NULL PRIMARY KEY,
    backup_id CHAR(36) NOT NULL,

    discord_emoji_id VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    animated BOOLEAN NOT NULL DEFAULT FALSE,
    available BOOLEAN NOT NULL DEFAULT TRUE,
    require_colons BOOLEAN NOT NULL DEFAULT TRUE,
    managed BOOLEAN NOT NULL DEFAULT FALSE,

    -- Local storage
    original_url TEXT NOT NULL,
    local_path TEXT NULL,

    INDEX idx_emojis_backup (backup_id),
    FOREIGN KEY (backup_id) REFERENCES discord_backups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add bot_id to discord_backups
ALTER TABLE discord_backups
    ADD COLUMN bot_id CHAR(36) NULL AFTER account_id,
    ADD COLUMN source_type ENUM('user_token', 'bot') NOT NULL DEFAULT 'user_token' AFTER type;

-- Add index and foreign key (separate statements for compatibility)
CREATE INDEX idx_discord_backups_bot ON discord_backups(bot_id);
CREATE INDEX idx_discord_backups_source ON discord_backups(source_type);

-- New permissions for bot management
INSERT INTO permissions (name, description, module) VALUES
('discord.manage_bots', 'Discord Bots verwalten', 'discord'),
('discord.restore_servers', 'Discord Server wiederherstellen', 'discord')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Assign new permissions to owner role
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'owner' AND p.name IN ('discord.manage_bots', 'discord.restore_servers')
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- Assign new permissions to admin role
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.name = 'admin' AND p.name IN ('discord.manage_bots', 'discord.restore_servers')
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;
