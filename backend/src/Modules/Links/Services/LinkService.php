<?php

declare(strict_types=1);

namespace App\Modules\Links\Services;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

class LinkService
{
    private const CODE_LENGTH = 6;
    private const CODE_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * Get all links for a user
     */
    public function getLinks(string $userId, array $params = []): array
    {
        $sql = 'SELECT * FROM short_links WHERE user_id = ?';
        $queryParams = [$userId];

        if (!empty($params['search'])) {
            $sql .= ' AND (title LIKE ? OR original_url LIKE ? OR short_code LIKE ?)';
            $search = '%' . $params['search'] . '%';
            $queryParams[] = $search;
            $queryParams[] = $search;
            $queryParams[] = $search;
        }

        if (isset($params['is_active'])) {
            $sql .= ' AND is_active = ?';
            $queryParams[] = $params['is_active'] ? 1 : 0;
        }

        $sql .= ' ORDER BY created_at DESC';

        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $countSql = 'SELECT COUNT(*) FROM short_links WHERE user_id = ?';
        $total = (int) $this->db->fetchOne($countSql, [$userId]);

        $sql .= ' LIMIT ? OFFSET ?';
        $queryParams[] = $perPage;
        $queryParams[] = $offset;

        $links = $this->db->fetchAllAssociative($sql, $queryParams);

        return [
            'items' => $links,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Get a single link
     */
    public function getLink(string $id, string $userId): ?array
    {
        return $this->db->fetchAssociative(
            'SELECT * FROM short_links WHERE id = ? AND user_id = ?',
            [$id, $userId]
        ) ?: null;
    }

    /**
     * Get link by short code (public)
     */
    public function getLinkByCode(string $code): ?array
    {
        return $this->db->fetchAssociative(
            'SELECT * FROM short_links WHERE short_code = ?',
            [$code]
        ) ?: null;
    }

    /**
     * Create a new short link
     */
    public function createLink(string $userId, array $data): array
    {
        $id = Uuid::uuid4()->toString();
        $shortCode = $data['custom_code'] ?? $this->generateUniqueCode();

        // Validate custom code
        if (!empty($data['custom_code'])) {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $data['custom_code'])) {
                throw new \InvalidArgumentException('Short code can only contain letters, numbers, hyphens and underscores');
            }
            if ($this->codeExists($data['custom_code'])) {
                throw new \InvalidArgumentException('This short code is already taken');
            }
        }

        // Hash password if provided
        $passwordHash = null;
        if (!empty($data['password'])) {
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $this->db->insert('short_links', [
            'id' => $id,
            'user_id' => $userId,
            'short_code' => $shortCode,
            'original_url' => $data['url'],
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'password_hash' => $passwordHash,
            'expires_at' => $data['expires_at'] ?? null,
            'max_clicks' => $data['max_clicks'] ?? null,
            'is_active' => 1,
        ]);

        return $this->getLink($id, $userId);
    }

    /**
     * Update a link
     */
    public function updateLink(string $id, string $userId, array $data): bool
    {
        $updateData = [];

        $fields = ['title', 'description', 'expires_at', 'max_clicks'];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        if (isset($data['is_active'])) {
            $updateData['is_active'] = $data['is_active'] ? 1 : 0;
        }

        if (!empty($data['password'])) {
            $updateData['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } elseif (array_key_exists('password', $data) && $data['password'] === null) {
            $updateData['password_hash'] = null;
        }

        if (empty($updateData)) {
            return false;
        }

        return $this->db->update('short_links', $updateData, [
            'id' => $id,
            'user_id' => $userId,
        ]) > 0;
    }

    /**
     * Delete a link
     */
    public function deleteLink(string $id, string $userId): bool
    {
        return $this->db->delete('short_links', [
            'id' => $id,
            'user_id' => $userId,
        ]) > 0;
    }

    /**
     * Record a click and return the original URL
     */
    public function recordClick(string $code, array $requestInfo): ?string
    {
        $link = $this->getLinkByCode($code);

        if (!$link) {
            return null;
        }

        // Check if link is active
        if (!$link['is_active']) {
            return null;
        }

        // Check expiration
        if ($link['expires_at'] && strtotime($link['expires_at']) < time()) {
            return null;
        }

        // Check max clicks
        if ($link['max_clicks'] && $link['click_count'] >= $link['max_clicks']) {
            return null;
        }

        // Record the click
        $clickId = Uuid::uuid4()->toString();
        $userAgent = $requestInfo['user_agent'] ?? '';
        $deviceInfo = $this->parseUserAgent($userAgent);

        $this->db->insert('short_link_clicks', [
            'id' => $clickId,
            'link_id' => $link['id'],
            'ip_address' => $requestInfo['ip'] ?? null,
            'user_agent' => $userAgent,
            'referrer' => $requestInfo['referrer'] ?? null,
            'country_code' => $this->getCountryFromIp($requestInfo['ip'] ?? ''),
            'browser' => $deviceInfo['browser'],
            'os' => $deviceInfo['os'],
            'device_type' => $deviceInfo['device'],
        ]);

        // Increment click count
        $this->db->executeStatement(
            'UPDATE short_links SET click_count = click_count + 1 WHERE id = ?',
            [$link['id']]
        );

        return $link['original_url'];
    }

    /**
     * Verify link password
     */
    public function verifyPassword(string $code, string $password): bool
    {
        $link = $this->getLinkByCode($code);
        if (!$link || !$link['password_hash']) {
            return true;
        }
        return password_verify($password, $link['password_hash']);
    }

    /**
     * Check if link requires password
     */
    public function requiresPassword(string $code): bool
    {
        $link = $this->getLinkByCode($code);
        return $link && !empty($link['password_hash']);
    }

    /**
     * Get click statistics for a link
     */
    public function getStats(string $id, string $userId, int $days = 30): array
    {
        $link = $this->getLink($id, $userId);
        if (!$link) {
            return [];
        }

        // Daily clicks
        $dailyClicks = $this->db->fetchAllAssociative(
            'SELECT DATE(clicked_at) as date, COUNT(*) as clicks
             FROM short_link_clicks
             WHERE link_id = ? AND clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(clicked_at)
             ORDER BY date',
            [$id, $days]
        );

        // Browser stats
        $browsers = $this->db->fetchAllAssociative(
            'SELECT browser, COUNT(*) as count
             FROM short_link_clicks
             WHERE link_id = ?
             GROUP BY browser
             ORDER BY count DESC
             LIMIT 10',
            [$id]
        );

        // OS stats
        $operatingSystems = $this->db->fetchAllAssociative(
            'SELECT os, COUNT(*) as count
             FROM short_link_clicks
             WHERE link_id = ?
             GROUP BY os
             ORDER BY count DESC
             LIMIT 10',
            [$id]
        );

        // Device stats
        $devices = $this->db->fetchAllAssociative(
            'SELECT device_type, COUNT(*) as count
             FROM short_link_clicks
             WHERE link_id = ?
             GROUP BY device_type
             ORDER BY count DESC',
            [$id]
        );

        // Country stats
        $countries = $this->db->fetchAllAssociative(
            'SELECT country_code, COUNT(*) as count
             FROM short_link_clicks
             WHERE link_id = ? AND country_code IS NOT NULL
             GROUP BY country_code
             ORDER BY count DESC
             LIMIT 10',
            [$id]
        );

        // Top referrers
        $referrers = $this->db->fetchAllAssociative(
            'SELECT referrer, COUNT(*) as count
             FROM short_link_clicks
             WHERE link_id = ? AND referrer IS NOT NULL AND referrer != \'\'
             GROUP BY referrer
             ORDER BY count DESC
             LIMIT 10',
            [$id]
        );

        // Recent clicks
        $recentClicks = $this->db->fetchAllAssociative(
            'SELECT * FROM short_link_clicks
             WHERE link_id = ?
             ORDER BY clicked_at DESC
             LIMIT 50',
            [$id]
        );

        return [
            'total_clicks' => $link['click_count'],
            'daily_clicks' => $dailyClicks,
            'browsers' => $browsers,
            'operating_systems' => $operatingSystems,
            'devices' => $devices,
            'countries' => $countries,
            'referrers' => $referrers,
            'recent_clicks' => $recentClicks,
        ];
    }

    /**
     * Get user statistics
     */
    public function getUserStats(string $userId): array
    {
        $totalLinks = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM short_links WHERE user_id = ?',
            [$userId]
        );

        $activeLinks = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM short_links WHERE user_id = ? AND is_active = 1',
            [$userId]
        );

        $totalClicks = (int) $this->db->fetchOne(
            'SELECT COALESCE(SUM(click_count), 0) FROM short_links WHERE user_id = ?',
            [$userId]
        );

        $clicksToday = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM short_link_clicks c
             JOIN short_links l ON c.link_id = l.id
             WHERE l.user_id = ? AND DATE(c.clicked_at) = CURDATE()',
            [$userId]
        );

        return [
            'total_links' => $totalLinks,
            'active_links' => $activeLinks,
            'total_clicks' => $totalClicks,
            'clicks_today' => $clicksToday,
        ];
    }

    // ==================== Helper Methods ====================

    private function generateUniqueCode(): string
    {
        do {
            $code = $this->generateCode();
        } while ($this->codeExists($code));

        return $code;
    }

    private function generateCode(): string
    {
        $code = '';
        $chars = self::CODE_CHARS;
        $length = strlen($chars);

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= $chars[random_int(0, $length - 1)];
        }

        return $code;
    }

    private function codeExists(string $code): bool
    {
        return (bool) $this->db->fetchOne(
            'SELECT 1 FROM short_links WHERE short_code = ?',
            [$code]
        );
    }

    private function parseUserAgent(string $userAgent): array
    {
        $browser = 'Unknown';
        $os = 'Unknown';
        $device = 'unknown';

        // Detect browser
        if (preg_match('/Firefox/i', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Chrome/i', $userAgent) && !preg_match('/Edge/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Safari/i', $userAgent) && !preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edge/i', $userAgent)) {
            $browser = 'Edge';
        } elseif (preg_match('/Opera|OPR/i', $userAgent)) {
            $browser = 'Opera';
        } elseif (preg_match('/MSIE|Trident/i', $userAgent)) {
            $browser = 'Internet Explorer';
        }

        // Detect OS
        if (preg_match('/Windows/i', $userAgent)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac/i', $userAgent)) {
            $os = 'macOS';
        } elseif (preg_match('/Linux/i', $userAgent) && !preg_match('/Android/i', $userAgent)) {
            $os = 'Linux';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $os = 'Android';
        } elseif (preg_match('/iOS|iPhone|iPad/i', $userAgent)) {
            $os = 'iOS';
        }

        // Detect device type
        if (preg_match('/bot|crawler|spider|slurp|googlebot/i', $userAgent)) {
            $device = 'bot';
        } elseif (preg_match('/Mobile|Android|iPhone/i', $userAgent) && !preg_match('/iPad|Tablet/i', $userAgent)) {
            $device = 'mobile';
        } elseif (preg_match('/iPad|Tablet/i', $userAgent)) {
            $device = 'tablet';
        } else {
            $device = 'desktop';
        }

        return [
            'browser' => $browser,
            'os' => $os,
            'device' => $device,
        ];
    }

    private function getCountryFromIp(string $ip): ?string
    {
        // Simple implementation - in production use a GeoIP service
        if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1') {
            return null;
        }

        // Could integrate with MaxMind GeoIP or similar service
        return null;
    }
}
