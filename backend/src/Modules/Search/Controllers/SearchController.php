<?php

declare(strict_types=1);

namespace App\Modules\Search\Controllers;

use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SearchController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    public function search(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();
        $query = trim($queryParams['q'] ?? '');
        $type = $queryParams['type'] ?? 'all'; // all, documents, lists
        $limit = min(20, max(1, (int) ($queryParams['limit'] ?? 10)));

        if (strlen($query) < 2) {
            return JsonResponse::success([
                'documents' => [],
                'lists' => [],
                'total' => 0
            ]);
        }

        $searchTerm = '%' . $query . '%';
        $results = [
            'documents' => [],
            'lists' => [],
            'total' => 0
        ];

        // Search documents
        if ($type === 'all' || $type === 'documents') {
            $documents = $this->db->fetchAllAssociative(
                'SELECT id, title, format, content, updated_at
                 FROM documents
                 WHERE user_id = ? AND (title LIKE ? OR content LIKE ?)
                 ORDER BY updated_at DESC
                 LIMIT ?',
                [$userId, $searchTerm, $searchTerm, $limit],
                [\PDO::PARAM_STR, \PDO::PARAM_STR, \PDO::PARAM_STR, \PDO::PARAM_INT]
            );

            // Add snippet for each result
            foreach ($documents as &$doc) {
                $doc['snippet'] = $this->createSnippet($doc['content'] ?? '', $query);
                $doc['type'] = 'document';
                unset($doc['content']); // Don't send full content
            }
            $results['documents'] = $documents;
        }

        // Search lists
        if ($type === 'all' || $type === 'lists') {
            $lists = $this->db->fetchAllAssociative(
                'SELECT l.id, l.title, l.description, l.type as list_type, l.color, l.updated_at,
                        (SELECT COUNT(*) FROM list_items WHERE list_id = l.id) as item_count
                 FROM lists l
                 WHERE l.user_id = ? AND (l.title LIKE ? OR l.description LIKE ?)
                 ORDER BY l.updated_at DESC
                 LIMIT ?',
                [$userId, $searchTerm, $searchTerm, $limit],
                [\PDO::PARAM_STR, \PDO::PARAM_STR, \PDO::PARAM_STR, \PDO::PARAM_INT]
            );

            foreach ($lists as &$list) {
                $list['type'] = 'list';
                $list['snippet'] = $list['description'] ? $this->createSnippet($list['description'], $query) : null;
            }
            $results['lists'] = $lists;

            // Also search in list items
            $listItems = $this->db->fetchAllAssociative(
                'SELECT li.id as item_id, li.content, li.list_id, l.title as list_title, l.color
                 FROM list_items li
                 JOIN lists l ON l.id = li.list_id
                 WHERE l.user_id = ? AND li.content LIKE ?
                 ORDER BY li.created_at DESC
                 LIMIT ?',
                [$userId, $searchTerm, $limit],
                [\PDO::PARAM_STR, \PDO::PARAM_STR, \PDO::PARAM_INT]
            );

            foreach ($listItems as &$item) {
                $item['type'] = 'list_item';
                $item['id'] = $item['list_id'];
                $item['title'] = $item['list_title'];
                $item['snippet'] = $this->createSnippet($item['content'], $query);
            }

            // Merge and deduplicate
            $listIds = array_column($results['lists'], 'id');
            foreach ($listItems as $item) {
                if (!in_array($item['id'], $listIds)) {
                    $results['lists'][] = $item;
                    $listIds[] = $item['id'];
                }
            }
        }

        $results['total'] = count($results['documents']) + count($results['lists']);

        return JsonResponse::success($results);
    }

    private function createSnippet(string $content, string $query, int $length = 150): string
    {
        // Strip HTML tags for text search
        $text = strip_tags($content);
        $text = html_entity_decode($text);

        // Try to find the query in the text
        $pos = stripos($text, $query);

        if ($pos === false) {
            // Query not found, return beginning of text
            return mb_substr($text, 0, $length) . (mb_strlen($text) > $length ? '...' : '');
        }

        // Calculate start position to center the query
        $start = max(0, $pos - (int)($length / 2));
        $snippet = mb_substr($text, $start, $length);

        // Clean up snippet
        if ($start > 0) {
            $snippet = '...' . ltrim($snippet);
        }
        if ($start + $length < mb_strlen($text)) {
            $snippet = rtrim($snippet) . '...';
        }

        return $snippet;
    }
}
