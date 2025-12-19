# Discord Bot Integration - Konzept

## 1. Übersicht

### Motivation
Aktuell verwendet der Discord Manager nur User Tokens, was folgende Nachteile hat:
- **ToS-Grauzone**: Self-botting verstößt technisch gegen Discord ToS
- **Token-Invalidierung**: User Tokens können jederzeit invalidiert werden
- **Kein DM-Zugriff ohne Berechtigung**: Zugriff nur auf eigene Inhalte

Ein Discord Bot bietet eine **offizielle, ToS-konforme Alternative** für Server-Backups.

### Anwendungsfälle

| Funktion | User Token | Bot |
|----------|-----------|-----|
| Server-Backup | ✅ Alle Server | ✅ Nur eingeladene Server |
| DM-Backup | ✅ Ja | ❌ Nein |
| Nachrichten löschen | ✅ Eigene | ✅ Bot-Nachrichten + Manage Messages |
| Media download | ✅ Ja | ✅ Ja |
| Mitgliederliste | ✅ Ja | ✅ Mit Intents |
| Server-Einstellungen | ❌ Limitiert | ✅ Mit Permissions |
| Rollen sichern | ⚠️ Partial | ✅ Vollständig |
| Emojis sichern | ⚠️ Partial | ✅ Vollständig |
| Webhooks sichern | ❌ Nein | ✅ Mit Permissions |

---

## 2. Bot-Funktionen

### 2.1 Server Backup (ToS-konform)
- **Alle Channels** auslesen (Text, Voice, Forum, Announcement)
- **Channel-Struktur** (Kategorien, Positionen, Permissions)
- **Nachrichten-Backup** mit allen Attachments
- **Thread-Backup** (aktive und archivierte)
- **Server-Einstellungen** (Name, Icon, Banner, Region)
- **Rollen** (Name, Farbe, Permissions, Position)
- **Emojis & Sticker** mit Download
- **Webhooks** (URL, Avatar, Name)
- **Audit Logs** (letzte Aktionen)

### 2.2 Server Restore / Clone
- Neuen Server erstellen mit gesicherten Einstellungen
- Channels und Kategorien wiederherstellen
- Rollen mit Permissions wiederherstellen
- Emojis hochladen
- Webhooks neu erstellen
- **Optional**: Nachrichten über Webhooks "replizieren"

### 2.3 Scheduled Backups
- Automatische tägliche/wöchentliche Backups
- Inkrementelle Backups (nur neue Nachrichten)
- Retention Policy (alte Backups löschen)

### 2.4 Live-Monitoring (Optional - Phase 2)
- Nachrichten in Echtzeit mitloggen (Gateway)
- Gelöschte Nachrichten erfassen (MESSAGE_DELETE Event)
- Member Join/Leave tracken

### 2.5 Moderation-Tools (Optional - Phase 3)
- Bulk-Delete von Nachrichten
- Auto-Moderation Regeln
- Keyword-Filter

---

## 3. Datenbank-Schema

### 3.1 Neue Tabelle: `discord_bots`

```sql
CREATE TABLE IF NOT EXISTS discord_bots (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,

    -- Bot Identifikation
    client_id VARCHAR(20) NOT NULL,
    client_secret_encrypted TEXT NOT NULL,
    bot_token_encrypted TEXT NOT NULL,

    -- Bot Info (von Discord API)
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
```

### 3.2 Neue Tabelle: `discord_bot_servers`

```sql
CREATE TABLE IF NOT EXISTS discord_bot_servers (
    id CHAR(36) NOT NULL PRIMARY KEY,
    bot_id CHAR(36) NOT NULL,

    -- Discord Guild Info
    discord_guild_id VARCHAR(20) NOT NULL,
    name VARCHAR(255) NOT NULL,
    icon VARCHAR(255) NULL,
    owner_id VARCHAR(20) NULL,
    member_count INT UNSIGNED NULL,

    -- Bot Permissions in diesem Server
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
    UNIQUE KEY unique_server_per_bot (bot_id, discord_guild_id),
    FOREIGN KEY (bot_id) REFERENCES discord_bots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.3 Erweiterung: `discord_backups`

```sql
ALTER TABLE discord_backups
    ADD COLUMN bot_id CHAR(36) NULL AFTER account_id,
    ADD COLUMN source_type ENUM('user_token', 'bot') NOT NULL DEFAULT 'user_token' AFTER type,
    ADD FOREIGN KEY (bot_id) REFERENCES discord_bots(id) ON DELETE SET NULL;

-- Index für Bot-Backups
CREATE INDEX idx_discord_backups_bot ON discord_backups(bot_id);
```

### 3.4 Neue Tabelle: `discord_backup_schedules`

```sql
CREATE TABLE IF NOT EXISTS discord_backup_schedules (
    id CHAR(36) NOT NULL PRIMARY KEY,
    bot_id CHAR(36) NOT NULL,
    bot_server_id CHAR(36) NOT NULL,

    -- Schedule
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    interval_type ENUM('daily', 'weekly', 'monthly') NOT NULL DEFAULT 'weekly',
    day_of_week TINYINT UNSIGNED NULL, -- 0-6 für weekly
    day_of_month TINYINT UNSIGNED NULL, -- 1-31 für monthly
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
    FOREIGN KEY (bot_id) REFERENCES discord_bots(id) ON DELETE CASCADE,
    FOREIGN KEY (bot_server_id) REFERENCES discord_bot_servers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.5 Neue Tabelle: `discord_server_settings` (Backup von Server-Settings)

```sql
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
```

### 3.6 Neue Tabelle: `discord_roles` (Backup von Rollen)

```sql
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
```

### 3.7 Neue Tabelle: `discord_emojis` (Backup von Emojis)

```sql
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
```

---

## 4. API Endpoints

### 4.1 Bot Management

```
POST   /api/v1/discord/bots                     - Bot hinzufügen
GET    /api/v1/discord/bots                     - Alle Bots des Users
GET    /api/v1/discord/bots/{id}                - Bot Details
PUT    /api/v1/discord/bots/{id}                - Bot aktualisieren
DELETE /api/v1/discord/bots/{id}                - Bot entfernen

POST   /api/v1/discord/bots/{id}/validate       - Token validieren
POST   /api/v1/discord/bots/{id}/sync           - Server-Liste aktualisieren
```

### 4.2 Bot Server Management

```
GET    /api/v1/discord/bots/{botId}/servers                - Alle Server des Bots
GET    /api/v1/discord/bots/{botId}/servers/{serverId}     - Server Details
POST   /api/v1/discord/bots/{botId}/servers/{serverId}/sync - Server-Channels aktualisieren

# Invite URL generieren
GET    /api/v1/discord/bots/{botId}/invite?permissions=...  - OAuth2 Invite URL
```

### 4.3 Bot Backups

```
POST   /api/v1/discord/bots/{botId}/backups                - Backup starten
GET    /api/v1/discord/bots/{botId}/backups                - Alle Backups des Bots
GET    /api/v1/discord/bots/{botId}/backups/{backupId}     - Backup Details

# Scheduled Backups
GET    /api/v1/discord/bots/{botId}/schedules              - Alle Schedules
POST   /api/v1/discord/bots/{botId}/schedules              - Schedule erstellen
PUT    /api/v1/discord/bots/{botId}/schedules/{id}         - Schedule bearbeiten
DELETE /api/v1/discord/bots/{botId}/schedules/{id}         - Schedule löschen
```

### 4.4 Server Restore

```
POST   /api/v1/discord/bots/{botId}/restore/{backupId}     - Server wiederherstellen
GET    /api/v1/discord/bots/{botId}/restore/{jobId}/status - Restore-Status
```

---

## 5. Backend Services

### 5.1 DiscordBotApiService.php

```php
class DiscordBotApiService
{
    // Unterschied zu User API: Authorization Header Format
    // Bot: "Bot {token}"
    // User: "{token}"

    // Gateway Intents für bestimmte Features
    private const INTENTS = [
        'GUILDS' => 1 << 0,
        'GUILD_MEMBERS' => 1 << 1,         // Privileged Intent!
        'GUILD_MESSAGES' => 1 << 9,
        'MESSAGE_CONTENT' => 1 << 15,      // Privileged Intent!
    ];

    public function getGuild(string $token, string $guildId): array;
    public function getGuildChannels(string $token, string $guildId): array;
    public function getGuildRoles(string $token, string $guildId): array;
    public function getGuildEmojis(string $token, string $guildId): array;
    public function getGuildMembers(string $token, string $guildId, int $limit = 1000): array;
    public function getGuildAuditLog(string $token, string $guildId): array;
    public function getChannelWebhooks(string $token, string $channelId): array;

    // Bot-spezifische Funktionen
    public function getCurrentBotApplication(string $token): array;
    public function getGatewayBot(string $token): array; // Rate Limits prüfen

    // Server Restore
    public function createGuild(string $token, array $data): array;
    public function createRole(string $token, string $guildId, array $data): array;
    public function createChannel(string $token, string $guildId, array $data): array;
    public function createEmoji(string $token, string $guildId, array $data): array;
    public function createWebhook(string $token, string $channelId, array $data): array;
}
```

### 5.2 DiscordBotBackupService.php

```php
class DiscordBotBackupService
{
    public function createFullServerBackup(string $botId, string $guildId, array $options): string;
    public function backupServerSettings(string $backupId, array $guildData): void;
    public function backupRoles(string $backupId, string $guildId): void;
    public function backupEmojis(string $backupId, string $guildId): void;
    public function backupChannels(string $backupId, string $guildId): void;
    public function backupMessages(string $backupId, string $channelId, array $options): void;
}
```

### 5.3 DiscordBotRestoreService.php

```php
class DiscordBotRestoreService
{
    public function restoreServer(string $botId, string $backupId, array $options): string;
    public function restoreRoles(string $jobId): void;
    public function restoreChannels(string $jobId): void;
    public function restoreEmojis(string $jobId): void;
    public function restoreWebhooks(string $jobId): void;
}
```

---

## 6. Frontend UI

### 6.1 Bot Management Tab

```
Discord Manager
├── Accounts (bestehend - User Tokens)
├── Bots (NEU)
│   ├── Bot hinzufügen
│   │   ├── Client ID eingeben
│   │   ├── Client Secret eingeben
│   │   └── Bot Token eingeben
│   ├── Bot-Liste
│   │   ├── Bot Avatar & Name
│   │   ├── Server-Anzahl
│   │   ├── Status (Online/Offline)
│   │   └── Aktionen (Sync, Invite, Delete)
│   └── Server-Verwaltung (pro Bot)
│       ├── Server-Liste mit Permissions
│       ├── Auto-Backup Toggle
│       └── Backup-Schedule konfigurieren
├── Servers (kombiniert User & Bot)
├── Backups (kombiniert User & Bot)
└── Media Gallery
```

### 6.2 Bot Server View

```vue
<template>
  <div class="bot-server-view">
    <!-- Server Header -->
    <div class="server-header">
      <img :src="server.icon_url" />
      <h2>{{ server.name }}</h2>
      <span class="member-count">{{ server.member_count }} Members</span>
      <span class="permissions">{{ formatPermissions(server.permissions) }}</span>
    </div>

    <!-- Quick Actions -->
    <div class="actions">
      <button @click="startBackup">Full Server Backup</button>
      <button @click="openScheduleModal">Schedule Backup</button>
      <button @click="syncServer">Refresh Channels</button>
    </div>

    <!-- Backup Options -->
    <div class="backup-options">
      <label><input type="checkbox" v-model="includeMessages"> Messages</label>
      <label><input type="checkbox" v-model="includeMedia"> Media</label>
      <label><input type="checkbox" v-model="includeRoles"> Roles</label>
      <label><input type="checkbox" v-model="includeEmojis"> Emojis</label>
      <label><input type="checkbox" v-model="includeSettings"> Server Settings</label>
    </div>

    <!-- Channel List -->
    <ChannelTree :channels="channels" @backup-channel="backupChannel" />

    <!-- Recent Backups -->
    <BackupList :backups="serverBackups" />
  </div>
</template>
```

### 6.3 Add Bot Modal

```vue
<template>
  <Modal title="Discord Bot hinzufügen">
    <div class="steps">
      <!-- Step 1: Create Bot -->
      <div class="step">
        <h3>1. Bot erstellen</h3>
        <p>Erstelle einen Bot im Discord Developer Portal:</p>
        <a href="https://discord.com/developers/applications" target="_blank">
          Discord Developer Portal öffnen
        </a>
        <ol>
          <li>Klicke "New Application"</li>
          <li>Gib einen Namen ein</li>
          <li>Gehe zu "Bot" → "Add Bot"</li>
          <li>Aktiviere "Message Content Intent"</li>
        </ol>
      </div>

      <!-- Step 2: Enter Credentials -->
      <div class="step">
        <h3>2. Credentials eingeben</h3>
        <input v-model="clientId" placeholder="Client ID (Application ID)" />
        <input v-model="clientSecret" type="password" placeholder="Client Secret" />
        <input v-model="botToken" type="password" placeholder="Bot Token" />
      </div>

      <!-- Step 3: Validate -->
      <div class="step">
        <h3>3. Validieren</h3>
        <button @click="validateBot">Bot validieren</button>
        <div v-if="botInfo" class="bot-preview">
          <img :src="botInfo.avatar_url" />
          <span>{{ botInfo.username }}#{{ botInfo.discriminator }}</span>
        </div>
      </div>
    </div>

    <template #footer>
      <button @click="saveBot" :disabled="!isValid">Bot speichern</button>
    </template>
  </Modal>
</template>
```

---

## 7. OAuth2 Bot Invite Flow

### 7.1 Invite URL generieren

```php
public function generateInviteUrl(string $clientId, array $permissions = []): string
{
    $defaultPermissions = [
        'VIEW_CHANNEL',           // Channels sehen
        'READ_MESSAGE_HISTORY',   // Nachrichten lesen
        'MANAGE_WEBHOOKS',        // Für Restore
        'MANAGE_ROLES',           // Für Restore (optional)
        'MANAGE_CHANNELS',        // Für Restore (optional)
        'MANAGE_EMOJIS',          // Für Emoji Backup (optional)
    ];

    $permissionBits = $this->calculatePermissions($permissions ?: $defaultPermissions);

    return sprintf(
        'https://discord.com/api/oauth2/authorize?client_id=%s&permissions=%d&scope=bot',
        $clientId,
        $permissionBits
    );
}
```

### 7.2 Permission Calculator

```php
private const PERMISSIONS = [
    'VIEW_CHANNEL' => 0x0000000000000400,
    'READ_MESSAGE_HISTORY' => 0x0000000000010000,
    'MANAGE_WEBHOOKS' => 0x0000000020000000,
    'MANAGE_ROLES' => 0x0000000010000000,
    'MANAGE_CHANNELS' => 0x0000000000000010,
    'MANAGE_EMOJIS' => 0x0000000040000000,
    'ADMINISTRATOR' => 0x0000000000000008,
];

public function calculatePermissions(array $permissionNames): int
{
    $bits = 0;
    foreach ($permissionNames as $name) {
        $bits |= self::PERMISSIONS[$name] ?? 0;
    }
    return $bits;
}
```

---

## 8. Implementierungs-Phasen

### Phase 1: Basis (1-2 Wochen Arbeit)
- [ ] Datenbank-Migration für Bot-Tabellen
- [ ] DiscordBotApiService mit Basis-Methoden
- [ ] Bot CRUD API Endpoints
- [ ] Bot hinzufügen/validieren im Frontend
- [ ] Server-Liste für Bot anzeigen
- [ ] Invite URL Generator

### Phase 2: Backups (1-2 Wochen Arbeit)
- [ ] Full Server Backup (Channels, Rollen, Emojis, Messages)
- [ ] Server Settings Backup
- [ ] Backup-Ansicht im Frontend
- [ ] Media Gallery Integration

### Phase 3: Scheduling (1 Woche Arbeit)
- [ ] Backup Schedules Tabelle & API
- [ ] Cron Job für scheduled Backups
- [ ] Schedule UI im Frontend
- [ ] Retention Policy (alte Backups löschen)

### Phase 4: Restore (2 Wochen Arbeit)
- [ ] Server Clone Funktion
- [ ] Restore UI mit Optionen
- [ ] Restore Progress Tracking
- [ ] Webhook-basierte Message Replikation (optional)

### Phase 5: Advanced (Optional)
- [ ] Live Gateway Connection (Discord Gateway API)
- [ ] Real-time Message Logging
- [ ] Deleted Message Recovery
- [ ] Moderation Commands

---

## 9. Sicherheitsüberlegungen

### 9.1 Token Storage
- Bot Tokens werden wie User Tokens verschlüsselt gespeichert
- Client Secret ebenfalls verschlüsseln
- Niemals Tokens im Frontend/Logs exposen

### 9.2 Permission Scoping
- Bot nur minimale Permissions anfordern
- Berechtigungen pro Server tracken
- Warnung wenn Permissions fehlen

### 9.3 Rate Limiting
- Discord Bot Rate Limits beachten (50 req/s global)
- Per-Route Limits (z.B. Message History: 5 req/5s)
- Exponential Backoff bei 429 Responses

### 9.4 Privileged Intents
- MESSAGE_CONTENT Intent nur anfordern wenn nötig
- Hinweis an User dass Intent im Portal aktiviert werden muss
- Fallback wenn Intent nicht verfügbar

---

## 10. Vorteile des Dual-System Ansatzes

| Aspekt | Nur User Token | User Token + Bot |
|--------|---------------|------------------|
| ToS Konformität | ⚠️ Grauzone | ✅ Bot ist offiziell |
| DM Backup | ✅ Ja | ⚠️ Nur mit User Token |
| Server Backup | ✅ Ja | ✅ Besser mit Bot |
| Server Restore | ❌ Nein | ✅ Ja |
| Scheduled Backups | ⚠️ Token kann invalide werden | ✅ Stabil |
| Audit Logs | ❌ Nein | ✅ Ja |
| Webhook Backup | ❌ Nein | ✅ Ja |
| Emojis mit URL | ⚠️ Limitiert | ✅ Vollständig |

---

## 11. User Flow Beispiel

```
1. User öffnet Discord Manager
2. Klickt auf "Bot hinzufügen"
3. Erstellt Bot im Discord Developer Portal (Anleitung im Modal)
4. Trägt Client ID, Secret und Token ein
5. Klickt "Validieren" → Bot-Info wird angezeigt
6. Klickt "Speichern"
7. Generiert Invite-Link mit gewünschten Permissions
8. Lädt Bot auf Server ein
9. Server erscheint in der Bot-Server-Liste
10. Startet Full Server Backup oder konfiguriert Schedule
```

---

## 12. Technische Notizen

### Discord API Unterschiede Bot vs User

```php
// User Token Request
$headers = [
    'Authorization: ' . $userToken,
    'X-Super-Properties: ' . base64_encode(json_encode($clientProps)),
];

// Bot Token Request
$headers = [
    'Authorization: Bot ' . $botToken,
    // Kein X-Super-Properties nötig
];
```

### Gateway Intents Berechnung

```php
$intents = 0;
$intents |= (1 << 0);  // GUILDS
$intents |= (1 << 9);  // GUILD_MESSAGES
$intents |= (1 << 15); // MESSAGE_CONTENT (privileged)
// = 33281
```

### Empfohlene Bot Permissions (Bitfield)

```
Basis Backup:     379968 (View Channels, Read History)
Mit Restore:      268954688 (+ Manage Channels, Roles, Webhooks, Emojis)
Administrator:    8 (Alle Rechte - nicht empfohlen)
```
