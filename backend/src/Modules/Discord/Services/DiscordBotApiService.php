<?php

declare(strict_types=1);

namespace App\Modules\Discord\Services;

/**
 * Discord API Service for Bot tokens
 * Uses "Bot {token}" authorization format instead of user token
 */
class DiscordBotApiService
{
    private const BASE_URL = 'https://discord.com/api/v10';
    private const CDN_URL = 'https://cdn.discordapp.com';

    // Rate limit: Bots have 50 requests per second global limit
    private const REQUEST_DELAY_MS = 50;
    private const MAX_RETRIES = 3;

    // Permission bits
    public const PERMISSIONS = [
        'CREATE_INSTANT_INVITE' => 0x0000000000000001,
        'KICK_MEMBERS' => 0x0000000000000002,
        'BAN_MEMBERS' => 0x0000000000000004,
        'ADMINISTRATOR' => 0x0000000000000008,
        'MANAGE_CHANNELS' => 0x0000000000000010,
        'MANAGE_GUILD' => 0x0000000000000020,
        'ADD_REACTIONS' => 0x0000000000000040,
        'VIEW_AUDIT_LOG' => 0x0000000000000080,
        'VIEW_CHANNEL' => 0x0000000000000400,
        'SEND_MESSAGES' => 0x0000000000000800,
        'MANAGE_MESSAGES' => 0x0000000000002000,
        'EMBED_LINKS' => 0x0000000000004000,
        'ATTACH_FILES' => 0x0000000000008000,
        'READ_MESSAGE_HISTORY' => 0x0000000000010000,
        'MANAGE_ROLES' => 0x0000000010000000,
        'MANAGE_WEBHOOKS' => 0x0000000020000000,
        'MANAGE_EMOJIS' => 0x0000000040000000,
    ];

    private ?int $lastRequestTime = null;

    /**
     * Validate a bot token and get bot user info
     */
    public function validateToken(string $token): ?array
    {
        try {
            return $this->getCurrentBot($token);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get current bot user info
     */
    public function getCurrentBot(string $token): array
    {
        return $this->request('GET', '/users/@me', $token);
    }

    /**
     * Get bot application info
     */
    public function getApplication(string $token): array
    {
        return $this->request('GET', '/oauth2/applications/@me', $token);
    }

    /**
     * Get gateway bot info (includes shard count and rate limits)
     */
    public function getGatewayBot(string $token): array
    {
        return $this->request('GET', '/gateway/bot', $token);
    }

    /**
     * Get all guilds the bot is in
     */
    public function getGuilds(string $token): array
    {
        return $this->request('GET', '/users/@me/guilds', $token);
    }

    /**
     * Get guild details
     */
    public function getGuild(string $token, string $guildId, bool $withCounts = true): array
    {
        $query = $withCounts ? '?with_counts=true' : '';
        return $this->request('GET', "/guilds/{$guildId}{$query}", $token);
    }

    /**
     * Get guild channels
     */
    public function getGuildChannels(string $token, string $guildId): array
    {
        return $this->request('GET', "/guilds/{$guildId}/channels", $token);
    }

    /**
     * Get guild roles
     */
    public function getGuildRoles(string $token, string $guildId): array
    {
        return $this->request('GET', "/guilds/{$guildId}/roles", $token);
    }

    /**
     * Get guild emojis
     */
    public function getGuildEmojis(string $token, string $guildId): array
    {
        return $this->request('GET', "/guilds/{$guildId}/emojis", $token);
    }

    /**
     * Get guild members (requires GUILD_MEMBERS intent)
     */
    public function getGuildMembers(string $token, string $guildId, int $limit = 1000, ?string $after = null): array
    {
        $query = ['limit' => min(1000, $limit)];
        if ($after) {
            $query['after'] = $after;
        }
        $queryString = http_build_query($query);
        return $this->request('GET', "/guilds/{$guildId}/members?{$queryString}", $token);
    }

    /**
     * Get guild audit log
     */
    public function getGuildAuditLog(string $token, string $guildId, array $options = []): array
    {
        $query = [];
        if (!empty($options['user_id'])) {
            $query['user_id'] = $options['user_id'];
        }
        if (!empty($options['action_type'])) {
            $query['action_type'] = $options['action_type'];
        }
        if (!empty($options['before'])) {
            $query['before'] = $options['before'];
        }
        $query['limit'] = min(100, $options['limit'] ?? 50);

        $queryString = !empty($query) ? '?' . http_build_query($query) : '';
        return $this->request('GET', "/guilds/{$guildId}/audit-logs{$queryString}", $token);
    }

    /**
     * Get channel info
     */
    public function getChannel(string $token, string $channelId): array
    {
        return $this->request('GET', "/channels/{$channelId}", $token);
    }

    /**
     * Get channel webhooks
     */
    public function getChannelWebhooks(string $token, string $channelId): array
    {
        return $this->request('GET', "/channels/{$channelId}/webhooks", $token);
    }

    /**
     * Get guild webhooks
     */
    public function getGuildWebhooks(string $token, string $guildId): array
    {
        return $this->request('GET', "/guilds/{$guildId}/webhooks", $token);
    }

    /**
     * Get messages from a channel
     */
    public function getChannelMessages(string $token, string $channelId, array $options = []): array
    {
        $query = [];

        if (!empty($options['before'])) {
            $query['before'] = $options['before'];
        }
        if (!empty($options['after'])) {
            $query['after'] = $options['after'];
        }
        if (!empty($options['around'])) {
            $query['around'] = $options['around'];
        }

        $query['limit'] = min(100, (int) ($options['limit'] ?? 100));

        $queryString = !empty($query) ? '?' . http_build_query($query) : '';

        return $this->request('GET', "/channels/{$channelId}/messages{$queryString}", $token);
    }

    /**
     * Get all messages from a channel (handles pagination)
     */
    public function getAllChannelMessages(string $token, string $channelId, ?string $beforeId = null): \Generator
    {
        $lastMessageId = $beforeId;

        while (true) {
            $options = ['limit' => 100];

            if ($lastMessageId) {
                $options['before'] = $lastMessageId;
            }

            $messages = $this->getChannelMessages($token, $channelId, $options);

            if (empty($messages)) {
                break;
            }

            foreach ($messages as $message) {
                yield $message;
                $lastMessageId = $message['id'];
            }

            if (count($messages) < 100) {
                break;
            }
        }
    }

    /**
     * Get pinned messages
     */
    public function getPinnedMessages(string $token, string $channelId): array
    {
        return $this->request('GET', "/channels/{$channelId}/pins", $token);
    }

    /**
     * Get thread members
     */
    public function getThreadMembers(string $token, string $threadId): array
    {
        return $this->request('GET', "/channels/{$threadId}/thread-members", $token);
    }

    /**
     * Get active threads in a guild
     */
    public function getActiveThreads(string $token, string $guildId): array
    {
        return $this->request('GET', "/guilds/{$guildId}/threads/active", $token);
    }

    /**
     * Get archived threads in a channel
     */
    public function getArchivedThreads(string $token, string $channelId, bool $private = false): array
    {
        $type = $private ? 'private' : 'public';
        return $this->request('GET', "/channels/{$channelId}/threads/archived/{$type}", $token);
    }

    // ========== Create Methods (for restore) ==========

    /**
     * Create a new guild (bot must be in less than 10 guilds)
     */
    public function createGuild(string $token, array $data): array
    {
        return $this->request('POST', '/guilds', $token, $data);
    }

    /**
     * Create a channel
     */
    public function createChannel(string $token, string $guildId, array $data): array
    {
        return $this->request('POST', "/guilds/{$guildId}/channels", $token, $data);
    }

    /**
     * Create a role
     */
    public function createRole(string $token, string $guildId, array $data): array
    {
        return $this->request('POST', "/guilds/{$guildId}/roles", $token, $data);
    }

    /**
     * Create an emoji
     */
    public function createEmoji(string $token, string $guildId, array $data): array
    {
        return $this->request('POST', "/guilds/{$guildId}/emojis", $token, $data);
    }

    /**
     * Create a webhook
     */
    public function createWebhook(string $token, string $channelId, array $data): array
    {
        return $this->request('POST', "/channels/{$channelId}/webhooks", $token, $data);
    }

    // ========== Utility Methods ==========

    /**
     * Generate OAuth2 bot invite URL
     */
    public function generateInviteUrl(string $clientId, array $permissions = [], ?string $guildId = null): string
    {
        $defaultPermissions = [
            'VIEW_CHANNEL',
            'READ_MESSAGE_HISTORY',
        ];

        $permissionBits = $this->calculatePermissions($permissions ?: $defaultPermissions);

        $params = [
            'client_id' => $clientId,
            'permissions' => $permissionBits,
            'scope' => 'bot',
        ];

        if ($guildId) {
            $params['guild_id'] = $guildId;
            $params['disable_guild_select'] = 'true';
        }

        return 'https://discord.com/api/oauth2/authorize?' . http_build_query($params);
    }

    /**
     * Calculate permission bits from permission names
     */
    public function calculatePermissions(array $permissionNames): int
    {
        $bits = 0;
        foreach ($permissionNames as $name) {
            $bits |= self::PERMISSIONS[$name] ?? 0;
        }
        return $bits;
    }

    /**
     * Parse permission bits to permission names
     */
    public function parsePermissions(int $bits): array
    {
        $permissions = [];
        foreach (self::PERMISSIONS as $name => $bit) {
            if (($bits & $bit) === $bit) {
                $permissions[] = $name;
            }
        }
        return $permissions;
    }

    /**
     * Check if bot has specific permission
     */
    public function hasPermission(int $botPermissions, string $permission): bool
    {
        $bit = self::PERMISSIONS[$permission] ?? 0;
        return ($botPermissions & $bit) === $bit;
    }

    /**
     * Build avatar URL
     */
    public function getAvatarUrl(?string $userId, ?string $avatarHash, int $size = 128): ?string
    {
        if (!$userId || !$avatarHash) {
            return null;
        }

        $extension = str_starts_with($avatarHash, 'a_') ? 'gif' : 'png';
        return self::CDN_URL . "/avatars/{$userId}/{$avatarHash}.{$extension}?size={$size}";
    }

    /**
     * Build guild icon URL
     */
    public function getGuildIconUrl(?string $guildId, ?string $iconHash, int $size = 128): ?string
    {
        if (!$guildId || !$iconHash) {
            return null;
        }

        $extension = str_starts_with($iconHash, 'a_') ? 'gif' : 'png';
        return self::CDN_URL . "/icons/{$guildId}/{$iconHash}.{$extension}?size={$size}";
    }

    /**
     * Build emoji URL
     */
    public function getEmojiUrl(string $emojiId, bool $animated = false): string
    {
        $extension = $animated ? 'gif' : 'png';
        return self::CDN_URL . "/emojis/{$emojiId}.{$extension}";
    }

    /**
     * Download a file from Discord CDN
     */
    public function downloadFile(string $url, string $localPath): bool
    {
        $dir = dirname($localPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ch = curl_init($url);
        $fp = fopen($localPath, 'wb');

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_USERAGENT => 'DiscordBot (KyuubiSoft, 1.0)',
        ]);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        fclose($fp);

        if (!$success || $httpCode !== 200) {
            @unlink($localPath);
            return false;
        }

        return true;
    }

    /**
     * Convert Discord snowflake to timestamp
     */
    public function snowflakeToTimestamp(string $snowflake): int
    {
        $discordEpoch = 1420070400000;
        $timestamp = ((int) $snowflake >> 22) + $discordEpoch;
        return (int) ($timestamp / 1000);
    }

    // ========== Private Methods ==========

    /**
     * Make an API request to Discord
     */
    private function request(string $method, string $endpoint, string $token, ?array $body = null, int $retryCount = 0): array
    {
        $this->rateLimit();

        $url = self::BASE_URL . $endpoint;

        $ch = curl_init();

        // Bot token format
        $headers = [
            'Authorization: Bot ' . $token,
            'Content-Type: application/json',
            'User-Agent: DiscordBot (KyuubiSoft, 1.0)',
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        $this->lastRequestTime = (int) (microtime(true) * 1000);

        if ($error) {
            throw new \RuntimeException("Discord API request failed: {$error}");
        }

        $data = json_decode($response, true) ?? [];

        // Handle rate limiting
        if ($httpCode === 429) {
            $retryAfter = ($data['retry_after'] ?? 1) * 1000;

            if ($retryCount < self::MAX_RETRIES) {
                usleep((int) ($retryAfter * 1000));
                return $this->request($method, $endpoint, $token, $body, $retryCount + 1);
            }

            throw new \RuntimeException('Discord API rate limit exceeded');
        }

        // Handle errors
        if ($httpCode >= 400) {
            $message = $data['message'] ?? 'Unknown error';
            $code = $data['code'] ?? 0;
            throw new \RuntimeException("Discord API error ({$code}): {$message}", $httpCode);
        }

        return $data;
    }

    /**
     * Apply rate limiting between requests
     */
    private function rateLimit(): void
    {
        if ($this->lastRequestTime !== null) {
            $elapsed = (int) (microtime(true) * 1000) - $this->lastRequestTime;
            $remaining = self::REQUEST_DELAY_MS - $elapsed;

            if ($remaining > 0) {
                usleep($remaining * 1000);
            }
        }
    }
}
