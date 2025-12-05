<?php

declare(strict_types=1);

namespace App\Modules\Documents\Controllers;

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

class DocumentController
{
    public function __construct(
        private readonly Connection $db,
        private readonly ProjectAccessService $projectAccess
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;
        $includeShared = ($queryParams['include_shared'] ?? '1') === '1';
        $projectId = $queryParams['project_id'] ?? null;

        // Check if user is restricted to projects only
        $isRestricted = $this->projectAccess->isUserRestricted($userId);
        $accessibleProjectIds = [];

        if ($isRestricted) {
            $accessibleProjectIds = $this->projectAccess->getUserAccessibleProjectIds($userId);
            if (empty($accessibleProjectIds)) {
                // User has no project access - return empty results
                return JsonResponse::paginated([], 0, $page, $perPage);
            }
        }

        // Build project filter join and where clause
        $projectJoin = '';
        $projectParams = [];
        $projectTypes = [];

        if ($projectId) {
            // Specific project filter
            if ($isRestricted && !in_array($projectId, $accessibleProjectIds)) {
                // User can't access this project
                return JsonResponse::paginated([], 0, $page, $perPage);
            }
            $projectJoin = ' INNER JOIN project_links pl ON pl.linkable_id = d.id AND pl.linkable_type = ? AND pl.project_id = ?';
            $projectParams = ['document', $projectId];
            $projectTypes = [\PDO::PARAM_STR, \PDO::PARAM_STR];
        } elseif ($isRestricted) {
            // User is restricted - only show documents from accessible projects
            $placeholders = implode(',', array_fill(0, count($accessibleProjectIds), '?'));
            $projectJoin = " INNER JOIN project_links pl ON pl.linkable_id = d.id AND pl.linkable_type = 'document' AND pl.project_id IN ({$placeholders})";
            $projectParams = $accessibleProjectIds;
            $projectTypes = array_fill(0, count($accessibleProjectIds), \PDO::PARAM_STR);
        }

        // Get own documents and shared documents
        if ($includeShared && !$isRestricted) {
            $sql = 'SELECT d.id, d.user_id, d.folder_id, d.title, d.format, d.is_archived, d.created_at, d.updated_at,
                        u.username as owner_name, ds.permission as shared_permission,
                        CASE WHEN d.user_id = ? THEN 1 ELSE 0 END as is_owner
                 FROM documents d
                 LEFT JOIN document_shares ds ON d.id = ds.document_id AND ds.user_id = ?
                 LEFT JOIN users u ON d.user_id = u.id' . $projectJoin . '
                 WHERE (d.user_id = ? OR ds.user_id = ?) AND d.is_archived = FALSE
                 ORDER BY d.updated_at DESC
                 LIMIT ? OFFSET ?';

            $params = array_merge([$userId, $userId], $projectParams, [$userId, $userId, $perPage, $offset]);
            $types = array_merge([\PDO::PARAM_STR, \PDO::PARAM_STR], $projectTypes, [\PDO::PARAM_STR, \PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT]);

            $documents = $this->db->fetchAllAssociative($sql, $params, $types);

            $countSql = 'SELECT COUNT(DISTINCT d.id) FROM documents d
                 LEFT JOIN document_shares ds ON d.id = ds.document_id AND ds.user_id = ?' . $projectJoin . '
                 WHERE (d.user_id = ? OR ds.user_id = ?) AND d.is_archived = FALSE';
            $countParams = array_merge([$userId], $projectParams, [$userId, $userId]);

            $total = (int) $this->db->fetchOne($countSql, $countParams);
        } else {
            // For restricted users or when not including shared
            $sql = 'SELECT DISTINCT d.id, d.user_id, d.folder_id, d.title, d.format, d.is_archived, d.created_at, d.updated_at,
                        CASE WHEN d.user_id = ? THEN 1 ELSE 0 END as is_owner
                 FROM documents d' . $projectJoin . '
                 WHERE d.is_archived = FALSE
                 ORDER BY d.updated_at DESC
                 LIMIT ? OFFSET ?';

            $params = array_merge([$userId], $projectParams, [$perPage, $offset]);
            $types = array_merge([\PDO::PARAM_STR], $projectTypes, [\PDO::PARAM_INT, \PDO::PARAM_INT]);

            $documents = $this->db->fetchAllAssociative($sql, $params, $types);

            $countSql = 'SELECT COUNT(DISTINCT d.id) FROM documents d' . $projectJoin . ' WHERE d.is_archived = FALSE';
            $total = (int) $this->db->fetchOne($countSql, $projectParams, $projectTypes);
        }

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

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $docId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $doc = $this->getDocumentForUser($docId, $userId);

        return JsonResponse::success($doc);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $docId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
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

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $docId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getDocumentForUser($docId, $userId, true);

        $this->db->delete('documents', ['id' => $docId]);

        return JsonResponse::success(null, 'Document deleted successfully');
    }

    public function versions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $docId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

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

        // Check if user is restricted to projects only
        if ($this->projectAccess->isUserRestricted($userId)) {
            if (!$this->projectAccess->canAccessItem($userId, 'document', $docId)) {
                throw new ForbiddenException('Access denied - document not in your accessible projects');
            }
            // For restricted users, always return the doc if it's in their projects
            return $doc;
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

    // Sharing functionality
    public function getShares(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $docId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        // Only owner can view shares
        $doc = $this->db->fetchAssociative('SELECT * FROM documents WHERE id = ?', [$docId]);
        if (!$doc || $doc['user_id'] !== $userId) {
            throw new ForbiddenException('Only the owner can manage shares');
        }

        $shares = $this->db->fetchAllAssociative(
            'SELECT ds.*, u.username, u.email
             FROM document_shares ds
             JOIN users u ON ds.user_id = u.id
             WHERE ds.document_id = ?',
            [$docId]
        );

        return JsonResponse::success($shares);
    }

    public function addShare(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $docId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        // Only owner can add shares
        $doc = $this->db->fetchAssociative('SELECT * FROM documents WHERE id = ?', [$docId]);
        if (!$doc || $doc['user_id'] !== $userId) {
            throw new ForbiddenException('Only the owner can manage shares');
        }

        if (empty($data['user_id']) && empty($data['email'])) {
            throw new ValidationException('User ID or email is required');
        }

        // Find user by email if not provided by ID
        $shareUserId = $data['user_id'] ?? null;
        if (!$shareUserId && !empty($data['email'])) {
            $user = $this->db->fetchAssociative('SELECT id FROM users WHERE email = ?', [$data['email']]);
            if (!$user) {
                throw new NotFoundException('User not found with this email');
            }
            $shareUserId = $user['id'];
        }

        // Can't share with yourself
        if ($shareUserId === $userId) {
            throw new ValidationException('Cannot share with yourself');
        }

        // Check if already shared
        $existing = $this->db->fetchAssociative(
            'SELECT * FROM document_shares WHERE document_id = ? AND user_id = ?',
            [$docId, $shareUserId]
        );

        $permission = $data['permission'] ?? 'view';
        if (!in_array($permission, ['view', 'edit'])) {
            $permission = 'view';
        }

        if ($existing) {
            // Update permission
            $this->db->update('document_shares', ['permission' => $permission], [
                'document_id' => $docId,
                'user_id' => $shareUserId,
            ]);
        } else {
            $this->db->insert('document_shares', [
                'document_id' => $docId,
                'user_id' => $shareUserId,
                'permission' => $permission,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return JsonResponse::success(null, 'Document shared successfully');
    }

    public function removeShare(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $docId = $route->getArgument('id');
        $shareUserId = $route->getArgument('userId');

        // Only owner can remove shares
        $doc = $this->db->fetchAssociative('SELECT * FROM documents WHERE id = ?', [$docId]);
        if (!$doc || $doc['user_id'] !== $userId) {
            throw new ForbiddenException('Only the owner can manage shares');
        }

        $this->db->delete('document_shares', [
            'document_id' => $docId,
            'user_id' => $shareUserId,
        ]);

        return JsonResponse::success(null, 'Share removed successfully');
    }

    // ========================================================================
    // Public Sharing
    // ========================================================================

    public function enablePublicShare(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $docId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        // Only owner can manage public sharing
        $doc = $this->db->fetchAssociative('SELECT * FROM documents WHERE id = ?', [$docId]);
        if (!$doc || $doc['user_id'] !== $userId) {
            throw new ForbiddenException('Only the owner can manage public sharing');
        }

        // Generate unique token
        $token = bin2hex(random_bytes(16));

        $updateData = [
            'is_public' => 1,
            'public_token' => $token,
            'public_view_count' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Optional password protection
        if (!empty($data['password'])) {
            $updateData['public_password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            $updateData['public_password'] = null;
        }

        // Optional expiration
        if (!empty($data['expires_at'])) {
            $updateData['public_expires_at'] = $data['expires_at'];
        } else {
            $updateData['public_expires_at'] = null;
        }

        $this->db->update('documents', $updateData, ['id' => $docId]);

        return JsonResponse::success([
            'public_token' => $token,
            'public_url' => '/doc/' . $token,
        ], 'Public sharing enabled');
    }

    public function disablePublicShare(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $docId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        // Only owner can manage public sharing
        $doc = $this->db->fetchAssociative('SELECT * FROM documents WHERE id = ?', [$docId]);
        if (!$doc || $doc['user_id'] !== $userId) {
            throw new ForbiddenException('Only the owner can manage public sharing');
        }

        $this->db->update('documents', [
            'is_public' => 0,
            'public_token' => null,
            'public_password' => null,
            'public_expires_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $docId]);

        return JsonResponse::success(null, 'Public sharing disabled');
    }

    public function getPublicShareInfo(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $docId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        // Only owner can view share info
        $doc = $this->db->fetchAssociative('SELECT * FROM documents WHERE id = ?', [$docId]);
        if (!$doc || $doc['user_id'] !== $userId) {
            throw new ForbiddenException('Only the owner can view public share info');
        }

        return JsonResponse::success([
            'is_public' => (bool) $doc['is_public'],
            'public_token' => $doc['public_token'],
            'public_url' => $doc['public_token'] ? '/doc/' . $doc['public_token'] : null,
            'has_password' => !empty($doc['public_password']),
            'expires_at' => $doc['public_expires_at'],
            'view_count' => (int) $doc['public_view_count'],
        ]);
    }

    // Public view (no auth required)
    public function showPublic(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $token = RouteContext::fromRequest($request)->getRoute()->getArgument('token');
        $data = $request->getParsedBody() ?? [];

        $doc = $this->db->fetchAssociative(
            'SELECT d.*, u.username as owner_name
             FROM documents d
             LEFT JOIN users u ON d.user_id = u.id
             WHERE d.public_token = ? AND d.is_public = 1',
            [$token]
        );

        if (!$doc) {
            throw new NotFoundException('Document not found or not publicly shared');
        }

        // Check expiration
        if ($doc['public_expires_at'] && strtotime($doc['public_expires_at']) < time()) {
            throw new ForbiddenException('This shared link has expired');
        }

        // Check password if set
        if (!empty($doc['public_password'])) {
            $providedPassword = $data['password'] ?? $request->getQueryParams()['password'] ?? null;
            if (!$providedPassword) {
                return JsonResponse::error('Password required', 401, ['requires_password' => true]);
            }
            if (!password_verify($providedPassword, $doc['public_password'])) {
                return JsonResponse::error('Invalid password', 401, ['requires_password' => true]);
            }
        }

        // Increment view count
        $this->db->executeStatement(
            'UPDATE documents SET public_view_count = public_view_count + 1 WHERE id = ?',
            [$doc['id']]
        );

        // Remove sensitive data
        unset($doc['user_id'], $doc['public_password'], $doc['folder_id']);

        return JsonResponse::success($doc);
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
