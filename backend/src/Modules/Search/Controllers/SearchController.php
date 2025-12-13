<?php

declare(strict_types=1);

namespace App\Modules\Search\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Services\ProjectAccessService;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SearchController
{
    private const SEARCHABLE_TYPES = [
        'all', 'documents', 'lists', 'projects', 'kanban', 'snippets',
        'bookmarks', 'connections', 'passwords', 'checklists', 'tickets',
        'invoices', 'wiki', 'monitors', 'time_entries'
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly ProjectAccessService $projectAccess
    ) {}

    public function search(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();
        $query = trim($queryParams['q'] ?? '');
        $type = $queryParams['type'] ?? 'all';
        $limit = min(20, max(1, (int) ($queryParams['limit'] ?? 10)));

        if (strlen($query) < 2) {
            return JsonResponse::success($this->emptyResults());
        }

        if (!in_array($type, self::SEARCHABLE_TYPES)) {
            $type = 'all';
        }

        $searchTerm = '%' . $query . '%';
        $results = $this->emptyResults();

        // Check project access for restricted users
        $isRestricted = $this->projectAccess->isUserRestricted($userId);
        $accessibleProjectIds = $isRestricted ? $this->projectAccess->getUserAccessibleProjectIds($userId) : null;

        // Search documents
        if ($type === 'all' || $type === 'documents') {
            $results['documents'] = $this->searchDocuments($userId, $searchTerm, $limit, $accessibleProjectIds);
        }

        // Search lists
        if ($type === 'all' || $type === 'lists') {
            $results['lists'] = $this->searchLists($userId, $searchTerm, $query, $limit, $accessibleProjectIds);
        }

        // Search projects
        if ($type === 'all' || $type === 'projects') {
            $results['projects'] = $this->searchProjects($userId, $searchTerm, $limit, $accessibleProjectIds);
        }

        // Search kanban boards
        if ($type === 'all' || $type === 'kanban') {
            $results['kanban'] = $this->searchKanban($userId, $searchTerm, $query, $limit, $accessibleProjectIds);
        }

        // Search snippets
        if ($type === 'all' || $type === 'snippets') {
            $results['snippets'] = $this->searchSnippets($userId, $searchTerm, $query, $limit);
        }

        // Search bookmarks
        if ($type === 'all' || $type === 'bookmarks') {
            $results['bookmarks'] = $this->searchBookmarks($userId, $searchTerm, $limit);
        }

        // Search connections
        if ($type === 'all' || $type === 'connections') {
            $results['connections'] = $this->searchConnections($userId, $searchTerm, $limit);
        }

        // Search passwords (name/username only)
        if ($type === 'all' || $type === 'passwords') {
            $results['passwords'] = $this->searchPasswords($userId, $searchTerm, $limit);
        }

        // Search checklists
        if ($type === 'all' || $type === 'checklists') {
            $results['checklists'] = $this->searchChecklists($userId, $searchTerm, $limit);
        }

        // Search tickets
        if ($type === 'all' || $type === 'tickets') {
            $results['tickets'] = $this->searchTickets($userId, $searchTerm, $limit, $accessibleProjectIds);
        }

        // Search invoices
        if ($type === 'all' || $type === 'invoices') {
            $results['invoices'] = $this->searchInvoices($userId, $searchTerm, $limit, $accessibleProjectIds);
        }

        // Search wiki pages
        if ($type === 'all' || $type === 'wiki') {
            $results['wiki'] = $this->searchWiki($userId, $searchTerm, $query, $limit, $accessibleProjectIds);
        }

        // Search uptime monitors
        if ($type === 'all' || $type === 'monitors') {
            $results['monitors'] = $this->searchMonitors($userId, $searchTerm, $limit, $accessibleProjectIds);
        }

        // Search time entries
        if ($type === 'all' || $type === 'time_entries') {
            $results['time_entries'] = $this->searchTimeEntries($userId, $searchTerm, $limit, $accessibleProjectIds);
        }

        // Calculate total
        $results['total'] = 0;
        foreach ($results as $key => $value) {
            if ($key !== 'total' && is_array($value)) {
                $results['total'] += count($value);
            }
        }

        return JsonResponse::success($results);
    }

    private function emptyResults(): array
    {
        return [
            'documents' => [],
            'lists' => [],
            'projects' => [],
            'kanban' => [],
            'snippets' => [],
            'bookmarks' => [],
            'connections' => [],
            'passwords' => [],
            'checklists' => [],
            'tickets' => [],
            'invoices' => [],
            'wiki' => [],
            'monitors' => [],
            'time_entries' => [],
            'total' => 0
        ];
    }

    private function searchDocuments(string $userId, string $searchTerm, int $limit, ?array $accessibleProjectIds): array
    {
        $sql = 'SELECT id, title, format, content, project_id, updated_at FROM documents WHERE user_id = ? AND (title LIKE ? OR content LIKE ?)';
        $params = [$userId, $searchTerm, $searchTerm];

        if ($accessibleProjectIds !== null) {
            if (empty($accessibleProjectIds)) return [];
            $placeholders = implode(',', array_fill(0, count($accessibleProjectIds), '?'));
            $sql .= " AND project_id IN ({$placeholders})";
            $params = array_merge($params, $accessibleProjectIds);
        }

        $sql .= ' ORDER BY updated_at DESC LIMIT ' . $limit;
        $documents = $this->db->fetchAllAssociative($sql, $params);

        foreach ($documents as &$doc) {
            $doc['snippet'] = $this->createSnippet($doc['content'] ?? '', substr($searchTerm, 1, -1));
            $doc['type'] = 'document';
            unset($doc['content']);
        }
        return $documents;
    }

    private function searchLists(string $userId, string $searchTerm, string $query, int $limit, ?array $accessibleProjectIds): array
    {
        $sql = 'SELECT l.id, l.title, l.description, l.type as list_type, l.color, l.project_id, l.updated_at,
                (SELECT COUNT(*) FROM list_items WHERE list_id = l.id) as item_count
                FROM lists l WHERE l.user_id = ? AND (l.title LIKE ? OR l.description LIKE ?)';
        $params = [$userId, $searchTerm, $searchTerm];

        if ($accessibleProjectIds !== null) {
            if (empty($accessibleProjectIds)) return [];
            $placeholders = implode(',', array_fill(0, count($accessibleProjectIds), '?'));
            $sql .= " AND l.project_id IN ({$placeholders})";
            $params = array_merge($params, $accessibleProjectIds);
        }

        $sql .= ' ORDER BY l.updated_at DESC LIMIT ' . $limit;
        $lists = $this->db->fetchAllAssociative($sql, $params);

        foreach ($lists as &$list) {
            $list['type'] = 'list';
            $list['snippet'] = $list['description'] ? $this->createSnippet($list['description'], $query) : null;
        }
        return $lists;
    }

    private function searchProjects(string $userId, string $searchTerm, int $limit, ?array $accessibleProjectIds): array
    {
        $sql = 'SELECT id, name, description, color, status, updated_at FROM projects WHERE user_id = ? AND (name LIKE ? OR description LIKE ?)';
        $params = [$userId, $searchTerm, $searchTerm];

        if ($accessibleProjectIds !== null) {
            if (empty($accessibleProjectIds)) return [];
            $placeholders = implode(',', array_fill(0, count($accessibleProjectIds), '?'));
            $sql .= " AND id IN ({$placeholders})";
            $params = array_merge($params, $accessibleProjectIds);
        }

        $sql .= ' ORDER BY updated_at DESC LIMIT ' . $limit;
        $projects = $this->db->fetchAllAssociative($sql, $params);

        foreach ($projects as &$project) {
            $project['type'] = 'project';
            $project['snippet'] = $project['description'] ? $this->createSnippet($project['description'], substr($searchTerm, 1, -1)) : null;
        }
        return $projects;
    }

    private function searchKanban(string $userId, string $searchTerm, string $query, int $limit, ?array $accessibleProjectIds): array
    {
        $sql = 'SELECT id, title, description, color, project_id, updated_at FROM kanban_boards WHERE user_id = ? AND (title LIKE ? OR description LIKE ?)';
        $params = [$userId, $searchTerm, $searchTerm];

        if ($accessibleProjectIds !== null) {
            if (empty($accessibleProjectIds)) return [];
            $placeholders = implode(',', array_fill(0, count($accessibleProjectIds), '?'));
            $sql .= " AND project_id IN ({$placeholders})";
            $params = array_merge($params, $accessibleProjectIds);
        }

        $sql .= ' ORDER BY updated_at DESC LIMIT ' . $limit;
        $boards = $this->db->fetchAllAssociative($sql, $params);

        foreach ($boards as &$board) {
            $board['type'] = 'kanban_board';
            $board['snippet'] = $board['description'] ? $this->createSnippet($board['description'], $query) : null;
        }
        return $boards;
    }

    private function searchSnippets(string $userId, string $searchTerm, string $query, int $limit): array
    {
        $snippets = $this->db->fetchAllAssociative(
            'SELECT id, title, description, language, content, updated_at FROM snippets
             WHERE user_id = ? AND (title LIKE ? OR description LIKE ? OR content LIKE ?)
             ORDER BY updated_at DESC LIMIT ' . $limit,
            [$userId, $searchTerm, $searchTerm, $searchTerm]
        );

        foreach ($snippets as &$snippet) {
            $snippet['type'] = 'snippet';
            $snippet['snippet'] = $this->createSnippet($snippet['content'] ?? $snippet['description'] ?? '', $query);
            unset($snippet['content']);
        }
        return $snippets;
    }

    private function searchBookmarks(string $userId, string $searchTerm, int $limit): array
    {
        $bookmarks = $this->db->fetchAllAssociative(
            'SELECT id, title, url, description, group_id, created_at FROM bookmarks
             WHERE user_id = ? AND (title LIKE ? OR url LIKE ? OR description LIKE ?)
             ORDER BY created_at DESC LIMIT ' . $limit,
            [$userId, $searchTerm, $searchTerm, $searchTerm]
        );

        foreach ($bookmarks as &$bookmark) {
            $bookmark['type'] = 'bookmark';
            $bookmark['snippet'] = $bookmark['description'] ? $this->createSnippet($bookmark['description'], substr($searchTerm, 1, -1)) : $bookmark['url'];
        }
        return $bookmarks;
    }

    private function searchConnections(string $userId, string $searchTerm, int $limit): array
    {
        $connections = $this->db->fetchAllAssociative(
            'SELECT id, name, type, host, description, color, updated_at FROM connections
             WHERE user_id = ? AND (name LIKE ? OR host LIKE ? OR description LIKE ?)
             ORDER BY updated_at DESC LIMIT ' . $limit,
            [$userId, $searchTerm, $searchTerm, $searchTerm]
        );

        foreach ($connections as &$conn) {
            $conn['type_entity'] = $conn['type'];
            $conn['type'] = 'connection';
            $conn['snippet'] = $conn['host'];
        }
        return $connections;
    }

    private function searchPasswords(string $userId, string $searchTerm, int $limit): array
    {
        $passwords = $this->db->fetchAllAssociative(
            'SELECT id, name, username, url, category_id, updated_at FROM passwords
             WHERE user_id = ? AND (name LIKE ? OR username LIKE ? OR url LIKE ?)
             ORDER BY updated_at DESC LIMIT ' . $limit,
            [$userId, $searchTerm, $searchTerm, $searchTerm]
        );

        foreach ($passwords as &$pwd) {
            $pwd['type'] = 'password';
            $pwd['snippet'] = $pwd['username'] ? 'User: ' . $pwd['username'] : $pwd['url'];
        }
        return $passwords;
    }

    private function searchChecklists(string $userId, string $searchTerm, int $limit): array
    {
        $checklists = $this->db->fetchAllAssociative(
            'SELECT id, title, description, color, updated_at FROM shared_checklists
             WHERE user_id = ? AND (title LIKE ? OR description LIKE ?)
             ORDER BY updated_at DESC LIMIT ' . $limit,
            [$userId, $searchTerm, $searchTerm]
        );

        foreach ($checklists as &$checklist) {
            $checklist['type'] = 'checklist';
            $checklist['snippet'] = $checklist['description'] ? $this->createSnippet($checklist['description'], substr($searchTerm, 1, -1)) : null;
        }
        return $checklists;
    }

    private function searchTickets(string $userId, string $searchTerm, int $limit, ?array $accessibleProjectIds): array
    {
        $sql = 'SELECT id, title, description, status, priority, project_id, updated_at FROM tickets WHERE user_id = ? AND (title LIKE ? OR description LIKE ?)';
        $params = [$userId, $searchTerm, $searchTerm];

        if ($accessibleProjectIds !== null) {
            if (empty($accessibleProjectIds)) return [];
            $placeholders = implode(',', array_fill(0, count($accessibleProjectIds), '?'));
            $sql .= " AND project_id IN ({$placeholders})";
            $params = array_merge($params, $accessibleProjectIds);
        }

        $sql .= ' ORDER BY updated_at DESC LIMIT ' . $limit;
        $tickets = $this->db->fetchAllAssociative($sql, $params);

        foreach ($tickets as &$ticket) {
            $ticket['type'] = 'ticket';
            $ticket['snippet'] = $ticket['description'] ? $this->createSnippet($ticket['description'], substr($searchTerm, 1, -1)) : null;
        }
        return $tickets;
    }

    private function searchInvoices(string $userId, string $searchTerm, int $limit, ?array $accessibleProjectIds): array
    {
        $sql = 'SELECT id, invoice_number, client_name, status, total, project_id, updated_at FROM invoices WHERE user_id = ? AND (invoice_number LIKE ? OR client_name LIKE ?)';
        $params = [$userId, $searchTerm, $searchTerm];

        if ($accessibleProjectIds !== null) {
            if (empty($accessibleProjectIds)) return [];
            $placeholders = implode(',', array_fill(0, count($accessibleProjectIds), '?'));
            $sql .= " AND project_id IN ({$placeholders})";
            $params = array_merge($params, $accessibleProjectIds);
        }

        $sql .= ' ORDER BY updated_at DESC LIMIT ' . $limit;
        $invoices = $this->db->fetchAllAssociative($sql, $params);

        foreach ($invoices as &$invoice) {
            $invoice['type'] = 'invoice';
            $invoice['title'] = $invoice['invoice_number'];
            $invoice['snippet'] = $invoice['client_name'] . ' - ' . number_format((float)$invoice['total'], 2, ',', '.') . ' €';
        }
        return $invoices;
    }

    private function searchWiki(string $userId, string $searchTerm, string $query, int $limit, ?array $accessibleProjectIds): array
    {
        $sql = 'SELECT id, title, content, project_id, updated_at FROM wiki_pages WHERE user_id = ? AND (title LIKE ? OR content LIKE ?)';
        $params = [$userId, $searchTerm, $searchTerm];

        if ($accessibleProjectIds !== null) {
            if (empty($accessibleProjectIds)) return [];
            $placeholders = implode(',', array_fill(0, count($accessibleProjectIds), '?'));
            $sql .= " AND project_id IN ({$placeholders})";
            $params = array_merge($params, $accessibleProjectIds);
        }

        $sql .= ' ORDER BY updated_at DESC LIMIT ' . $limit;
        $pages = $this->db->fetchAllAssociative($sql, $params);

        foreach ($pages as &$page) {
            $page['type'] = 'wiki';
            $page['snippet'] = $this->createSnippet($page['content'] ?? '', $query);
            unset($page['content']);
        }
        return $pages;
    }

    private function searchMonitors(string $userId, string $searchTerm, int $limit, ?array $accessibleProjectIds): array
    {
        $sql = 'SELECT id, name, url, type, current_status, project_id, updated_at FROM uptime_monitors WHERE user_id = ? AND (name LIKE ? OR url LIKE ?)';
        $params = [$userId, $searchTerm, $searchTerm];

        if ($accessibleProjectIds !== null) {
            if (empty($accessibleProjectIds)) return [];
            $placeholders = implode(',', array_fill(0, count($accessibleProjectIds), '?'));
            $sql .= " AND project_id IN ({$placeholders})";
            $params = array_merge($params, $accessibleProjectIds);
        }

        $sql .= ' ORDER BY updated_at DESC LIMIT ' . $limit;
        $monitors = $this->db->fetchAllAssociative($sql, $params);

        foreach ($monitors as &$monitor) {
            $monitor['type_entity'] = $monitor['type'];
            $monitor['type'] = 'monitor';
            $monitor['title'] = $monitor['name'];
            $monitor['snippet'] = $monitor['url'] . ' (' . $monitor['current_status'] . ')';
        }
        return $monitors;
    }

    private function searchTimeEntries(string $userId, string $searchTerm, int $limit, ?array $accessibleProjectIds): array
    {
        $sql = 'SELECT te.id, te.task_name, te.description, te.project_id, te.started_at, p.name as project_name
                FROM time_entries te
                LEFT JOIN projects p ON te.project_id = p.id
                WHERE te.user_id = ? AND (te.task_name LIKE ? OR te.description LIKE ?)';
        $params = [$userId, $searchTerm, $searchTerm];

        if ($accessibleProjectIds !== null) {
            if (empty($accessibleProjectIds)) return [];
            $placeholders = implode(',', array_fill(0, count($accessibleProjectIds), '?'));
            $sql .= " AND te.project_id IN ({$placeholders})";
            $params = array_merge($params, $accessibleProjectIds);
        }

        $sql .= ' ORDER BY te.started_at DESC LIMIT ' . $limit;
        $entries = $this->db->fetchAllAssociative($sql, $params);

        foreach ($entries as &$entry) {
            $entry['type'] = 'time_entry';
            $entry['title'] = $entry['task_name'];
            $entry['snippet'] = ($entry['project_name'] ?? 'Kein Projekt') . ' - ' . $entry['started_at'];
        }
        return $entries;
    }

    private function createSnippet(string $content, string $query, int $length = 150): string
    {
        $text = strip_tags($content);
        $text = html_entity_decode($text);

        $pos = stripos($text, $query);

        if ($pos === false) {
            return mb_substr($text, 0, $length) . (mb_strlen($text) > $length ? '...' : '');
        }

        $start = max(0, $pos - (int)($length / 2));
        $snippet = mb_substr($text, $start, $length);

        if ($start > 0) {
            $snippet = '...' . ltrim($snippet);
        }
        if ($start + $length < mb_strlen($text)) {
            $snippet = rtrim($snippet) . '...';
        }

        return $snippet;
    }
}
