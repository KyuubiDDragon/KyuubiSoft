<?php

declare(strict_types=1);

namespace App\Modules\QuickNotes\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class QuickNoteController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $notes = $this->db->fetchAllAssociative(
            'SELECT * FROM quick_notes
             WHERE user_id = ?
             ORDER BY is_pinned DESC, position ASC, updated_at DESC',
            [$userId]
        );

        return JsonResponse::success($notes);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        $noteId = Uuid::uuid4()->toString();

        // Get next position
        $maxPosition = (int) $this->db->fetchOne(
            'SELECT COALESCE(MAX(position), 0) FROM quick_notes WHERE user_id = ?',
            [$userId]
        );

        $this->db->insert('quick_notes', [
            'id' => $noteId,
            'user_id' => $userId,
            'content' => $data['content'] ?? '',
            'is_pinned' => !empty($data['is_pinned']) ? 1 : 0,
            'color' => $data['color'] ?? 'default',
            'position' => $maxPosition + 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $note = $this->db->fetchAssociative('SELECT * FROM quick_notes WHERE id = ?', [$noteId]);

        return JsonResponse::created($note, 'Note created successfully');
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $note = $this->getNoteForUser($noteId, $userId);

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        if (array_key_exists('content', $data)) {
            $updateData['content'] = $data['content'];
        }
        if (array_key_exists('is_pinned', $data)) {
            $updateData['is_pinned'] = $data['is_pinned'] ? 1 : 0;
        }
        if (array_key_exists('color', $data)) {
            $updateData['color'] = $data['color'];
        }
        if (array_key_exists('position', $data)) {
            $updateData['position'] = (int) $data['position'];
        }

        $this->db->update('quick_notes', $updateData, ['id' => $noteId]);

        $note = $this->db->fetchAssociative('SELECT * FROM quick_notes WHERE id = ?', [$noteId]);

        return JsonResponse::success($note, 'Note updated successfully');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getNoteForUser($noteId, $userId);
        $this->db->delete('quick_notes', ['id' => $noteId]);

        return JsonResponse::success(null, 'Note deleted successfully');
    }

    public function reorder(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['order']) || !is_array($data['order'])) {
            throw new ValidationException('Order array is required');
        }

        foreach ($data['order'] as $position => $noteId) {
            $this->db->update('quick_notes', [
                'position' => $position,
            ], [
                'id' => $noteId,
                'user_id' => $userId,
            ]);
        }

        return JsonResponse::success(null, 'Notes reordered successfully');
    }

    private function getNoteForUser(string $noteId, string $userId): array
    {
        $note = $this->db->fetchAssociative(
            'SELECT * FROM quick_notes WHERE id = ? AND user_id = ?',
            [$noteId, $userId]
        );

        if (!$note) {
            throw new NotFoundException('Note not found');
        }

        return $note;
    }
}
