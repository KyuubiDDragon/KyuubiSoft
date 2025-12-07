<?php

declare(strict_types=1);

namespace App\Modules\News\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class NewsController
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Get all available feeds with user subscription status
     */
    public function getFeeds(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();
        $category = $queryParams['category'] ?? null;

        $sql = "SELECT f.*,
                       CASE WHEN ufs.user_id IS NOT NULL THEN 1 ELSE 0 END as is_subscribed
                FROM news_feeds f
                LEFT JOIN user_feed_subscriptions ufs ON f.id = ufs.feed_id AND ufs.user_id = ?
                WHERE f.is_active = 1";
        $params = [$userId];

        if ($category) {
            $sql .= " AND f.category = ?";
            $params[] = $category;
        }

        $sql .= " ORDER BY f.category, f.name";

        $feeds = $this->db->fetchAllAssociative($sql, $params);

        // Group by category
        $grouped = [];
        foreach ($feeds as $feed) {
            $cat = $feed['category'];
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $feed;
        }

        return JsonResponse::success([
            'feeds' => $feeds,
            'grouped' => $grouped,
            'categories' => $this->getCategories(),
        ]);
    }

    /**
     * Get user's subscribed feeds
     */
    public function getSubscriptions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $feeds = $this->db->fetchAllAssociative(
            "SELECT f.* FROM news_feeds f
             INNER JOIN user_feed_subscriptions ufs ON f.id = ufs.feed_id
             WHERE ufs.user_id = ? AND f.is_active = 1
             ORDER BY f.category, f.name",
            [$userId]
        );

        return JsonResponse::success($feeds);
    }

    /**
     * Subscribe to a feed
     */
    public function subscribe(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $feedId = RouteContext::fromRequest($request)->getRoute()->getArgument('feedId');

        // Verify feed exists
        $feed = $this->db->fetchAssociative('SELECT id FROM news_feeds WHERE id = ?', [$feedId]);
        if (!$feed) {
            throw new NotFoundException('Feed not found');
        }

        // Check if already subscribed
        $existing = $this->db->fetchOne(
            'SELECT 1 FROM user_feed_subscriptions WHERE user_id = ? AND feed_id = ?',
            [$userId, $feedId]
        );

        if (!$existing) {
            $this->db->insert('user_feed_subscriptions', [
                'user_id' => $userId,
                'feed_id' => $feedId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return JsonResponse::success(null, 'Subscribed successfully');
    }

    /**
     * Unsubscribe from a feed
     */
    public function unsubscribe(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $feedId = RouteContext::fromRequest($request)->getRoute()->getArgument('feedId');

        $this->db->delete('user_feed_subscriptions', [
            'user_id' => $userId,
            'feed_id' => $feedId,
        ]);

        return JsonResponse::success(null, 'Unsubscribed successfully');
    }

    /**
     * Get news items from subscribed feeds
     */
    public function getNews(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();
        $category = $queryParams['category'] ?? null;
        $feedId = $queryParams['feed_id'] ?? null;
        $onlySaved = isset($queryParams['saved']) && $queryParams['saved'] === '1';
        $onlyUnread = isset($queryParams['unread']) && $queryParams['unread'] === '1';
        $limit = min((int)($queryParams['limit'] ?? 50), 100);
        $offset = (int)($queryParams['offset'] ?? 0);

        $sql = "SELECT ni.*, f.name as feed_name, f.category as feed_category, f.icon_url as feed_icon,
                       COALESCE(uni.is_read, 0) as is_read,
                       COALESCE(uni.is_saved, 0) as is_saved
                FROM news_items ni
                INNER JOIN news_feeds f ON ni.feed_id = f.id
                INNER JOIN user_feed_subscriptions ufs ON f.id = ufs.feed_id AND ufs.user_id = ?
                LEFT JOIN user_news_interactions uni ON ni.id = uni.item_id AND uni.user_id = ?
                WHERE 1=1";
        $params = [$userId, $userId];

        if ($category) {
            $sql .= " AND f.category = ?";
            $params[] = $category;
        }

        if ($feedId) {
            $sql .= " AND f.id = ?";
            $params[] = $feedId;
        }

        if ($onlySaved) {
            $sql .= " AND uni.is_saved = 1";
        }

        if ($onlyUnread) {
            $sql .= " AND (uni.is_read IS NULL OR uni.is_read = 0)";
        }

        $sql .= " ORDER BY ni.published_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $items = $this->db->fetchAllAssociative($sql, $params);

        // Get total count
        $countSql = "SELECT COUNT(*) FROM news_items ni
                     INNER JOIN news_feeds f ON ni.feed_id = f.id
                     INNER JOIN user_feed_subscriptions ufs ON f.id = ufs.feed_id AND ufs.user_id = ?
                     LEFT JOIN user_news_interactions uni ON ni.id = uni.item_id AND uni.user_id = ?
                     WHERE 1=1";
        $countParams = [$userId, $userId];

        if ($category) {
            $countSql .= " AND f.category = ?";
            $countParams[] = $category;
        }
        if ($feedId) {
            $countSql .= " AND f.id = ?";
            $countParams[] = $feedId;
        }
        if ($onlySaved) {
            $countSql .= " AND uni.is_saved = 1";
        }
        if ($onlyUnread) {
            $countSql .= " AND (uni.is_read IS NULL OR uni.is_read = 0)";
        }

        $total = (int)$this->db->fetchOne($countSql, $countParams);

        return JsonResponse::success([
            'items' => $items,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * Mark item as read
     */
    public function markAsRead(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $itemId = RouteContext::fromRequest($request)->getRoute()->getArgument('itemId');

        $this->upsertInteraction($userId, $itemId, ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')]);

        return JsonResponse::success(null, 'Marked as read');
    }

    /**
     * Toggle saved status
     */
    public function toggleSaved(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $itemId = RouteContext::fromRequest($request)->getRoute()->getArgument('itemId');

        // Get current status
        $current = $this->db->fetchAssociative(
            'SELECT is_saved FROM user_news_interactions WHERE user_id = ? AND item_id = ?',
            [$userId, $itemId]
        );

        $newSaved = $current ? !$current['is_saved'] : true;
        $this->upsertInteraction($userId, $itemId, [
            'is_saved' => $newSaved ? 1 : 0,
            'saved_at' => $newSaved ? date('Y-m-d H:i:s') : null,
        ]);

        return JsonResponse::success(['is_saved' => $newSaved]);
    }

    /**
     * Refresh feeds (fetch new items)
     */
    public function refreshFeeds(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];
        $feedId = $data['feed_id'] ?? null;

        // Get feeds to refresh (user's subscribed feeds or specific feed)
        if ($feedId) {
            $feeds = $this->db->fetchAllAssociative(
                'SELECT f.* FROM news_feeds f WHERE f.id = ? AND f.is_active = 1',
                [$feedId]
            );
        } else {
            $feeds = $this->db->fetchAllAssociative(
                "SELECT DISTINCT f.* FROM news_feeds f
                 INNER JOIN user_feed_subscriptions ufs ON f.id = ufs.feed_id
                 WHERE ufs.user_id = ? AND f.is_active = 1
                 AND (f.last_fetched_at IS NULL OR f.last_fetched_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))",
                [$userId]
            );
        }

        $results = [];
        foreach ($feeds as $feed) {
            try {
                $count = $this->fetchFeedItems($feed);
                $results[$feed['name']] = ['success' => true, 'new_items' => $count];
            } catch (\Exception $e) {
                $results[$feed['name']] = ['success' => false, 'error' => $e->getMessage()];
                // Update feed with error
                $this->db->update('news_feeds', [
                    'fetch_error' => substr($e->getMessage(), 0, 500),
                    'last_fetched_at' => date('Y-m-d H:i:s'),
                ], ['id' => $feed['id']]);
            }
        }

        return JsonResponse::success([
            'refreshed' => count($feeds),
            'results' => $results,
        ]);
    }

    /**
     * Add custom feed
     */
    public function addFeed(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['url'])) {
            throw new ValidationException('URL is required');
        }

        $url = filter_var($data['url'], FILTER_VALIDATE_URL);
        if (!$url) {
            throw new ValidationException('Invalid URL');
        }

        // Check if feed already exists
        $existing = $this->db->fetchAssociative('SELECT id FROM news_feeds WHERE url = ?', [$url]);
        if ($existing) {
            // Just subscribe to existing feed
            $this->db->executeStatement(
                'INSERT IGNORE INTO user_feed_subscriptions (user_id, feed_id, created_at) VALUES (?, ?, ?)',
                [$userId, $existing['id'], date('Y-m-d H:i:s')]
            );
            return JsonResponse::success(['feed_id' => $existing['id']], 'Subscribed to existing feed');
        }

        // Try to fetch and parse the feed to validate it
        $feedData = $this->parseFeedUrl($url);

        $feedId = Uuid::uuid4()->toString();
        $this->db->insert('news_feeds', [
            'id' => $feedId,
            'name' => $data['name'] ?? $feedData['title'] ?? 'Custom Feed',
            'url' => $url,
            'category' => $data['category'] ?? 'other',
            'language' => $data['language'] ?? 'en',
            'icon_url' => $feedData['icon'] ?? null,
            'is_system' => 0,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Auto-subscribe
        $this->db->insert('user_feed_subscriptions', [
            'user_id' => $userId,
            'feed_id' => $feedId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Fetch initial items
        $feed = $this->db->fetchAssociative('SELECT * FROM news_feeds WHERE id = ?', [$feedId]);
        $this->fetchFeedItems($feed);

        return JsonResponse::created(['feed_id' => $feedId], 'Feed added successfully');
    }

    /**
     * Delete custom feed (only non-system feeds)
     */
    public function deleteFeed(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $feedId = RouteContext::fromRequest($request)->getRoute()->getArgument('feedId');

        $feed = $this->db->fetchAssociative('SELECT * FROM news_feeds WHERE id = ?', [$feedId]);
        if (!$feed) {
            throw new NotFoundException('Feed not found');
        }

        if ($feed['is_system']) {
            throw new ValidationException('Cannot delete system feeds');
        }

        $this->db->delete('news_feeds', ['id' => $feedId]);

        return JsonResponse::success(null, 'Feed deleted');
    }

    /**
     * Get unread count for dashboard
     */
    public function getUnreadCount(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $count = (int)$this->db->fetchOne(
            "SELECT COUNT(*) FROM news_items ni
             INNER JOIN news_feeds f ON ni.feed_id = f.id
             INNER JOIN user_feed_subscriptions ufs ON f.id = ufs.feed_id AND ufs.user_id = ?
             LEFT JOIN user_news_interactions uni ON ni.id = uni.item_id AND uni.user_id = ?
             WHERE (uni.is_read IS NULL OR uni.is_read = 0)
             AND ni.published_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$userId, $userId]
        );

        return JsonResponse::success(['unread_count' => $count]);
    }

    /**
     * Fetch full article content from original URL
     */
    public function fetchFullContent(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $itemId = RouteContext::fromRequest($request)->getRoute()->getArgument('itemId');

        // Get item
        $item = $this->db->fetchAssociative(
            'SELECT * FROM news_items WHERE id = ?',
            [$itemId]
        );

        if (!$item) {
            throw new NotFoundException('Article not found');
        }

        // Check if we already have full content cached
        if (!empty($item['full_content'])) {
            return JsonResponse::success([
                'content' => $item['full_content'],
                'cached' => true,
            ]);
        }

        // Fetch the article from the original URL
        try {
            $fullContent = $this->extractArticleContent($item['url']);

            // Cache it in the database
            if (!empty($fullContent)) {
                $this->db->update('news_items', [
                    'full_content' => $fullContent,
                ], ['id' => $itemId]);
            }

            return JsonResponse::success([
                'content' => $fullContent ?: $item['content'] ?: $item['description'],
                'cached' => false,
            ]);
        } catch (\Exception $e) {
            // Return existing content on error
            return JsonResponse::success([
                'content' => $item['content'] ?: $item['description'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract article content from a webpage
     */
    private function extractArticleContent(string $url): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: de,en-US;q=0.7,en;q=0.3',
            ],
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($html === false || $httpCode >= 400) {
            return null;
        }

        // Parse HTML
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);

        // Try to find article content using common selectors
        $contentSelectors = [
            // Common article containers
            "//article",
            "//*[contains(@class, 'article-content')]",
            "//*[contains(@class, 'article__content')]",
            "//*[contains(@class, 'article-body')]",
            "//*[contains(@class, 'post-content')]",
            "//*[contains(@class, 'entry-content')]",
            "//*[contains(@class, 'content-body')]",
            "//*[contains(@class, 'story-body')]",
            "//*[contains(@itemprop, 'articleBody')]",
            "//*[@id='article-body']",
            "//*[@id='content']",
            "//main",
            // Heise specific
            "//*[contains(@class, 'article__body')]",
            "//*[contains(@class, 'a-article-content')]",
            // Golem specific
            "//*[contains(@class, 'formatted')]",
            // Generic
            "//*[contains(@class, 'text')]//p/..",
        ];

        $content = null;
        foreach ($contentSelectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes && $nodes->length > 0) {
                // Get the node with the most text content
                $bestNode = null;
                $bestLength = 0;
                foreach ($nodes as $node) {
                    $text = trim($node->textContent);
                    if (mb_strlen($text) > $bestLength) {
                        $bestLength = mb_strlen($text);
                        $bestNode = $node;
                    }
                }
                if ($bestNode && $bestLength > 200) {
                    $content = $doc->saveHTML($bestNode);
                    break;
                }
            }
        }

        if (!$content) {
            // Fallback: get all paragraphs
            $paragraphs = $xpath->query('//p');
            $texts = [];
            foreach ($paragraphs as $p) {
                $text = trim($p->textContent);
                if (mb_strlen($text) > 50) {
                    $texts[] = '<p>' . htmlspecialchars($text) . '</p>';
                }
            }
            if (count($texts) > 2) {
                $content = implode("\n", $texts);
            }
        }

        if (!$content) {
            return null;
        }

        // Clean up the content
        $content = $this->cleanArticleHtml($content);

        return $content;
    }

    /**
     * Clean up extracted article HTML
     */
    private function cleanArticleHtml(string $html): string
    {
        // Remove scripts, styles, comments, ads, etc.
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        $html = preg_replace('/<nav[^>]*>.*?<\/nav>/is', '', $html);
        $html = preg_replace('/<aside[^>]*>.*?<\/aside>/is', '', $html);
        $html = preg_replace('/<footer[^>]*>.*?<\/footer>/is', '', $html);
        $html = preg_replace('/<header[^>]*>.*?<\/header>/is', '', $html);
        $html = preg_replace('/<form[^>]*>.*?<\/form>/is', '', $html);
        $html = preg_replace('/<iframe[^>]*>.*?<\/iframe>/is', '', $html);
        $html = preg_replace('/<noscript[^>]*>.*?<\/noscript>/is', '', $html);

        // Remove common ad/social/related content classes
        $html = preg_replace('/<[^>]*(advertisement|social|share|related|sidebar|comment|newsletter|subscribe|promo)[^>]*>.*?<\/[^>]+>/is', '', $html);

        // Remove empty tags
        $html = preg_replace('/<(\w+)[^>]*>\s*<\/\1>/i', '', $html);

        // Remove excessive whitespace
        $html = preg_replace('/\s+/', ' ', $html);
        $html = preg_replace('/>\s+</', '><', $html);

        // Only keep safe tags
        $html = strip_tags($html, '<p><br><a><strong><b><em><i><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><code><pre><img><figure><figcaption>');

        // Limit length
        if (mb_strlen($html) > 50000) {
            $html = mb_substr($html, 0, 50000) . '...';
        }

        return trim($html);
    }

    // ==================== Helper Methods ====================

    private function getCategories(): array
    {
        return [
            'tech' => ['name' => 'Technologie', 'icon' => 'cpu'],
            'gaming' => ['name' => 'Gaming', 'icon' => 'gamepad'],
            'general' => ['name' => 'Allgemein', 'icon' => 'newspaper'],
            'dev' => ['name' => 'Development', 'icon' => 'code'],
            'security' => ['name' => 'Security', 'icon' => 'shield'],
            'other' => ['name' => 'Sonstiges', 'icon' => 'folder'],
        ];
    }

    private function upsertInteraction(string $userId, string $itemId, array $data): void
    {
        $existing = $this->db->fetchOne(
            'SELECT 1 FROM user_news_interactions WHERE user_id = ? AND item_id = ?',
            [$userId, $itemId]
        );

        if ($existing) {
            $this->db->update('user_news_interactions', $data, [
                'user_id' => $userId,
                'item_id' => $itemId,
            ]);
        } else {
            $this->db->insert('user_news_interactions', array_merge([
                'user_id' => $userId,
                'item_id' => $itemId,
            ], $data));
        }
    }

    private function fetchFeedItems(array $feed): int
    {
        $feedData = $this->parseFeedUrl($feed['url']);
        $newCount = 0;

        foreach ($feedData['items'] as $item) {
            $guid = $item['guid'] ?? md5($item['link'] ?? $item['title']);

            // Check if item already exists
            $exists = $this->db->fetchOne(
                'SELECT 1 FROM news_items WHERE feed_id = ? AND guid = ?',
                [$feed['id'], $guid]
            );

            if (!$exists) {
                $this->db->insert('news_items', [
                    'id' => Uuid::uuid4()->toString(),
                    'feed_id' => $feed['id'],
                    'guid' => $guid,
                    'title' => mb_substr($item['title'] ?? 'No title', 0, 500),
                    'description' => $item['description'] ?? null,
                    'content' => $item['content'] ?? null,
                    'url' => $item['link'] ?? '',
                    'image_url' => $item['image'] ?? null,
                    'author' => $item['author'] ?? null,
                    'published_at' => $item['pubDate'] ?? date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $newCount++;
            }
        }

        // Update feed last_fetched_at
        $this->db->update('news_feeds', [
            'last_fetched_at' => date('Y-m-d H:i:s'),
            'fetch_error' => null,
        ], ['id' => $feed['id']]);

        return $newCount;
    }

    private function parseFeedUrl(string $url): array
    {
        // Use cURL for more reliable HTTP requests
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'KyuubiSoft News Reader/1.0 (compatible; RSS/Atom reader)',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Accept: application/rss+xml, application/atom+xml, application/xml, text/xml, */*',
            ],
        ]);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($content === false || !empty($error)) {
            throw new \Exception('Could not fetch feed: ' . ($error ?: 'Unknown error'));
        }

        if ($httpCode >= 400) {
            throw new \Exception('Feed returned HTTP ' . $httpCode);
        }

        if (empty($content)) {
            throw new \Exception('Feed returned empty response');
        }

        // Suppress XML errors
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        if ($xml === false) {
            throw new \Exception('Invalid XML feed');
        }

        $items = [];
        $title = '';
        $icon = null;

        // Try RSS 2.0 format
        if (isset($xml->channel)) {
            $title = (string)$xml->channel->title;
            $icon = (string)($xml->channel->image->url ?? '');

            foreach ($xml->channel->item as $item) {
                $items[] = $this->parseRssItem($item);
            }
        }
        // Try Atom format
        elseif ($xml->getName() === 'feed') {
            $title = (string)$xml->title;
            $icon = (string)($xml->icon ?? $xml->logo ?? '');

            foreach ($xml->entry as $entry) {
                $items[] = $this->parseAtomEntry($entry);
            }
        }
        // Try RDF format
        elseif (isset($xml->item)) {
            $title = (string)($xml->channel->title ?? 'Feed');
            foreach ($xml->item as $item) {
                $items[] = $this->parseRssItem($item);
            }
        }

        return [
            'title' => $title,
            'icon' => $icon ?: null,
            'items' => array_slice($items, 0, 50), // Limit to 50 items
        ];
    }

    private function parseRssItem(\SimpleXMLElement $item): array
    {
        $namespaces = $item->getNamespaces(true);
        $content = '';
        $image = null;

        // Try to get content:encoded
        if (isset($namespaces['content'])) {
            $contentNs = $item->children($namespaces['content']);
            $content = (string)($contentNs->encoded ?? '');
        }

        // Try to get media:content or enclosure for image
        if (isset($namespaces['media'])) {
            $mediaNs = $item->children($namespaces['media']);
            $image = (string)($mediaNs->content['url'] ?? $mediaNs->thumbnail['url'] ?? '');
        }
        if (!$image && isset($item->enclosure['url'])) {
            $encUrl = (string)$item->enclosure['url'];
            if (preg_match('/\.(jpg|jpeg|png|gif|webp)/i', $encUrl)) {
                $image = $encUrl;
            }
        }

        // Extract image from description if not found
        if (!$image) {
            $desc = (string)$item->description;
            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $desc, $matches)) {
                $image = $matches[1];
            }
        }

        $pubDate = null;
        if (!empty($item->pubDate)) {
            $pubDate = date('Y-m-d H:i:s', strtotime((string)$item->pubDate));
        }

        return [
            'guid' => (string)($item->guid ?? $item->link ?? ''),
            'title' => strip_tags((string)$item->title),
            'description' => $this->cleanHtml((string)$item->description),
            'content' => $content ? $this->cleanHtml($content) : null,
            'link' => (string)$item->link,
            'image' => $image ?: null,
            'author' => (string)($item->author ?? $item->creator ?? ''),
            'pubDate' => $pubDate,
        ];
    }

    private function parseAtomEntry(\SimpleXMLElement $entry): array
    {
        $link = '';
        foreach ($entry->link as $l) {
            if ((string)$l['rel'] === 'alternate' || empty($l['rel'])) {
                $link = (string)$l['href'];
                break;
            }
        }
        if (!$link && isset($entry->link['href'])) {
            $link = (string)$entry->link['href'];
        }

        $content = (string)($entry->content ?? $entry->summary ?? '');
        $image = null;

        // Try to extract image
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $content, $matches)) {
            $image = $matches[1];
        }

        $pubDate = null;
        $dateStr = (string)($entry->published ?? $entry->updated ?? '');
        if ($dateStr) {
            $pubDate = date('Y-m-d H:i:s', strtotime($dateStr));
        }

        return [
            'guid' => (string)($entry->id ?? $link),
            'title' => strip_tags((string)$entry->title),
            'description' => $this->cleanHtml((string)($entry->summary ?? '')),
            'content' => $this->cleanHtml($content),
            'link' => $link,
            'image' => $image,
            'author' => (string)($entry->author->name ?? ''),
            'pubDate' => $pubDate,
        ];
    }

    private function cleanHtml(string $html): string
    {
        // Remove scripts and styles
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);

        // Strip tags but keep some basic formatting
        $html = strip_tags($html, '<p><br><a><strong><em><ul><ol><li><h1><h2><h3><h4><blockquote><code><pre>');

        // Limit length
        if (mb_strlen($html) > 5000) {
            $html = mb_substr($html, 0, 5000) . '...';
        }

        return trim($html);
    }
}
