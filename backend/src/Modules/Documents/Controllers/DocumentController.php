<?php

declare(strict_types=1);

namespace App\Modules\Documents\Controllers;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;

class DocumentController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $documents = $this->db->fetchAllAssociative(
            'SELECT id, user_id, folder_id, title, format, is_archived, created_at, updated_at
             FROM documents
             WHERE user_id = ? AND is_archived = FALSE
             ORDER BY updated_at DESC
             LIMIT ? OFFSET ?',
            [$userId, $perPage, $offset],
            [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT]
        );

        $total = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM documents WHERE user_id = ? AND is_archived = FALSE',
            [$userId]
        );

        return JsonResponse::paginated($documents, $total, $page, $perPage);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['title'])) {
            throw new ValidationException('Title is required');
        }

        $docId = Uuid::uuid4()->toString();

        $this->db->insert('documents', [
            'id' => $docId,
            'user_id' => $userId,
            'folder_id' => $data['folder_id'] ?? null,
            'title' => $data['title'],
            'content' => $data['content'] ?? '',
            'format' => $data['format'] ?? 'markdown',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Create initial version
        $this->createVersion($docId, $data['content'] ?? '', $userId, 'Initial version');

        $doc = $this->db->fetchAssociative('SELECT * FROM documents WHERE id = ?', [$docId]);

        return JsonResponse::created($doc, 'Document created successfully');
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $docId = $args['id'];

        $doc = $this->getDocumentForUser($docId, $userId);

        return JsonResponse::success($doc);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $docId = $args['id'];
        $data = $request->getParsedBody() ?? [];

        $doc = $this->getDocumentForUser($docId, $userId, true);

        $updateData = [];
        $allowedFields = ['title', 'content', 'format', 'folder_id', 'is_archived'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        // Create version if content changed
        if (isset($data['content']) && $data['content'] !== $doc['content']) {
            $this->createVersion($docId, $data['content'], $userId, $data['change_summary'] ?? null);
        }

        if (!empty($updateData)) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $this->db->update('documents', $updateData, ['id' => $docId]);
        }

        $updated = $this->db->fetchAssociative('SELECT * FROM documents WHERE id = ?', [$docId]);

        return JsonResponse::success($updated, 'Document updated successfully');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $docId = $args['id'];

        $this->getDocumentForUser($docId, $userId, true);

        $this->db->delete('documents', ['id' => $docId]);

        return JsonResponse::success(null, 'Document deleted successfully');
    }

    public function versions(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $docId = $args['id'];

        $this->getDocumentForUser($docId, $userId);

        $versions = $this->db->fetchAllAssociative(
            'SELECT id, version_number, change_summary, created_by, created_at
             FROM document_versions
             WHERE document_id = ?
             ORDER BY version_number DESC',
            [$docId]
        );

        return JsonResponse::success($versions);
    }

    private function getDocumentForUser(string $docId, string $userId, bool $requireOwner = false): array
    {
        $doc = $this->db->fetchAssociative(
            'SELECT * FROM documents WHERE id = ?',
            [$docId]
        );

        if (!$doc) {
            throw new NotFoundException('Document not found');
        }

        if ($doc['user_id'] === $userId) {
            return $doc;
        }

        if (!$requireOwner) {
            $share = $this->db->fetchAssociative(
                'SELECT * FROM document_shares WHERE document_id = ? AND user_id = ?',
                [$docId, $userId]
            );

            if ($share) {
                $doc['shared_permission'] = $share['permission'];
                return $doc;
            }
        }

        throw new ForbiddenException('Access denied');
    }

    private function createVersion(string $docId, string $content, string $userId, ?string $summary = null): void
    {
        $lastVersion = (int) $this->db->fetchOne(
            'SELECT COALESCE(MAX(version_number), 0) FROM document_versions WHERE document_id = ?',
            [$docId]
        );

        $this->db->insert('document_versions', [
            'id' => Uuid::uuid4()->toString(),
            'document_id' => $docId,
            'content' => $content,
            'version_number' => $lastVersion + 1,
            'change_summary' => $summary,
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
