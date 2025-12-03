<?php

declare(strict_types=1);

namespace App\Modules\Kanban\Controllers;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Core\Services\ProjectAccessService;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class KanbanController
{
    public function __construct(
        private readonly Connection $db,
        private readonly ProjectAccessService $projectAccess
    ) {}

    // Board methods
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();
        $projectId = $queryParams['project_id'] ?? null;

        // Check if user is restricted to projects only
        $isRestricted = $this->projectAccess->isUserRestricted($userId);
        $accessibleProjectIds = [];

        if ($isRestricted) {
            $accessibleProjectIds = $this->projectAccess->getUserAccessibleProjectIds($userId);
            if (empty($accessibleProjectIds)) {
                return JsonResponse::success(['items' => []]);
            }
        }

        // Build project filter
        $projectJoin = '';
        $projectParams = [];

        if ($projectId) {
            if ($isRestricted && !in_array($projectId, $accessibleProjectIds)) {
                return JsonResponse::success(['items' => []]);
            }
            $projectJoin = ' INNER JOIN project_links pl ON pl.linkable_id = kb.id AND pl.linkable_type = ? AND pl.project_id = ?';
            $projectParams = ['kanban_board', $projectId];
        } elseif ($isRestricted) {
            $placeholders = implode(',', array_fill(0, count($accessibleProjectIds), '?'));
            $projectJoin = " INNER JOIN project_links pl ON pl.linkable_id = kb.id AND pl.linkable_type = 'kanban_board' AND pl.project_id IN ({$placeholders})";
            $projectParams = $accessibleProjectIds;
        }

        if ($isRestricted) {
            $sql = 'SELECT DISTINCT kb.*,
                        (SELECT COUNT(*) FROM kanban_columns WHERE board_id = kb.id) as column_count,
                        (SELECT COUNT(*) FROM kanban_cards kc
                         JOIN kanban_columns col ON kc.column_id = col.id
                         WHERE col.board_id = kb.id) as card_count
                 FROM kanban_boards kb' . $projectJoin . '
                 WHERE kb.is_archived = FALSE
                 ORDER BY kb.updated_at DESC';
            $params = $projectParams;
        } else {
            $sql = 'SELECT kb.*,
                        (SELECT COUNT(*) FROM kanban_columns WHERE board_id = kb.id) as column_count,
                        (SELECT COUNT(*) FROM kanban_cards kc
                         JOIN kanban_columns col ON kc.column_id = col.id
                         WHERE col.board_id = kb.id) as card_count
                 FROM kanban_boards kb
                 LEFT JOIN kanban_board_shares kbs ON kb.id = kbs.board_id AND kbs.user_id = ?' . $projectJoin . '
                 WHERE (kb.user_id = ? OR kbs.user_id = ?) AND kb.is_archived = FALSE
                 ORDER BY kb.updated_at DESC';
            $params = array_merge([$userId], $projectParams, [$userId, $userId]);
        }

        $boards = $this->db->fetchAllAssociative($sql, $params);

        return JsonResponse::success(['items' => $boards]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['title'])) {
            throw new ValidationException('Title is required');
        }

        $boardId = Uuid::uuid4()->toString();

        $this->db->insert('kanban_boards', [
            'id' => $boardId,
            'user_id' => $userId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? '#6366f1',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Create default columns
        $defaultColumns = ['To Do', 'In Progress', 'Done'];
        $colors = ['#6B7280', '#3B82F6', '#10B981'];
        foreach ($defaultColumns as $index => $title) {
            $this->db->insert('kanban_columns', [
                'id' => Uuid::uuid4()->toString(),
                'board_id' => $boardId,
                'title' => $title,
                'color' => $colors[$index],
                'position' => $index,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $board = $this->db->fetchAssociative('SELECT * FROM kanban_boards WHERE id = ?', [$boardId]);

        return JsonResponse::created($board, 'Board created successfully');
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $boardId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $board = $this->getBoardForUser($boardId, $userId);

        // Get columns with cards
        $columns = $this->db->fetchAllAssociative(
            'SELECT * FROM kanban_columns WHERE board_id = ? ORDER BY position',
            [$boardId]
        );

        foreach ($columns as &$column) {
            $column['cards'] = $this->db->fetchAllAssociative(
                'SELECT kc.*, u.username as assignee_name, u.email as assignee_email
                 FROM kanban_cards kc
                 LEFT JOIN users u ON u.id = kc.assigned_to
                 WHERE kc.column_id = ?
                 ORDER BY kc.position',
                [$column['id']]
            );
            foreach ($column['cards'] as &$card) {
                $card['labels'] = $card['labels'] ? json_decode($card['labels'], true) : [];
                $card['attachments'] = $card['attachments'] ? json_decode($card['attachments'], true) : [];
                if ($card['assigned_to']) {
                    $card['assignee'] = [
                        'id' => $card['assigned_to'],
                        'username' => $card['assignee_name'],
                        'email' => $card['assignee_email'],
                    ];
                } else {
                    $card['assignee'] = null;
                }
                unset($card['assignee_name'], $card['assignee_email']);
            }
        }

        $board['columns'] = $columns;

        return JsonResponse::success($board);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $boardId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $this->getBoardForUser($boardId, $userId, true);

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];
        $allowedFields = ['title', 'description', 'color', 'is_archived'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        $this->db->update('kanban_boards', $updateData, ['id' => $boardId]);

        $board = $this->db->fetchAssociative('SELECT * FROM kanban_boards WHERE id = ?', [$boardId]);

        return JsonResponse::success($board, 'Board updated successfully');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $boardId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getBoardForUser($boardId, $userId, true);
        $this->db->delete('kanban_boards', ['id' => $boardId]);

        return JsonResponse::success(null, 'Board deleted successfully');
    }

    // Column methods
    public function createColumn(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $boardId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $this->getBoardForUser($boardId, $userId, true);

        if (empty($data['title'])) {
            throw new ValidationException('Title is required');
        }

        $maxPosition = (int) $this->db->fetchOne(
            'SELECT COALESCE(MAX(position), -1) FROM kanban_columns WHERE board_id = ?',
            [$boardId]
        );

        $columnId = Uuid::uuid4()->toString();

        $this->db->insert('kanban_columns', [
            'id' => $columnId,
            'board_id' => $boardId,
            'title' => $data['title'],
            'color' => $data['color'] ?? '#3B82F6',
            'position' => $maxPosition + 1,
            'wip_limit' => $data['wip_limit'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $column = $this->db->fetchAssociative('SELECT * FROM kanban_columns WHERE id = ?', [$columnId]);
        $column['cards'] = [];

        return JsonResponse::created($column, 'Column created successfully');
    }

    public function updateColumn(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $columnId = $route->getArgument('columnId');
        $data = $request->getParsedBody() ?? [];

        $this->getBoardForUser($boardId, $userId, true);

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];
        $allowedFields = ['title', 'color', 'wip_limit'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        $this->db->update('kanban_columns', $updateData, ['id' => $columnId, 'board_id' => $boardId]);

        $column = $this->db->fetchAssociative('SELECT * FROM kanban_columns WHERE id = ?', [$columnId]);

        return JsonResponse::success($column, 'Column updated successfully');
    }

    public function deleteColumn(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $columnId = $route->getArgument('columnId');

        $this->getBoardForUser($boardId, $userId, true);
        $this->db->delete('kanban_columns', ['id' => $columnId, 'board_id' => $boardId]);

        return JsonResponse::success(null, 'Column deleted successfully');
    }

    public function reorderColumns(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $boardId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $this->getBoardForUser($boardId, $userId, true);

        if (!isset($data['columns']) || !is_array($data['columns'])) {
            throw new ValidationException('Columns array is required');
        }

        foreach ($data['columns'] as $position => $columnId) {
            $this->db->update('kanban_columns', ['position' => $position], [
                'id' => $columnId,
                'board_id' => $boardId,
            ]);
        }

        return JsonResponse::success(null, 'Columns reordered successfully');
    }

    // Card methods
    public function createCard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $columnId = $route->getArgument('columnId');
        $data = $request->getParsedBody() ?? [];

        $this->getBoardForUser($boardId, $userId, true);

        if (empty($data['title'])) {
            throw new ValidationException('Title is required');
        }

        $maxPosition = (int) $this->db->fetchOne(
            'SELECT COALESCE(MAX(position), -1) FROM kanban_cards WHERE column_id = ?',
            [$columnId]
        );

        $cardId = Uuid::uuid4()->toString();

        $this->db->insert('kanban_cards', [
            'id' => $cardId,
            'column_id' => $columnId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? null,
            'position' => $maxPosition + 1,
            'priority' => $data['priority'] ?? 'medium',
            'due_date' => $data['due_date'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'labels' => isset($data['labels']) ? json_encode($data['labels']) : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Update board timestamp
        $this->db->update('kanban_boards', ['updated_at' => date('Y-m-d H:i:s')], ['id' => $boardId]);

        $card = $this->db->fetchAssociative('SELECT * FROM kanban_cards WHERE id = ?', [$cardId]);
        $card['labels'] = $card['labels'] ? json_decode($card['labels'], true) : [];

        return JsonResponse::created($card, 'Card created successfully');
    }

    public function updateCard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $cardId = $route->getArgument('cardId');
        $data = $request->getParsedBody() ?? [];

        $this->getBoardForUser($boardId, $userId, true);

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];
        $allowedFields = ['title', 'description', 'color', 'priority', 'due_date', 'assigned_to'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        if (isset($data['labels'])) {
            $updateData['labels'] = json_encode($data['labels']);
        }

        $this->db->update('kanban_cards', $updateData, ['id' => $cardId]);

        // Update board timestamp
        $this->db->update('kanban_boards', ['updated_at' => date('Y-m-d H:i:s')], ['id' => $boardId]);

        $card = $this->db->fetchAssociative('SELECT * FROM kanban_cards WHERE id = ?', [$cardId]);
        $card['labels'] = $card['labels'] ? json_decode($card['labels'], true) : [];

        return JsonResponse::success($card, 'Card updated successfully');
    }

    public function deleteCard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $cardId = $route->getArgument('cardId');

        $this->getBoardForUser($boardId, $userId, true);
        $this->db->delete('kanban_cards', ['id' => $cardId]);

        return JsonResponse::success(null, 'Card deleted successfully');
    }

    public function moveCard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $cardId = $route->getArgument('cardId');
        $data = $request->getParsedBody() ?? [];

        $this->getBoardForUser($boardId, $userId, true);

        if (!isset($data['column_id']) || !isset($data['position'])) {
            throw new ValidationException('Column ID and position are required');
        }

        // Verify column belongs to board
        $column = $this->db->fetchAssociative(
            'SELECT * FROM kanban_columns WHERE id = ? AND board_id = ?',
            [$data['column_id'], $boardId]
        );

        if (!$column) {
            throw new NotFoundException('Column not found');
        }

        $this->db->update('kanban_cards', [
            'column_id' => $data['column_id'],
            'position' => $data['position'],
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $cardId]);

        // Update board timestamp
        $this->db->update('kanban_boards', ['updated_at' => date('Y-m-d H:i:s')], ['id' => $boardId]);

        return JsonResponse::success(null, 'Card moved successfully');
    }

    public function getBoardUsers(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $boardId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $board = $this->getBoardForUser($boardId, $userId);

        // Get board owner
        $owner = $this->db->fetchAssociative(
            'SELECT id, username, email FROM users WHERE id = ?',
            [$board['user_id']]
        );

        // Get shared users
        $sharedUsers = $this->db->fetchAllAssociative(
            'SELECT u.id, u.username, u.email, kbs.permission
             FROM kanban_board_shares kbs
             JOIN users u ON u.id = kbs.user_id
             WHERE kbs.board_id = ?',
            [$boardId]
        );

        $users = [$owner];
        foreach ($sharedUsers as $user) {
            $users[] = $user;
        }

        return JsonResponse::success(['users' => $users]);
    }

    // Card Attachments
    public function uploadAttachment(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $cardId = $route->getArgument('cardId');

        $this->getBoardForUser($boardId, $userId, true);

        // Verify card exists and belongs to board
        $card = $this->db->fetchAssociative(
            'SELECT kc.* FROM kanban_cards kc
             JOIN kanban_columns col ON kc.column_id = col.id
             WHERE kc.id = ? AND col.board_id = ?',
            [$cardId, $boardId]
        );

        if (!$card) {
            throw new NotFoundException('Card not found');
        }

        $uploadedFiles = $request->getUploadedFiles();
        if (empty($uploadedFiles['file'])) {
            throw new ValidationException('No file uploaded');
        }

        $file = $uploadedFiles['file'];
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new ValidationException('File upload failed');
        }

        // Validate file type (images only)
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $mimeType = $file->getClientMediaType();
        if (!in_array($mimeType, $allowedTypes)) {
            throw new ValidationException('Only images are allowed (JPEG, PNG, GIF, WebP)');
        }

        // Max 5MB
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new ValidationException('File too large (max 5MB)');
        }

        // Generate unique filename
        $extension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
        $filename = Uuid::uuid4()->toString() . '.' . $extension;
        $uploadPath = __DIR__ . '/../../../../storage/uploads/kanban/';

        // Ensure directory exists
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $file->moveTo($uploadPath . $filename);

        // Create attachment record
        $attachment = [
            'id' => Uuid::uuid4()->toString(),
            'filename' => $filename,
            'original_name' => $file->getClientFilename(),
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'uploaded_at' => date('Y-m-d H:i:s'),
        ];

        // Get existing attachments and add new one
        $attachments = $card['attachments'] ? json_decode($card['attachments'], true) : [];
        $attachments[] = $attachment;

        $this->db->update('kanban_cards', [
            'attachments' => json_encode($attachments),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $cardId]);

        // Update board timestamp
        $this->db->update('kanban_boards', ['updated_at' => date('Y-m-d H:i:s')], ['id' => $boardId]);

        return JsonResponse::created($attachment, 'Attachment uploaded successfully');
    }

    public function deleteAttachment(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $cardId = $route->getArgument('cardId');
        $attachmentId = $route->getArgument('attachmentId');

        $this->getBoardForUser($boardId, $userId, true);

        // Verify card exists and belongs to board
        $card = $this->db->fetchAssociative(
            'SELECT kc.* FROM kanban_cards kc
             JOIN kanban_columns col ON kc.column_id = col.id
             WHERE kc.id = ? AND col.board_id = ?',
            [$cardId, $boardId]
        );

        if (!$card) {
            throw new NotFoundException('Card not found');
        }

        $attachments = $card['attachments'] ? json_decode($card['attachments'], true) : [];
        $attachmentIndex = null;
        $attachmentToDelete = null;

        foreach ($attachments as $index => $attachment) {
            if ($attachment['id'] === $attachmentId) {
                $attachmentIndex = $index;
                $attachmentToDelete = $attachment;
                break;
            }
        }

        if ($attachmentIndex === null) {
            throw new NotFoundException('Attachment not found');
        }

        // Delete file
        $filePath = __DIR__ . '/../../../../storage/uploads/kanban/' . $attachmentToDelete['filename'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Remove from array and save
        array_splice($attachments, $attachmentIndex, 1);

        $this->db->update('kanban_cards', [
            'attachments' => empty($attachments) ? null : json_encode($attachments),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $cardId]);

        return JsonResponse::success(null, 'Attachment deleted successfully');
    }

    public function serveAttachment(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $route = RouteContext::fromRequest($request)->getRoute();
        $filename = $route->getArgument('filename');

        // Sanitize filename
        $filename = basename($filename);
        $filePath = __DIR__ . '/../../../../storage/uploads/kanban/' . $filename;

        if (!file_exists($filePath)) {
            throw new NotFoundException('File not found');
        }

        // Get mime type
        $mimeType = mime_content_type($filePath);

        $response = $response
            ->withHeader('Content-Type', $mimeType)
            ->withHeader('Content-Length', (string) filesize($filePath))
            ->withHeader('Cache-Control', 'public, max-age=31536000');

        $response->getBody()->write(file_get_contents($filePath));

        return $response;
    }

    private function getBoardForUser(string $boardId, string $userId, bool $requireEditAccess = false): array
    {
        $board = $this->db->fetchAssociative(
            'SELECT * FROM kanban_boards WHERE id = ?',
            [$boardId]
        );

        if (!$board) {
            throw new NotFoundException('Board not found');
        }

        // Check if user is restricted to projects only
        if ($this->projectAccess->isUserRestricted($userId)) {
            if (!$this->projectAccess->canAccessItem($userId, 'kanban_board', $boardId)) {
                throw new ForbiddenException('Access denied - board not in your accessible projects');
            }
            return $board;
        }

        if ($board['user_id'] === $userId) {
            return $board;
        }

        $share = $this->db->fetchAssociative(
            'SELECT * FROM kanban_board_shares WHERE board_id = ? AND user_id = ?',
            [$boardId, $userId]
        );

        if (!$share) {
            throw new ForbiddenException('Access denied');
        }

        if ($requireEditAccess && $share['permission'] !== 'edit') {
            throw new ForbiddenException('Edit access required');
        }

        $board['shared_permission'] = $share['permission'];
        return $board;
    }
}
