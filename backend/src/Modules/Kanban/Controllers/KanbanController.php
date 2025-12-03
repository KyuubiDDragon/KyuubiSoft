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

                // Get tags for this card
                $card['tags'] = $this->db->fetchAllAssociative(
                    'SELECT t.* FROM kanban_tags t
                     JOIN kanban_card_tags ct ON t.id = ct.tag_id
                     WHERE ct.card_id = ?
                     ORDER BY t.name',
                    [$card['id']]
                );

                // Get links for this card
                $links = $this->db->fetchAllAssociative(
                    'SELECT * FROM kanban_card_links WHERE card_id = ? ORDER BY created_at DESC',
                    [$card['id']]
                );
                foreach ($links as &$link) {
                    $link['linkable'] = $this->getLinkableData($link['linkable_type'], $link['linkable_id']);
                }
                $card['links'] = $links;

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

        // Get all board tags
        $board['tags'] = $this->db->fetchAllAssociative(
            'SELECT * FROM kanban_tags WHERE board_id = ? ORDER BY name',
            [$boardId]
        );

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

        // Update each column's position using raw SQL for reliability
        foreach ($data['columns'] as $position => $columnId) {
            $this->db->executeStatement(
                'UPDATE kanban_columns SET position = ?, updated_at = NOW() WHERE id = ? AND board_id = ?',
                [(int) $position, $columnId, $boardId]
            );
        }

        // Return the updated order for verification
        $updatedColumns = $this->db->fetchAllAssociative(
            'SELECT id, title, position FROM kanban_columns WHERE board_id = ? ORDER BY position',
            [$boardId]
        );

        return JsonResponse::success(['columns' => $updatedColumns], 'Columns reordered successfully');
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

        // Add tags if provided
        if (!empty($data['tags'])) {
            foreach ($data['tags'] as $tag) {
                $tagId = is_array($tag) ? ($tag['id'] ?? null) : $tag;
                if ($tagId) {
                    // Verify tag belongs to this board
                    $existingTag = $this->db->fetchOne(
                        'SELECT id FROM kanban_tags WHERE id = ? AND board_id = ?',
                        [$tagId, $boardId]
                    );
                    if ($existingTag) {
                        $this->db->insert('kanban_card_tags', [
                            'card_id' => $cardId,
                            'tag_id' => $tagId,
                        ]);
                    }
                }
            }
        }

        $card = $this->db->fetchAssociative('SELECT * FROM kanban_cards WHERE id = ?', [$cardId]);
        $card['labels'] = $card['labels'] ? json_decode($card['labels'], true) : [];

        // Get tags for response
        $card['tags'] = $this->db->fetchAllAssociative(
            'SELECT t.* FROM kanban_tags t
             JOIN kanban_card_tags ct ON t.id = ct.tag_id
             WHERE ct.card_id = ?
             ORDER BY t.name',
            [$cardId]
        );

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

    // ==================
    // Tag Methods
    // ==================

    public function getTags(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $boardId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getBoardForUser($boardId, $userId);

        $tags = $this->db->fetchAllAssociative(
            'SELECT * FROM kanban_tags WHERE board_id = ? ORDER BY name',
            [$boardId]
        );

        return JsonResponse::success(['items' => $tags]);
    }

    public function createTag(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $boardId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $this->getBoardForUser($boardId, $userId, true);

        if (empty($data['name'])) {
            throw new ValidationException('Tag name is required');
        }

        $tagId = Uuid::uuid4()->toString();

        $this->db->insert('kanban_tags', [
            'id' => $tagId,
            'board_id' => $boardId,
            'name' => trim($data['name']),
            'color' => $data['color'] ?? '#6B7280',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $tag = $this->db->fetchAssociative('SELECT * FROM kanban_tags WHERE id = ?', [$tagId]);

        return JsonResponse::created($tag, 'Tag created successfully');
    }

    public function updateTag(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $tagId = $route->getArgument('tagId');
        $data = $request->getParsedBody() ?? [];

        $this->getBoardForUser($boardId, $userId, true);

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = trim($data['name']);
        }
        if (isset($data['color'])) {
            $updateData['color'] = $data['color'];
        }

        if (!empty($updateData)) {
            $this->db->update('kanban_tags', $updateData, ['id' => $tagId, 'board_id' => $boardId]);
        }

        $tag = $this->db->fetchAssociative('SELECT * FROM kanban_tags WHERE id = ?', [$tagId]);

        return JsonResponse::success($tag, 'Tag updated successfully');
    }

    public function deleteTag(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $tagId = $route->getArgument('tagId');

        $this->getBoardForUser($boardId, $userId, true);

        $this->db->delete('kanban_tags', ['id' => $tagId, 'board_id' => $boardId]);

        return JsonResponse::success(null, 'Tag deleted successfully');
    }

    public function addCardTag(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $cardId = $route->getArgument('cardId');
        $tagId = $route->getArgument('tagId');

        $this->getBoardForUser($boardId, $userId, true);

        // Verify card belongs to board
        $card = $this->db->fetchAssociative(
            'SELECT kc.* FROM kanban_cards kc
             JOIN kanban_columns col ON kc.column_id = col.id
             WHERE kc.id = ? AND col.board_id = ?',
            [$cardId, $boardId]
        );

        if (!$card) {
            throw new NotFoundException('Card not found');
        }

        // Verify tag belongs to board
        $tag = $this->db->fetchAssociative(
            'SELECT * FROM kanban_tags WHERE id = ? AND board_id = ?',
            [$tagId, $boardId]
        );

        if (!$tag) {
            throw new NotFoundException('Tag not found');
        }

        // Add tag (ignore if already exists)
        try {
            $this->db->insert('kanban_card_tags', [
                'card_id' => $cardId,
                'tag_id' => $tagId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            // Tag already assigned, ignore
        }

        return JsonResponse::success(null, 'Tag added to card');
    }

    public function removeCardTag(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $cardId = $route->getArgument('cardId');
        $tagId = $route->getArgument('tagId');

        $this->getBoardForUser($boardId, $userId, true);

        $this->db->delete('kanban_card_tags', ['card_id' => $cardId, 'tag_id' => $tagId]);

        return JsonResponse::success(null, 'Tag removed from card');
    }

    // ==================
    // Link Methods
    // ==================

    public function getCardLinks(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $cardId = $route->getArgument('cardId');

        $this->getBoardForUser($boardId, $userId);

        $links = $this->db->fetchAllAssociative(
            'SELECT * FROM kanban_card_links WHERE card_id = ? ORDER BY created_at DESC',
            [$cardId]
        );

        // Enrich with linkable data
        foreach ($links as &$link) {
            $link['linkable'] = $this->getLinkableData($link['linkable_type'], $link['linkable_id']);
        }

        return JsonResponse::success(['items' => $links]);
    }

    public function addCardLink(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $cardId = $route->getArgument('cardId');
        $data = $request->getParsedBody() ?? [];

        $this->getBoardForUser($boardId, $userId, true);

        if (empty($data['linkable_type']) || empty($data['linkable_id'])) {
            throw new ValidationException('Linkable type and ID are required');
        }

        $allowedTypes = ['document', 'list', 'snippet', 'bookmark'];
        if (!in_array($data['linkable_type'], $allowedTypes)) {
            throw new ValidationException('Invalid linkable type');
        }

        // Verify linkable exists
        $linkable = $this->getLinkableData($data['linkable_type'], $data['linkable_id']);
        if (!$linkable) {
            throw new NotFoundException('Linked item not found');
        }

        $linkId = Uuid::uuid4()->toString();

        try {
            $this->db->insert('kanban_card_links', [
                'id' => $linkId,
                'card_id' => $cardId,
                'linkable_type' => $data['linkable_type'],
                'linkable_id' => $data['linkable_id'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            throw new ValidationException('Link already exists');
        }

        $link = $this->db->fetchAssociative('SELECT * FROM kanban_card_links WHERE id = ?', [$linkId]);
        $link['linkable'] = $linkable;

        return JsonResponse::created($link, 'Link added successfully');
    }

    public function removeCardLink(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $cardId = $route->getArgument('cardId');
        $linkId = $route->getArgument('linkId');

        $this->getBoardForUser($boardId, $userId, true);

        $this->db->delete('kanban_card_links', ['id' => $linkId, 'card_id' => $cardId]);

        return JsonResponse::success(null, 'Link removed successfully');
    }

    public function getLinkableItems(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $type = $route->getArgument('type');
        $queryParams = $request->getQueryParams();
        $search = $queryParams['search'] ?? '';

        $this->getBoardForUser($boardId, $userId);

        // Check if board is linked to a project
        $projectId = $this->db->fetchOne(
            'SELECT project_id FROM project_links WHERE linkable_type = ? AND linkable_id = ?',
            ['kanban_board', $boardId]
        );

        $items = [];
        $typeMapping = [
            'document' => ['table' => 'documents', 'fields' => 't.id, t.title, t.updated_at', 'archived' => 'is_archived = 0'],
            'list' => ['table' => 'lists', 'fields' => 't.id, t.title, t.updated_at', 'archived' => 'is_archived = 0'],
            'snippet' => ['table' => 'snippets', 'fields' => 't.id, t.title, t.language, t.updated_at', 'archived' => null],
            'bookmark' => ['table' => 'bookmarks', 'fields' => 't.id, t.title, t.url, t.updated_at', 'archived' => null],
        ];

        if (!isset($typeMapping[$type])) {
            return JsonResponse::success(['items' => []]);
        }

        $config = $typeMapping[$type];
        $archivedCondition = $config['archived'] ? " AND t.{$config['archived']}" : '';

        if ($projectId) {
            // Filter by project - only show items linked to the same project
            $sql = "SELECT {$config['fields']} FROM {$config['table']} t
                    INNER JOIN project_links pl ON pl.linkable_id = t.id AND pl.linkable_type = ?
                    WHERE pl.project_id = ?{$archivedCondition}";
            $params = [$type, $projectId];

            if ($search) {
                $sql .= ' AND t.title LIKE ?';
                $params[] = "%{$search}%";
            }
            $sql .= ' ORDER BY t.updated_at DESC LIMIT 50';
            $items = $this->db->fetchAllAssociative($sql, $params);
        } else {
            // No project filter - show all user's items (use same alias for consistency)
            $archivedConditionWithAlias = $config['archived'] ? " AND t.{$config['archived']}" : '';
            $sql = "SELECT {$config['fields']} FROM {$config['table']} t WHERE t.user_id = ?{$archivedConditionWithAlias}";
            $params = [$userId];

            if ($search) {
                $sql .= ' AND t.title LIKE ?';
                $params[] = "%{$search}%";
            }
            $sql .= ' ORDER BY t.updated_at DESC LIMIT 50';
            $items = $this->db->fetchAllAssociative($sql, $params);
        }

        return JsonResponse::success(['items' => $items, 'projectFiltered' => (bool) $projectId]);
    }

    private function getLinkableData(string $type, string $id): ?array
    {
        return match ($type) {
            'document' => $this->db->fetchAssociative(
                'SELECT id, title, updated_at FROM documents WHERE id = ?',
                [$id]
            ) ?: null,
            'list' => $this->db->fetchAssociative(
                'SELECT id, title, updated_at FROM lists WHERE id = ?',
                [$id]
            ) ?: null,
            'snippet' => $this->db->fetchAssociative(
                'SELECT id, title, language, updated_at FROM snippets WHERE id = ?',
                [$id]
            ) ?: null,
            'bookmark' => $this->db->fetchAssociative(
                'SELECT id, title, url, updated_at FROM bookmarks WHERE id = ?',
                [$id]
            ) ?: null,
            default => null,
        };
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

    // ==================
    // Checklist Methods
    // ==================

    public function getChecklists(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $cardId = $route->getArgument('cardId');

        $this->getBoardForUser($boardId, $userId);

        $checklists = $this->db->fetchAllAssociative(
            'SELECT * FROM kanban_card_checklists WHERE card_id = ? ORDER BY position',
            [$cardId]
        );

        foreach ($checklists as &$checklist) {
            $checklist['items'] = $this->db->fetchAllAssociative(
                'SELECT ci.*, u.username as completed_by_name
                 FROM kanban_checklist_items ci
                 LEFT JOIN users u ON u.id = ci.completed_by
                 WHERE ci.checklist_id = ?
                 ORDER BY ci.position',
                [$checklist['id']]
            );
        }

        return JsonResponse::success(['checklists' => $checklists]);
    }

    public function createChecklist(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $cardId = $route->getArgument('cardId');
        $data = $request->getParsedBody() ?? [];

        $this->getBoardForUser($boardId, $userId, true);

        if (empty($data['title'])) {
            throw new ValidationException('Title is required');
        }

        $maxPosition = (int) $this->db->fetchOne(
            'SELECT COALESCE(MAX(position), -1) FROM kanban_card_checklists WHERE card_id = ?',
            [$cardId]
        );

        $checklistId = Uuid::uuid4()->toString();
        $this->db->insert('kanban_card_checklists', [
            'id' => $checklistId,
            'card_id' => $cardId,
            'title' => $data['title'],
            'position' => $maxPosition + 1,
        ]);

        $checklist = $this->db->fetchAssociative(
            'SELECT * FROM kanban_card_checklists WHERE id = ?',
            [$checklistId]
        );
        $checklist['items'] = [];

        return JsonResponse::created($checklist, 'Checklist created');
    }

    public function updateChecklist(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $checklistId = $route->getArgument('checklistId');
        $data = $request->getParsedBody() ?? [];

        $this->getBoardForUser($boardId, $userId, true);

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];
        if (isset($data['title'])) {
            $updateData['title'] = $data['title'];
        }

        $this->db->update('kanban_card_checklists', $updateData, ['id' => $checklistId]);

        return JsonResponse::success(null, 'Checklist updated');
    }

    public function deleteChecklist(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $checklistId = $route->getArgument('checklistId');

        $this->getBoardForUser($boardId, $userId, true);

        $this->db->delete('kanban_card_checklists', ['id' => $checklistId]);

        return JsonResponse::success(null, 'Checklist deleted');
    }

    public function addChecklistItem(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $checklistId = $route->getArgument('checklistId');
        $data = $request->getParsedBody() ?? [];

        $this->getBoardForUser($boardId, $userId, true);

        if (empty($data['content'])) {
            throw new ValidationException('Content is required');
        }

        $maxPosition = (int) $this->db->fetchOne(
            'SELECT COALESCE(MAX(position), -1) FROM kanban_checklist_items WHERE checklist_id = ?',
            [$checklistId]
        );

        $itemId = Uuid::uuid4()->toString();
        $this->db->insert('kanban_checklist_items', [
            'id' => $itemId,
            'checklist_id' => $checklistId,
            'content' => $data['content'],
            'position' => $maxPosition + 1,
        ]);

        $item = $this->db->fetchAssociative(
            'SELECT * FROM kanban_checklist_items WHERE id = ?',
            [$itemId]
        );

        return JsonResponse::created($item, 'Item added');
    }

    public function toggleChecklistItem(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $itemId = $route->getArgument('itemId');

        $this->getBoardForUser($boardId, $userId, true);

        $item = $this->db->fetchAssociative(
            'SELECT * FROM kanban_checklist_items WHERE id = ?',
            [$itemId]
        );

        if (!$item) {
            throw new NotFoundException('Item not found');
        }

        $isCompleted = !$item['is_completed'];
        $this->db->update('kanban_checklist_items', [
            'is_completed' => $isCompleted,
            'completed_at' => $isCompleted ? date('Y-m-d H:i:s') : null,
            'completed_by' => $isCompleted ? $userId : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $itemId]);

        $item = $this->db->fetchAssociative(
            'SELECT ci.*, u.username as completed_by_name
             FROM kanban_checklist_items ci
             LEFT JOIN users u ON u.id = ci.completed_by
             WHERE ci.id = ?',
            [$itemId]
        );

        return JsonResponse::success($item, 'Item toggled');
    }

    public function updateChecklistItem(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $itemId = $route->getArgument('itemId');
        $data = $request->getParsedBody() ?? [];

        $this->getBoardForUser($boardId, $userId, true);

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];
        if (isset($data['content'])) {
            $updateData['content'] = $data['content'];
        }

        $this->db->update('kanban_checklist_items', $updateData, ['id' => $itemId]);

        return JsonResponse::success(null, 'Item updated');
    }

    public function deleteChecklistItem(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $itemId = $route->getArgument('itemId');

        $this->getBoardForUser($boardId, $userId, true);

        $this->db->delete('kanban_checklist_items', ['id' => $itemId]);

        return JsonResponse::success(null, 'Item deleted');
    }

    // ==================
    // Comment Methods
    // ==================

    public function getComments(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $cardId = $route->getArgument('cardId');

        $this->getBoardForUser($boardId, $userId);

        $comments = $this->db->fetchAllAssociative(
            'SELECT c.*, u.username, u.email
             FROM kanban_card_comments c
             JOIN users u ON u.id = c.user_id
             WHERE c.card_id = ?
             ORDER BY c.created_at DESC',
            [$cardId]
        );

        return JsonResponse::success(['comments' => $comments]);
    }

    public function addComment(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $cardId = $route->getArgument('cardId');
        $data = $request->getParsedBody() ?? [];

        $this->getBoardForUser($boardId, $userId);

        if (empty($data['content'])) {
            throw new ValidationException('Content is required');
        }

        $commentId = Uuid::uuid4()->toString();
        $this->db->insert('kanban_card_comments', [
            'id' => $commentId,
            'card_id' => $cardId,
            'user_id' => $userId,
            'content' => $data['content'],
        ]);

        $comment = $this->db->fetchAssociative(
            'SELECT c.*, u.username, u.email
             FROM kanban_card_comments c
             JOIN users u ON u.id = c.user_id
             WHERE c.id = ?',
            [$commentId]
        );

        return JsonResponse::created($comment, 'Comment added');
    }

    public function updateComment(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $commentId = $route->getArgument('commentId');
        $data = $request->getParsedBody() ?? [];

        $this->getBoardForUser($boardId, $userId);

        // Only author can edit their comment
        $comment = $this->db->fetchAssociative(
            'SELECT * FROM kanban_card_comments WHERE id = ?',
            [$commentId]
        );

        if (!$comment || $comment['user_id'] !== $userId) {
            throw new ForbiddenException('Cannot edit this comment');
        }

        if (empty($data['content'])) {
            throw new ValidationException('Content is required');
        }

        $this->db->update('kanban_card_comments', [
            'content' => $data['content'],
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $commentId]);

        return JsonResponse::success(null, 'Comment updated');
    }

    public function deleteComment(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $boardId = $route->getArgument('id');
        $commentId = $route->getArgument('commentId');

        $this->getBoardForUser($boardId, $userId);

        // Only author can delete their comment
        $comment = $this->db->fetchAssociative(
            'SELECT * FROM kanban_card_comments WHERE id = ?',
            [$commentId]
        );

        if (!$comment || $comment['user_id'] !== $userId) {
            throw new ForbiddenException('Cannot delete this comment');
        }

        $this->db->delete('kanban_card_comments', ['id' => $commentId]);

        return JsonResponse::success(null, 'Comment deleted');
    }
}
