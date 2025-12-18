<?php

declare(strict_types=1);

namespace App\Modules\Discord\Services;

use App\Core\Exceptions\ValidationException;

class DiscordApiService
{
    private const BASE_URL = 'https://discord.com/api/v10';
    private const CDN_URL = 'https://cdn.discordapp.com';

    // Rate limit: ~50 requests per second, but we'll be conservative
    private const REQUEST_DELAY_MS = 100;
    private const RETRY_AFTER_DEFAULT = 1000;
    private const MAX_RETRIES = 3;

    private ?int $lastRequestTime = null;

    /**
     * Build X-Super-Properties header for user token authentication
     * This header identifies the client to Discord
     */
    private function getSuperProperties(): string
    {
        $properties = [
            'os' => 'Windows',
            'browser' => 'Chrome',
            'device' => '',
            'system_locale' => 'de-DE',
            'browser_user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'browser_version' => '120.0.0.0',
            'os_version' => '10',
            'referrer' => '',
            'referring_domain' => '',
            'referrer_current' => '',
            'referring_domain_current' => '',
            'release_channel' => 'stable',
            'client_build_number' => 254573,
            'client_event_source' => null,
        ];

        return base64_encode(json_encode($properties));
    }

    /**
     * Validate a user token and get user info
     */
    public function validateToken(string $token): ?array
    {
        try {
            return $this->getCurrentUser($token);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get current user info
     */
    public function getCurrentUser(string $token): array
    {
        return $this->request('GET', '/users/@me', $token);
    }

    /**
     * Get all guilds (servers) the user is in
     */
    public function getGuilds(string $token): array
    {
        return $this->request('GET', '/users/@me/guilds', $token);
    }

    /**
     * Get guild details
     */
    public function getGuild(string $token, string $guildId): array
    {
        return $this->request('GET', "/guilds/{$guildId}", $token);
    }

    /**
     * Get guild channels
     */
    public function getGuildChannels(string $token, string $guildId): array
    {
        return $this->request('GET', "/guilds/{$guildId}/channels", $token);
    }

    /**
     * Get DM channels
     */
    public function getDMChannels(string $token): array
    {
        return $this->request('GET', '/users/@me/channels', $token);
    }

    /**
     * Get channel info
     */
    public function getChannel(string $token, string $channelId): array
    {
        return $this->request('GET', "/channels/{$channelId}", $token);
    }

    /**
     * Get messages from a channel
     *
     * @param array $options [before, after, around, limit (max 100)]
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
     * Returns a generator to handle large channels efficiently
     */
    public function getAllChannelMessages(string $token, string $channelId, ?string $beforeId = null, ?string $afterId = null): \Generator
    {
        $lastMessageId = $beforeId;

        while (true) {
            $options = ['limit' => 100];

            if ($lastMessageId) {
                $options['before'] = $lastMessageId;
            } elseif ($afterId) {
                $options['after'] = $afterId;
            }

            $messages = $this->getChannelMessages($token, $channelId, $options);

            if (empty($messages)) {
                break;
            }

            foreach ($messages as $message) {
                yield $message;
                $lastMessageId = $message['id'];
            }

            // If we got fewer than 100 messages, we've reached the end
            if (count($messages) < 100) {
                break;
            }
        }
    }

    /**
     * Search messages in a guild (user account only feature)
     */
    public function searchGuildMessages(string $token, string $guildId, array $filters = []): array
    {
        $query = [];

        if (!empty($filters['content'])) {
            $query['content'] = $filters['content'];
        }
        if (!empty($filters['author_id'])) {
            $query['author_id'] = $filters['author_id'];
        }
        if (!empty($filters['channel_id'])) {
            $query['channel_id'] = $filters['channel_id'];
        }
        if (!empty($filters['has'])) {
            $query['has'] = $filters['has']; // link, embed, file, video, image, sound
        }
        if (!empty($filters['min_id'])) {
            $query['min_id'] = $filters['min_id'];
        }
        if (!empty($filters['max_id'])) {
            $query['max_id'] = $filters['max_id'];
        }

        $query['include_nsfw'] = 'true';

        $queryString = http_build_query($query);

        return $this->request('GET', "/guilds/{$guildId}/messages/search?{$queryString}", $token);
    }

    /**
     * Search messages in a DM channel
     */
    public function searchChannelMessages(string $token, string $channelId, array $filters = []): array
    {
        $query = [];

        if (!empty($filters['content'])) {
            $query['content'] = $filters['content'];
        }
        if (!empty($filters['author_id'])) {
            $query['author_id'] = $filters['author_id'];
        }
        if (!empty($filters['has'])) {
            $query['has'] = $filters['has'];
        }
        if (!empty($filters['min_id'])) {
            $query['min_id'] = $filters['min_id'];
        }
        if (!empty($filters['max_id'])) {
            $query['max_id'] = $filters['max_id'];
        }

        $queryString = http_build_query($query);

        return $this->request('GET', "/channels/{$channelId}/messages/search?{$queryString}", $token);
    }

    /**
     * Delete a message (can only delete own messages)
     */
    public function deleteMessage(string $token, string $channelId, string $messageId): bool
    {
        try {
            $this->request('DELETE', "/channels/{$channelId}/messages/{$messageId}", $token);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get a specific message
     */
    public function getMessage(string $token, string $channelId, string $messageId): array
    {
        return $this->request('GET', "/channels/{$channelId}/messages/{$messageId}", $token);
    }

    /**
     * Download an attachment/media file
     */
    public function downloadAttachment(string $url, string $localPath): bool
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
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
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
     * Convert Discord snowflake to timestamp
     */
    public function snowflakeToTimestamp(string $snowflake): int
    {
        // Discord epoch: 2015-01-01T00:00:00.000Z
        $discordEpoch = 1420070400000;
        $timestamp = ((int) $snowflake >> 22) + $discordEpoch;
        return (int) ($timestamp / 1000);
    }

    /**
     * Convert timestamp to Discord snowflake
     */
    public function timestampToSnowflake(int $timestamp): string
    {
        $discordEpoch = 1420070400000;
        $snowflake = ($timestamp * 1000 - $discordEpoch) << 22;
        return (string) $snowflake;
    }

    /**
     * Make an API request to Discord
     */
    private function request(string $method, string $endpoint, string $token, ?array $body = null, int $retryCount = 0): array
    {
        // Rate limiting
        $this->rateLimit();

        $url = self::BASE_URL . $endpoint;

        $ch = curl_init();

        $headers = [
            'Authorization: ' . $token,
            'Content-Type: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'X-Super-Properties: ' . $this->getSuperProperties(),
            'X-Discord-Locale: de',
            'X-Discord-Timezone: Europe/Berlin',
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
