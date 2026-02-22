<?php

declare(strict_types=1);

namespace App\Modules\News\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use Doctrine\DBAL\Connection;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
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
            $sql .= " AND (ni.article_category = ? OR (ni.article_category IS NULL AND f.category = ?))";
            $params[] = $category;
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
            $countSql .= " AND (ni.article_category = ? OR (ni.article_category IS NULL AND f.category = ?))";
            $countParams[] = $category;
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
        try {
            $res = (new GuzzleClient([
                'timeout'         => 20,
                'connect_timeout' => 10,
                'allow_redirects' => ['max' => 5],
                'verify'          => false,
                'headers'         => [
                    'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'de,en-US;q=0.7,en;q=0.3',
                ],
            ]))->get($url);

            if ($res->getStatusCode() >= 400) {
                return null;
            }
            $html = $res->getBody()->getContents();
        } catch (GuzzleException) {
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
            'hardware' => ['name' => 'Hardware', 'icon' => 'chip'],
            'software' => ['name' => 'Software', 'icon' => 'window'],
            'mobile' => ['name' => 'Mobile', 'icon' => 'smartphone'],
            'ai' => ['name' => 'KI / AI', 'icon' => 'brain'],
            'science' => ['name' => 'Wissenschaft', 'icon' => 'beaker'],
            'entertainment' => ['name' => 'Entertainment', 'icon' => 'film'],
            'business' => ['name' => 'Business', 'icon' => 'briefcase'],
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
                $title = mb_substr($item['title'] ?? 'No title', 0, 500);
                $description = $item['description'] ?? null;
                $url = $item['link'] ?? '';

                // Determine article category based on content and URL
                $articleCategory = $this->categorizeArticle($title, $description, $feed['category'], $url);

                $this->db->insert('news_items', [
                    'id' => Uuid::uuid4()->toString(),
                    'feed_id' => $feed['id'],
                    'article_category' => $articleCategory,
                    'guid' => $guid,
                    'title' => $title,
                    'description' => $description,
                    'content' => $item['content'] ?? null,
                    'url' => $url,
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
        try {
            $res = (new GuzzleClient([
                'timeout'         => 15,
                'connect_timeout' => 10,
                'allow_redirects' => ['max' => 5],
                'verify'          => false,
                'headers'         => [
                    'User-Agent' => 'KyuubiSoft News Reader/1.0 (compatible; RSS/Atom reader)',
                    'Accept'     => 'application/rss+xml, application/atom+xml, application/xml, text/xml, */*',
                ],
            ]))->get($url);
        } catch (GuzzleException $e) {
            throw new \Exception('Could not fetch feed: ' . $e->getMessage());
        }

        if ($res->getStatusCode() >= 400) {
            throw new \Exception('Feed returned HTTP ' . $res->getStatusCode());
        }

        $content = $res->getBody()->getContents();

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

    /**
     * Categorize an article based on its title, description, and URL
     */
    private function categorizeArticle(string $title, ?string $description, string $feedCategory, ?string $url = null): string
    {
        $titleLower = mb_strtolower($title);
        $text = mb_strtolower($title . ' ' . ($description ?? ''));
        $urlLower = mb_strtolower($url ?? '');

        // URL path hints - very reliable indicators
        $urlPatterns = [
            'gaming' => ['/games/', '/gaming/', '/spiele/', '/esport/', '/playstation/', '/xbox/', '/nintendo/'],
            'hardware' => ['/hardware/', '/grafikkarte/', '/cpu/', '/gpu/', '/prozessor/', '/test/', '/review/', '/benchmark/'],
            'software' => ['/software/', '/download/', '/apps/', '/programme/'],
            'mobile' => ['/handy/', '/smartphone/', '/mobile/', '/iphone/', '/android/', '/tablet/'],
            'security' => ['/security/', '/sicherheit/', '/datenschutz/', '/privacy/'],
            'dev' => ['/developer/', '/entwickler/', '/programming/', '/code/', '/github/'],
            'ai' => ['/ki/', '/ai/', '/kuenstliche-intelligenz/', '/artificial-intelligence/', '/chatgpt/', '/machine-learning/'],
            'science' => ['/wissenschaft/', '/science/', '/forschung/', '/weltraum/', '/space/'],
            'entertainment' => ['/entertainment/', '/film/', '/movie/', '/serie/', '/musik/', '/streaming/'],
            'business' => ['/business/', '/wirtschaft/', '/finanzen/', '/boerse/', '/unternehmen/'],
        ];

        // Check URL patterns first (high confidence)
        foreach ($urlPatterns as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (mb_strpos($urlLower, $pattern) !== false) {
                    return $category;
                }
            }
        }

        // High-priority compound keywords (phrases that are very specific)
        $compoundKeywords = [
            'gaming' => [
                'game pass', 'playstation 5', 'playstation 4', 'xbox series', 'nintendo switch',
                'epic games', 'ea sports', 'call of duty', 'grand theft auto', 'world of warcraft',
                'league of legends', 'counter strike', 'battle royale', 'open world', 'early access',
                'release date', 'erscheinungsdatum', 'neues spiel', 'new game', 'spiel angekündigt',
                'gameplay trailer', 'game trailer', 'gaming pc', 'gaming laptop', 'gaming monitor',
                'kostenlos spielen', 'free to play', 'indie game', 'aaa game', 'triple a',
            ],
            'ai' => [
                'künstliche intelligenz', 'artificial intelligence', 'machine learning', 'deep learning',
                'large language model', 'generative ai', 'generative ki', 'neural network', 'neuronales netz',
                'stable diffusion', 'text to image', 'text-to-image', 'ki-modell', 'ai model',
                'ki-assistent', 'ai assistant', 'sprachmodell', 'language model', 'foundation model',
                'ki-startup', 'ai startup', 'ki-forschung', 'ai research', 'ki-regulierung',
            ],
            'hardware' => [
                'grafikkarte test', 'gpu test', 'cpu test', 'prozessor test', 'benchmark test',
                'graphics card', 'power supply', 'pc build', 'pc zusammenstellen', 'gaming pc bauen',
                'rtx 4090', 'rtx 4080', 'rtx 4070', 'rtx 5090', 'rtx 5080', 'rtx 5070',
                'radeon rx', 'geforce rtx', 'intel core', 'amd ryzen', 'apple m1', 'apple m2', 'apple m3', 'apple m4',
                'ddr5 ram', 'nvme ssd', 'pcie 5', 'usb-c', 'thunderbolt 4', 'wifi 7', 'wifi 6e',
            ],
            'mobile' => [
                'iphone 15', 'iphone 16', 'iphone 14', 'galaxy s24', 'galaxy s23', 'galaxy s25',
                'pixel 8', 'pixel 9', 'apple watch', 'samsung galaxy', 'google pixel',
                'ios 18', 'ios 17', 'android 15', 'android 14', 'mobile app', 'smartphone test',
                'handy test', 'tablet test', 'smartwatch test', 'foldable phone', 'falt-handy',
            ],
            'security' => [
                'data breach', 'datenleck', 'sicherheitslücke', 'zero day', 'zero-day',
                'ransomware angriff', 'ransomware attack', 'hacker angriff', 'cyber attack', 'cyberangriff',
                'zwei-faktor', 'two-factor', '2fa', 'passwort manager', 'password manager',
                'end-to-end', 'verschlüsselung', 'encryption', 'bsi warnt', 'cert warnt',
            ],
            'dev' => [
                'open source', 'visual studio', 'vs code', 'github copilot', 'software development',
                'web development', 'app development', 'neue version', 'new release', 'framework update',
                'programming language', 'programmiersprache', 'code review', 'pull request', 'git repository',
            ],
            'software' => [
                'windows 11', 'windows 12', 'macos sonoma', 'macos sequoia', 'linux kernel',
                'chrome update', 'firefox update', 'browser update', 'office 365', 'microsoft 365',
                'adobe creative', 'creative cloud', 'neue funktion', 'new feature', 'software update',
            ],
            'science' => [
                'james webb', 'hubble teleskop', 'black hole', 'schwarzes loch', 'mars mission',
                'moon mission', 'mondmission', 'klimawandel', 'climate change', 'erneuerbare energie',
                'renewable energy', 'quantencomputer', 'quantum computer', 'crispr', 'gen-editing',
            ],
            'entertainment' => [
                'neue staffel', 'new season', 'netflix serie', 'amazon prime', 'disney plus',
                'apple tv', 'streaming dienst', 'streaming service', 'box office', 'kino start',
                'oscar nominierung', 'grammy award', 'spotify wrapped', 'album release',
            ],
            'business' => [
                'quartalszahlen', 'quarterly earnings', 'ipo', 'börsengang', 'aktie steigt', 'aktie fällt',
                'milliarden dollar', 'billion dollar', 'übernahme', 'acquisition', 'merger',
                'entlassungen', 'layoffs', 'stellenabbau', 'startup funding', 'serie a', 'serie b',
            ],
        ];

        $scores = [];

        // Check compound keywords first (worth more points)
        foreach ($compoundKeywords as $category => $phrases) {
            foreach ($phrases as $phrase) {
                if (mb_strpos($text, $phrase) !== false) {
                    $scores[$category] = ($scores[$category] ?? 0) + (mb_strpos($titleLower, $phrase) !== false ? 8 : 4);
                }
            }
        }

        // Single keyword patterns (expanded)
        $categoryKeywords = [
            'gaming' => [
                // Platforms & Stores
                'playstation', 'xbox', 'nintendo', 'switch', 'steam', 'gog', 'uplay', 'origin', 'battle.net',
                // Gaming terms
                'videospiel', 'videogame', 'gaming', 'gamer', 'zocken', 'zockt', 'gameplay', 'speedrun',
                'esport', 'e-sport', 'twitch', 'streamer', 'lets play', 'walkthrough',
                'fps', 'mmorpg', 'rpg', 'moba', 'roguelike', 'metroidvania', 'souls-like', 'survival',
                'multiplayer', 'singleplayer', 'koop', 'co-op', 'pvp', 'pve',
                // Popular games
                'fortnite', 'minecraft', 'valorant', 'overwatch', 'apex legends', 'pubg',
                'gta', 'zelda', 'mario', 'pokemon', 'final fantasy', 'resident evil',
                'elden ring', 'dark souls', 'sekiro', 'bloodborne', 'armored core',
                'diablo', 'path of exile', 'lost ark', 'destiny',
                'cyberpunk', 'witcher', 'baldur', 'starfield', 'fallout', 'elder scrolls', 'skyrim',
                'assassin', 'far cry', 'watch dogs', 'ghost recon',
                'fifa', 'fc 24', 'fc 25', 'madden', 'nba 2k', 'nhl',
                'cod', 'battlefield', 'rainbow six', 'tarkov',
                'hogwarts legacy', 'spider-man', 'god of war', 'horizon',
                'monster hunter', 'dragon quest', 'persona', 'fire emblem',
                // Publishers/Developers
                'ubisoft', 'activision', 'blizzard', 'bethesda', 'rockstar', 'ea games',
                'cd projekt', 'fromsoftware', 'capcom', 'square enix', 'bandai namco', 'sega',
                'nintendo', 'sony interactive', 'xbox game studios', 'valve',
                // Hardware
                'ps5', 'ps4', 'playstation vr', 'psvr', 'dualsense',
                'controller', 'konsole', 'handheld', 'steam deck', 'rog ally',
                // Terms
                'dlc', 'addon', 'erweiterung', 'expansion', 'patch', 'season pass', 'battle pass',
                'early access', 'beta', 'demo', 'release', 'launch', 'ankündigung',
            ],
            'ai' => [
                // Companies
                'openai', 'anthropic', 'claude', 'google deepmind', 'deepmind', 'meta ai', 'mistral',
                'hugging face', 'huggingface', 'stability ai', 'cohere', 'inflection',
                // Products
                'chatgpt', 'gpt-4', 'gpt-5', 'gpt-4o', 'gpt4', 'gpt5',
                'gemini', 'bard', 'copilot', 'bing chat', 'perplexity',
                'dall-e', 'dalle', 'midjourney', 'stable diffusion', 'firefly', 'imagen',
                'llama', 'mistral', 'mixtral', 'phi-3', 'claude', 'opus', 'sonnet', 'haiku',
                'sora', 'runway', 'pika', 'kling',
                // Terms
                'künstliche intelligenz', 'ki-', ' ki ', 'k.i.',
                'artificial intelligence', 'ai-', ' ai ',
                'machine learning', 'maschinelles lernen', 'deep learning',
                'llm', 'sprachmodell', 'transformer', 'neural', 'neuronal',
                'chatbot', 'bot', 'assistent', 'assistant',
                'prompt', 'prompting', 'fine-tuning', 'finetuning', 'training',
                'halluzination', 'hallucination', 'alignment', 'rlhf',
                'text-to-image', 'image-to-text', 'text-to-video', 'multimodal',
                'embedding', 'vector', 'rag', 'retrieval',
            ],
            'security' => [
                // Threats
                'malware', 'ransomware', 'spyware', 'adware', 'trojaner', 'trojan', 'wurm', 'worm',
                'virus', 'phishing', 'spam', 'scam', 'betrug', 'fraud',
                'botnet', 'ddos', 'dos-angriff', 'brute force', 'keylogger',
                // Vulnerabilities
                'sicherheitslücke', 'schwachstelle', 'vulnerability', 'exploit', 'zero-day', 'cve-',
                'bug bounty', 'penetration test', 'pentest', 'audit',
                // Protection
                'firewall', 'antivirus', 'virenscanner', 'vpn', 'proxy', 'tor',
                'verschlüsselung', 'encryption', 'ssl', 'tls', 'https', 'e2e', 'end-to-end',
                'passwort', 'password', 'passkey', 'biometrie', 'biometric',
                'authentifizierung', 'authentication', '2fa', 'mfa', 'totp',
                // Privacy
                'datenschutz', 'privacy', 'dsgvo', 'gdpr', 'tracking', 'cookie', 'fingerprint',
                // Events
                'hack', 'hacker', 'hackerangriff', 'cyberangriff', 'cyberattack', 'angriff',
                'datenleck', 'data breach', 'leak', 'gehackt', 'kompromittiert',
                // Organizations
                'bsi', 'cert', 'cisa', 'nsa', 'bka', 'interpol',
                'kaspersky', 'norton', 'mcafee', 'bitdefender', 'avast',
            ],
            'hardware' => [
                // CPUs
                'prozessor', 'cpu', 'chip', 'soc', 'apu',
                'intel', 'core i9', 'core i7', 'core i5', 'core i3', 'xeon', 'raptor lake', 'meteor lake', 'arrow lake',
                'amd', 'ryzen', 'threadripper', 'epyc', 'zen 4', 'zen 5',
                'apple silicon', 'm1', 'm2', 'm3', 'm4', 'a17', 'a18',
                'qualcomm', 'snapdragon', 'arm', 'risc-v',
                // GPUs
                'gpu', 'grafikkarte', 'graphics card', 'grafikchip',
                'nvidia', 'geforce', 'rtx', 'gtx', 'quadro', 'tesla',
                'radeon', 'rx 7900', 'rx 7800', 'rx 7600', 'rdna',
                'intel arc', 'xe',
                // Memory & Storage
                'ram', 'arbeitsspeicher', 'speicher', 'ddr4', 'ddr5', 'lpddr',
                'ssd', 'nvme', 'pcie', 'festplatte', 'hdd', 'nas', 'raid',
                'sd-karte', 'usb-stick', 'externe festplatte',
                // Mainboard & PSU
                'mainboard', 'motherboard', 'sockel', 'socket', 'bios', 'uefi',
                'netzteil', 'power supply', 'psu', 'watt',
                // Cooling
                'kühler', 'cooler', 'lüfter', 'fan', 'wasserkühlung', 'aio', 'radiator',
                // Display
                'monitor', 'display', 'bildschirm', 'screen', 'panel',
                'oled', 'lcd', 'ips', 'va', 'tn', 'mini-led', 'micro-led',
                '4k', '8k', '1440p', 'qhd', 'ultrawide', '144hz', '240hz', '360hz',
                'hdr', 'freesync', 'g-sync', 'vrr',
                // Peripherals
                'tastatur', 'keyboard', 'mechanisch', 'mechanical',
                'maus', 'mouse', 'gaming maus', 'gaming mouse',
                'headset', 'kopfhörer', 'mikrofon', 'webcam',
                // General
                'benchmark', 'test', 'review', 'vergleich', 'comparison',
                'übertakten', 'overclocking', 'undervolt',
                'gehäuse', 'case', 'pc-bau', 'build', 'zusammenbau',
            ],
            'mobile' => [
                // Devices
                'smartphone', 'handy', 'mobiltelefon', 'tablet', 'phablet',
                'smartwatch', 'wearable', 'fitness tracker', 'smart ring',
                'earbuds', 'airpods', 'kopfhörer', 'true wireless',
                // Apple
                'iphone', 'ipad', 'apple watch', 'ios', 'ipados', 'watchos',
                'airpods', 'magsafe', 'lightning', 'airdrop', 'facetime',
                // Android
                'android', 'samsung', 'galaxy', 'pixel', 'oneplus', 'xiaomi', 'oppo', 'vivo',
                'huawei', 'honor', 'motorola', 'nothing phone', 'fairphone',
                'wear os', 'one ui', 'miui', 'coloros', 'oxygenos',
                // Features
                'kamera', 'camera', 'megapixel', 'mp', 'zoom', 'nachtmodus',
                'akku', 'battery', 'schnellladen', 'fast charging', 'wireless charging',
                'display', 'amoled', 'ltpo', 'always-on',
                '5g', 'lte', 'mobilfunk', 'esim', 'dual-sim',
                // Stores
                'app store', 'play store', 'google play', 'apk',
            ],
            'dev' => [
                // Languages
                'javascript', 'typescript', 'python', 'java', 'kotlin', 'swift', 'objective-c',
                'rust', 'golang', 'c++', 'c#', 'php', 'ruby', 'perl', 'scala', 'elixir',
                'html', 'css', 'sql', 'graphql', 'webassembly', 'wasm',
                // Frameworks
                'react', 'vue', 'angular', 'svelte', 'next.js', 'nuxt', 'remix', 'astro',
                'node.js', 'nodejs', 'deno', 'bun', 'express', 'fastify',
                'django', 'flask', 'fastapi', 'spring', 'laravel', 'rails',
                'flutter', 'react native', 'ionic', 'electron', 'tauri',
                '.net', 'dotnet', 'blazor', 'maui',
                // Tools
                'git', 'github', 'gitlab', 'bitbucket', 'svn',
                'docker', 'kubernetes', 'k8s', 'podman', 'container',
                'jenkins', 'circleci', 'github actions', 'gitlab ci', 'ci/cd', 'devops',
                'terraform', 'ansible', 'puppet', 'chef',
                'npm', 'yarn', 'pnpm', 'pip', 'cargo', 'maven', 'gradle',
                // IDEs
                'ide', 'visual studio', 'vscode', 'vs code', 'intellij', 'jetbrains',
                'webstorm', 'pycharm', 'android studio', 'xcode', 'neovim', 'vim',
                // Concepts
                'api', 'rest', 'graphql', 'webhook', 'microservice', 'serverless',
                'repository', 'commit', 'branch', 'merge', 'pull request', 'pr',
                'bug', 'debug', 'testing', 'unit test', 'integration test',
                'agile', 'scrum', 'kanban', 'sprint',
                'open source', 'opensource', 'oss', 'lizenz', 'license',
                'programmier', 'developer', 'entwickler', 'coder', 'coding',
            ],
            'software' => [
                // Operating Systems
                'windows', 'microsoft windows', 'win11', 'win10',
                'macos', 'mac os', 'osx', 'ventura', 'sonoma', 'sequoia',
                'linux', 'ubuntu', 'debian', 'fedora', 'arch', 'manjaro', 'mint', 'pop!_os',
                'chromeos', 'chrome os',
                // Browsers
                'chrome', 'chromium', 'firefox', 'safari', 'edge', 'opera', 'vivaldi', 'brave',
                'browser', 'webseite', 'website', 'erweiterung', 'extension', 'addon',
                // Office
                'office', 'word', 'excel', 'powerpoint', 'outlook', 'teams',
                'google docs', 'google sheets', 'notion', 'obsidian', 'evernote',
                'libreoffice', 'openoffice',
                // Creative
                'photoshop', 'illustrator', 'premiere', 'after effects', 'lightroom',
                'adobe', 'creative cloud', 'cc', 'affinity', 'gimp', 'inkscape',
                'figma', 'sketch', 'canva', 'blender', 'unity', 'unreal engine',
                'davinci resolve', 'final cut', 'audacity', 'ableton', 'fl studio',
                // Utilities
                'app', 'anwendung', 'programm', 'software', 'tool', 'utility',
                'update', 'patch', 'version', 'release', 'download', 'installer',
                'freeware', 'shareware', 'open source',
            ],
            'science' => [
                // Space
                'weltraum', 'space', 'all', 'kosmos', 'universum', 'universe',
                'nasa', 'esa', 'spacex', 'blue origin', 'rocket lab', 'roscosmos', 'jaxa', 'cnsa',
                'rakete', 'rocket', 'starship', 'falcon', 'ariane', 'sls', 'new glenn',
                'satellit', 'satellite', 'starlink', 'iss', 'raumstation', 'space station',
                'mars', 'mond', 'moon', 'venus', 'jupiter', 'saturn', 'asteroid',
                'teleskop', 'telescope', 'james webb', 'jwst', 'hubble', 'euclid',
                'astronomie', 'astronomy', 'kosmologie', 'cosmology',
                'schwarzes loch', 'black hole', 'supernova', 'exoplanet',
                // Physics & Chemistry
                'physik', 'physics', 'chemie', 'chemistry',
                'quanten', 'quantum', 'teilchen', 'particle', 'atom', 'elektron', 'photon',
                'cern', 'lhc', 'teilchenbeschleuniger', 'collider', 'fusion',
                // Biology & Medicine
                'biologie', 'biology', 'genetik', 'genetics', 'dna', 'rna', 'genom', 'genome',
                'crispr', 'gentherapie', 'gene therapy', 'stammzellen', 'stem cell',
                'medizin', 'medicine', 'klinisch', 'clinical', 'studie', 'study',
                'impfstoff', 'vaccine', 'therapie', 'therapy', 'behandlung', 'treatment',
                // Environment
                'klima', 'climate', 'klimawandel', 'erderwärmung', 'global warming',
                'umwelt', 'environment', 'nachhaltigkeit', 'sustainability',
                'erneuerbar', 'renewable', 'solar', 'wind', 'wasserstoff', 'hydrogen',
                'co2', 'emission', 'treibhausgas', 'greenhouse',
                // General
                'wissenschaft', 'science', 'forschung', 'research', 'forscher', 'researcher',
                'experiment', 'labor', 'laboratory', 'entdeckung', 'discovery',
                'nobelpreis', 'nobel prize',
            ],
            'entertainment' => [
                // Film & TV
                'film', 'movie', 'kino', 'cinema', 'blockbuster', 'box office',
                'serie', 'series', 'staffel', 'season', 'episode', 'folge',
                'trailer', 'teaser', 'premier', 'premiere', 'release',
                // Streaming
                'netflix', 'amazon prime', 'prime video', 'disney+', 'disney plus',
                'apple tv', 'hbo max', 'max', 'paramount+', 'peacock', 'hulu',
                'crunchyroll', 'anime', 'manga',
                'streaming', 'stream', 'binge', 'on demand',
                // Music
                'musik', 'music', 'song', 'album', 'single', 'track',
                'spotify', 'apple music', 'tidal', 'deezer', 'soundcloud',
                'konzert', 'concert', 'tour', 'festival', 'live',
                'künstler', 'artist', 'band', 'sänger', 'singer', 'rapper',
                'grammy', 'mtv', 'billboard', 'charts',
                // Social Media
                'youtube', 'tiktok', 'instagram', 'facebook', 'twitter', 'x.com',
                'reddit', 'discord', 'snapchat', 'threads', 'bluesky', 'mastodon',
                'social media', 'soziale medien', 'creator', 'influencer', 'viral',
                // Celebrities
                'promi', 'celebrity', 'star', 'hollywood', 'oscar', 'emmy', 'golden globe',
            ],
            'business' => [
                // Companies
                'unternehmen', 'company', 'firma', 'konzern', 'corporation', 'inc', 'gmbh', 'ag',
                'startup', 'start-up', 'gründer', 'founder', 'gründung',
                // People
                'ceo', 'cto', 'cfo', 'geschäftsführer', 'vorstand', 'board',
                'manager', 'director', 'executive',
                // Finance
                'aktie', 'stock', 'share', 'börse', 'stock market', 'nasdaq', 'dax', 'dow jones',
                'investor', 'investment', 'investition', 'venture capital', 'vc',
                'ipo', 'börsengang', 'bewertung', 'valuation', 'unicorn',
                // Business Events
                'übernahme', 'acquisition', 'merger', 'fusion', 'aufkauf',
                'partnerschaft', 'partnership', 'kooperation', 'cooperation',
                'entlassung', 'layoff', 'stellenabbau', 'restrukturierung',
                // Financials
                'umsatz', 'revenue', 'gewinn', 'profit', 'verlust', 'loss', 'marge', 'margin',
                'quartal', 'quarter', 'bilanz', 'earnings', 'jahresbericht', 'annual report',
                'prognose', 'forecast', 'guidance', 'ausblick', 'outlook',
                // Markets
                'markt', 'market', 'marktanteil', 'market share', 'wettbewerb', 'competition',
                'branche', 'industry', 'sektor', 'sector',
            ],
            'tech' => [
                // General tech
                'technologie', 'technology', 'tech', 'digital', 'innovation', 'trend',
                // Internet & Network
                'internet', 'web', 'online', 'cloud', 'saas', 'paas', 'iaas',
                'server', 'hosting', 'datacenter', 'rechenzentrum',
                'netzwerk', 'network', 'router', 'switch', 'ethernet',
                'wlan', 'wifi', 'bluetooth', 'zigbee', 'matter',
                // Smart Home & IoT
                'smart home', 'smarthome', 'hausautomation', 'home automation',
                'iot', 'internet of things', 'sensor', 'alexa', 'google home', 'siri',
                'smart speaker', 'smart display', 'smart tv',
                // Gadgets
                'gadget', 'gerät', 'device', 'zubehör', 'accessory',
                'vr', 'virtual reality', 'ar', 'augmented reality', 'mixed reality', 'xr',
                'drohne', 'drone', 'roboter', 'robot', '3d-drucker', '3d printer',
                // Energy
                'elektro', 'electric', 'e-auto', 'elektroauto', 'ev',
                'akku', 'batterie', 'battery', 'laden', 'charging', 'ladestation',
                'solar', 'photovoltaik', 'balkonkraftwerk',
                // Big Tech
                'apple', 'google', 'microsoft', 'amazon', 'meta', 'facebook',
                'alphabet', 'tesla', 'nvidia', 'intel', 'amd', 'qualcomm',
                'samsung', 'sony', 'lg', 'huawei', 'xiaomi', 'lenovo', 'dell', 'hp', 'asus',
            ],
        ];

        // Check single keywords
        foreach ($categoryKeywords as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_strpos($text, $keyword) !== false) {
                    // Title matches worth more
                    if (mb_strpos($titleLower, $keyword) !== false) {
                        $scores[$category] = ($scores[$category] ?? 0) + 3;
                    } else {
                        $scores[$category] = ($scores[$category] ?? 0) + 1;
                    }
                }
            }
        }

        // Apply category boost based on feed category (slight hint, not override)
        if (!empty($feedCategory) && isset($scores[$feedCategory])) {
            $scores[$feedCategory] += 2;
        }

        // If we found matching keywords, return the highest scoring category
        if (!empty($scores)) {
            arsort($scores);
            $topCategory = array_key_first($scores);
            $topScore = $scores[$topCategory];

            // Only return if score is meaningful (at least 2 matches or 1 strong match)
            if ($topScore >= 3) {
                return $topCategory;
            }
        }

        // Fallback to feed category if valid
        $validCategories = ['tech', 'gaming', 'general', 'dev', 'security', 'hardware', 'software', 'mobile', 'ai', 'science', 'entertainment', 'business', 'other'];
        if (in_array($feedCategory, $validCategories)) {
            return $feedCategory;
        }

        return 'other';
    }
}
