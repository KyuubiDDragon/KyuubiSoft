<?php

declare(strict_types=1);

namespace App\Modules\Notes\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

/**
 * Controller for public note access (shared pages)
 */
class PublicNoteController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * Get public note by token
     * No authentication required
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $token = RouteContext::fromRequest($request)->getRoute()->getArgument('token');

        if (!$token || strlen($token) < 20) {
            throw new NotFoundException('Page not found');
        }

        $note = $this->db->fetchAssociative(
            "SELECT n.id, n.title, n.slug, n.content, n.icon, n.cover_image, n.word_count,
                    n.created_at, n.updated_at, n.public_token, n.public_settings,
                    u.username as author_name
             FROM notes n
             LEFT JOIN users u ON n.user_id = u.id
             WHERE n.public_token = ? AND n.is_deleted = FALSE AND n.is_archived = FALSE",
            [$token]
        );

        if (!$note) {
            throw new NotFoundException('Page not found');
        }

        // Parse public settings
        $settings = $note['public_settings'] ? json_decode($note['public_settings'], true) : [];

        // Check if sharing is enabled
        if (empty($settings['enabled'])) {
            throw new NotFoundException('Page not found');
        }

        // Check expiration
        if (!empty($settings['expires_at'])) {
            if (strtotime($settings['expires_at']) < time()) {
                throw new NotFoundException('This shared page has expired');
            }
        }

        // Track view count
        $this->db->executeStatement(
            "UPDATE notes SET public_views = COALESCE(public_views, 0) + 1 WHERE id = ?",
            [$note['id']]
        );

        // Build response
        $publicNote = [
            'title' => $note['title'],
            'content' => $note['content'],
            'icon' => $note['icon'],
            'cover_image' => $note['cover_image'],
            'word_count' => $note['word_count'],
            'updated_at' => $note['updated_at'],
            'created_at' => $note['created_at'],
        ];

        // Include author if setting allows
        if (!empty($settings['show_author'])) {
            $publicNote['author'] = $note['author_name'];
        }

        // Include edit date if setting allows
        if (empty($settings['hide_date'])) {
            $publicNote['show_date'] = true;
        }

        return JsonResponse::success($publicNote);
    }

    /**
     * Generate or get share link for a note
     * Requires authentication
     */
    public function share(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $note = $this->getNoteForUser($noteId, $userId);

        $data = $request->getParsedBody() ?? [];

        // Generate token if not exists
        $token = $note['public_token'];
        if (!$token) {
            $token = $this->generateSecureToken();
        }

        // Build settings
        $settings = [
            'enabled' => !empty($data['enabled']),
            'show_author' => !empty($data['show_author']),
            'hide_date' => !empty($data['hide_date']),
            'allow_comments' => !empty($data['allow_comments']),
            'password' => $data['password'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ];

        // Update note
        $this->db->update('notes', [
            'public_token' => $token,
            'public_settings' => json_encode($settings),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $noteId]);

        // Build public URL
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        $publicUrl = "{$baseUrl}/public/note/{$token}";

        return JsonResponse::success([
            'token' => $token,
            'url' => $publicUrl,
            'settings' => $settings,
        ], 'Share settings updated');
    }

    /**
     * Get current share status for a note
     */
    public function status(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $note = $this->getNoteForUser($noteId, $userId);

        $settings = $note['public_settings'] ? json_decode($note['public_settings'], true) : [];
        $isShared = !empty($note['public_token']) && !empty($settings['enabled']);

        $result = [
            'is_shared' => $isShared,
            'token' => $note['public_token'],
            'settings' => $settings,
            'views' => (int) ($note['public_views'] ?? 0),
        ];

        if ($isShared && $note['public_token']) {
            $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost';
            $result['url'] = "{$baseUrl}/public/note/{$note['public_token']}";
        }

        return JsonResponse::success($result);
    }

    /**
     * Disable sharing for a note
     */
    public function unshare(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $note = $this->getNoteForUser($noteId, $userId);

        // Keep token but disable sharing
        $settings = $note['public_settings'] ? json_decode($note['public_settings'], true) : [];
        $settings['enabled'] = false;

        $this->db->update('notes', [
            'public_settings' => json_encode($settings),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $noteId]);

        return JsonResponse::success(null, 'Sharing disabled');
    }

    /**
     * Regenerate share token
     */
    public function regenerateToken(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $noteId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $note = $this->getNoteForUser($noteId, $userId);

        $newToken = $this->generateSecureToken();

        $this->db->update('notes', [
            'public_token' => $newToken,
            'public_views' => 0, // Reset view count
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $noteId]);

        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost';

        return JsonResponse::success([
            'token' => $newToken,
            'url' => "{$baseUrl}/public/note/{$newToken}",
        ], 'New share link generated');
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function getNoteForUser(string $noteId, string $userId): array
    {
        $note = $this->db->fetchAssociative(
            'SELECT * FROM notes WHERE id = ? AND user_id = ? AND is_deleted = FALSE',
            [$noteId, $userId]
        );

        if (!$note) {
            throw new NotFoundException('Note not found');
        }

        return $note;
    }

    private function generateSecureToken(): string
    {
        return bin2hex(random_bytes(16)); // 32 character hex string
    }
}
