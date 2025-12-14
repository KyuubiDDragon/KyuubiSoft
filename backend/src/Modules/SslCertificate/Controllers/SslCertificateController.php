<?php

declare(strict_types=1);

namespace App\Modules\SslCertificate\Controllers;

use App\Core\Http\JsonResponse;
use App\Modules\SslCertificate\Services\SslCheckerService;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class SslCertificateController
{
    public function __construct(
        private readonly Connection $db,
        private readonly SslCheckerService $sslChecker
    ) {}

    private function getRouteArg(ServerRequestInterface $request, string $name): ?string
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        return $route ? $route->getArgument($name) : null;
    }

    /**
     * List all SSL certificates
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $sql = 'SELECT c.*, f.name as folder_name, f.color as folder_color, p.name as project_name
                FROM ssl_certificates c
                LEFT JOIN ssl_certificate_folders f ON c.folder_id = f.id
                LEFT JOIN projects p ON c.project_id = p.id
                WHERE c.user_id = ?';
        $sqlParams = [$userId];

        if (!empty($params['project_id'])) {
            $sql .= ' AND c.project_id = ?';
            $sqlParams[] = $params['project_id'];
        }

        if (!empty($params['status'])) {
            $sql .= ' AND c.current_status = ?';
            $sqlParams[] = $params['status'];
        }

        if (!empty($params['folder_id'])) {
            $sql .= ' AND c.folder_id = ?';
            $sqlParams[] = $params['folder_id'];
        }

        $sql .= ' ORDER BY c.days_until_expiry ASC, c.name ASC';

        $certificates = $this->db->fetchAllAssociative($sql, $sqlParams);

        // Cast booleans
        foreach ($certificates as &$cert) {
            $cert['is_active'] = (bool) $cert['is_active'];
            $cert['chain_valid'] = $cert['chain_valid'] === null ? null : (bool) $cert['chain_valid'];
            $cert['notify_on_expiry_warning'] = (bool) $cert['notify_on_expiry_warning'];
            $cert['notify_on_expiry_critical'] = (bool) $cert['notify_on_expiry_critical'];
            $cert['notify_on_expired'] = (bool) $cert['notify_on_expired'];
            $cert['notify_on_renewed'] = (bool) $cert['notify_on_renewed'];
            $cert['notify_on_chain_error'] = (bool) $cert['notify_on_chain_error'];
            $cert['san_domains'] = json_decode($cert['san_domains'] ?? '[]', true);
            $cert['chain_info'] = json_decode($cert['chain_info'] ?? '[]', true);
        }

        // Get folders
        $folders = $this->db->fetchAllAssociative(
            'SELECT * FROM ssl_certificate_folders WHERE user_id = ? ORDER BY sort_order, name',
            [$userId]
        );

        foreach ($folders as &$folder) {
            $folder['is_collapsed'] = (bool) $folder['is_collapsed'];
        }

        // Get stats
        $stats = [
            'total' => count($certificates),
            'valid' => count(array_filter($certificates, fn($c) => $c['current_status'] === 'valid')),
            'expiring_soon' => count(array_filter($certificates, fn($c) => $c['current_status'] === 'expiring_soon')),
            'expired' => count(array_filter($certificates, fn($c) => $c['current_status'] === 'expired')),
            'error' => count(array_filter($certificates, fn($c) => in_array($c['current_status'], ['error', 'invalid']))),
        ];

        return JsonResponse::success([
            'items' => $certificates,
            'folders' => $folders,
            'stats' => $stats,
        ]);
    }

    /**
     * Get a single certificate
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $certificate = $this->db->fetchAssociative(
            'SELECT * FROM ssl_certificates WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$certificate) {
            return JsonResponse::notFound('Certificate not found');
        }

        // Get recent checks
        $checks = $this->db->fetchAllAssociative(
            'SELECT * FROM ssl_certificate_checks WHERE certificate_id = ? ORDER BY checked_at DESC LIMIT 30',
            [$id]
        );

        // Get notifications
        $notifications = $this->db->fetchAllAssociative(
            'SELECT * FROM ssl_notifications WHERE certificate_id = ? ORDER BY sent_at DESC LIMIT 10',
            [$id]
        );

        // Cast and decode
        $certificate['is_active'] = (bool) $certificate['is_active'];
        $certificate['chain_valid'] = $certificate['chain_valid'] === null ? null : (bool) $certificate['chain_valid'];
        $certificate['san_domains'] = json_decode($certificate['san_domains'] ?? '[]', true);
        $certificate['chain_info'] = json_decode($certificate['chain_info'] ?? '[]', true);

        foreach ($checks as &$check) {
            $check['chain_valid'] = $check['chain_valid'] === null ? null : (bool) $check['chain_valid'];
            $check['check_data'] = json_decode($check['check_data'] ?? '{}', true);
        }

        return JsonResponse::success([
            'certificate' => $certificate,
            'checks' => $checks,
            'notifications' => $notifications,
        ]);
    }

    /**
     * Create a new certificate monitor
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['name'])) {
            return JsonResponse::error('Name is required', 400);
        }

        if (empty($data['hostname'])) {
            return JsonResponse::error('Hostname is required', 400);
        }

        $id = Uuid::uuid4()->toString();

        $this->db->insert('ssl_certificates', [
            'id' => $id,
            'user_id' => $userId,
            'project_id' => $data['project_id'] ?? null,
            'folder_id' => $data['folder_id'] ?? null,
            'name' => $data['name'],
            'hostname' => $data['hostname'],
            'port' => $data['port'] ?? 443,
            'is_active' => 1,
            'check_interval' => $data['check_interval'] ?? 86400,
            'warn_days_before' => $data['warn_days_before'] ?? 30,
            'critical_days_before' => $data['critical_days_before'] ?? 7,
            'notify_on_expiry_warning' => $data['notify_on_expiry_warning'] ?? 1,
            'notify_on_expiry_critical' => $data['notify_on_expiry_critical'] ?? 1,
            'notify_on_expired' => $data['notify_on_expired'] ?? 1,
            'notify_on_renewed' => $data['notify_on_renewed'] ?? 1,
            'notify_on_chain_error' => $data['notify_on_chain_error'] ?? 1,
            'current_status' => 'pending',
        ]);

        // Perform initial check
        $this->performCheck($id);

        $certificate = $this->db->fetchAssociative(
            'SELECT * FROM ssl_certificates WHERE id = ?',
            [$id]
        );

        $certificate['san_domains'] = json_decode($certificate['san_domains'] ?? '[]', true);
        $certificate['chain_info'] = json_decode($certificate['chain_info'] ?? '[]', true);

        return JsonResponse::created($certificate, 'Certificate monitor created');
    }

    /**
     * Update a certificate monitor
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');
        $data = $request->getParsedBody() ?? [];

        $exists = $this->db->fetchOne(
            'SELECT 1 FROM ssl_certificates WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$exists) {
            return JsonResponse::notFound('Certificate not found');
        }

        $updateFields = [
            'name', 'hostname', 'port', 'is_active', 'check_interval',
            'warn_days_before', 'critical_days_before',
            'notify_on_expiry_warning', 'notify_on_expiry_critical',
            'notify_on_expired', 'notify_on_renewed', 'notify_on_chain_error',
            'project_id', 'folder_id'
        ];

        $updates = [];
        $params = [];

        foreach ($updateFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (!empty($updates)) {
            $params[] = $id;
            $this->db->executeStatement(
                'UPDATE ssl_certificates SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE id = ?',
                $params
            );
        }

        return JsonResponse::success(null, 'Certificate updated');
    }

    /**
     * Delete a certificate monitor
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $deleted = $this->db->delete('ssl_certificates', [
            'id' => $id,
            'user_id' => $userId,
        ]);

        if (!$deleted) {
            return JsonResponse::notFound('Certificate not found');
        }

        return JsonResponse::success(null, 'Certificate deleted');
    }

    /**
     * Check a certificate now
     */
    public function check(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $certificate = $this->db->fetchAssociative(
            'SELECT * FROM ssl_certificates WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$certificate) {
            return JsonResponse::notFound('Certificate not found');
        }

        $result = $this->performCheck($id);

        return JsonResponse::success($result, 'Certificate checked');
    }

    /**
     * Get overall statistics
     */
    public function stats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $stats = [
            'total' => $this->db->fetchOne(
                'SELECT COUNT(*) FROM ssl_certificates WHERE user_id = ?',
                [$userId]
            ),
            'by_status' => $this->db->fetchAllKeyValue(
                'SELECT current_status, COUNT(*) FROM ssl_certificates WHERE user_id = ? GROUP BY current_status',
                [$userId]
            ),
            'expiring_within_7_days' => $this->db->fetchAllAssociative(
                'SELECT id, name, hostname, days_until_expiry, valid_until
                 FROM ssl_certificates
                 WHERE user_id = ? AND days_until_expiry <= 7 AND days_until_expiry >= 0
                 ORDER BY days_until_expiry ASC',
                [$userId]
            ),
            'expiring_within_30_days' => $this->db->fetchAllAssociative(
                'SELECT id, name, hostname, days_until_expiry, valid_until
                 FROM ssl_certificates
                 WHERE user_id = ? AND days_until_expiry <= 30 AND days_until_expiry > 7
                 ORDER BY days_until_expiry ASC',
                [$userId]
            ),
            'expired' => $this->db->fetchAllAssociative(
                'SELECT id, name, hostname, days_until_expiry, valid_until
                 FROM ssl_certificates
                 WHERE user_id = ? AND (days_until_expiry < 0 OR current_status = \'expired\')
                 ORDER BY days_until_expiry ASC',
                [$userId]
            ),
        ];

        return JsonResponse::success($stats);
    }

    /**
     * Check all active certificates
     */
    public function checkAll(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $certificates = $this->db->fetchAllAssociative(
            'SELECT id FROM ssl_certificates WHERE user_id = ? AND is_active = 1',
            [$userId]
        );

        $results = [];
        foreach ($certificates as $cert) {
            $results[$cert['id']] = $this->performCheck($cert['id']);
        }

        return JsonResponse::success([
            'checked' => count($results),
            'results' => $results,
        ], 'All certificates checked');
    }

    // ==================== Folder Methods ====================

    /**
     * Create a folder
     */
    public function createFolder(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['name'])) {
            return JsonResponse::error('Name is required', 400);
        }

        $id = Uuid::uuid4()->toString();

        $this->db->insert('ssl_certificate_folders', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'color' => $data['color'] ?? '#6366f1',
            'icon' => $data['icon'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        $folder = $this->db->fetchAssociative(
            'SELECT * FROM ssl_certificate_folders WHERE id = ?',
            [$id]
        );

        return JsonResponse::created($folder, 'Folder created');
    }

    /**
     * Update a folder
     */
    public function updateFolder(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');
        $data = $request->getParsedBody() ?? [];

        $updated = $this->db->update('ssl_certificate_folders', [
            'name' => $data['name'] ?? null,
            'color' => $data['color'] ?? null,
            'icon' => $data['icon'] ?? null,
            'sort_order' => $data['sort_order'] ?? null,
            'is_collapsed' => $data['is_collapsed'] ?? null,
        ], [
            'id' => $id,
            'user_id' => $userId,
        ]);

        if (!$updated) {
            return JsonResponse::notFound('Folder not found');
        }

        return JsonResponse::success(null, 'Folder updated');
    }

    /**
     * Delete a folder
     */
    public function deleteFolder(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $deleted = $this->db->delete('ssl_certificate_folders', [
            'id' => $id,
            'user_id' => $userId,
        ]);

        if (!$deleted) {
            return JsonResponse::notFound('Folder not found');
        }

        return JsonResponse::success(null, 'Folder deleted');
    }

    // ==================== Helper Methods ====================

    private function performCheck(string $certificateId): array
    {
        $certificate = $this->db->fetchAssociative(
            'SELECT * FROM ssl_certificates WHERE id = ?',
            [$certificateId]
        );

        if (!$certificate) {
            return ['error' => 'Certificate not found'];
        }

        $result = $this->sslChecker->checkCertificate(
            $certificate['hostname'],
            (int) $certificate['port']
        );

        $checkId = Uuid::uuid4()->toString();

        // Record check
        $this->db->insert('ssl_certificate_checks', [
            'id' => $checkId,
            'certificate_id' => $certificateId,
            'status' => $result['status'],
            'issuer' => $result['issuer'] ?? null,
            'subject' => $result['subject'] ?? null,
            'valid_from' => $result['valid_from'] ?? null,
            'valid_until' => $result['valid_until'] ?? null,
            'days_until_expiry' => $result['days_until_expiry'] ?? null,
            'response_time_ms' => $result['response_time_ms'] ?? null,
            'chain_valid' => $result['chain_valid'] ?? null,
            'error_message' => $result['error_message'] ?? null,
            'check_data' => json_encode($result),
        ]);

        // Update certificate
        $updateData = [
            'current_status' => $result['status'],
            'last_check_at' => date('Y-m-d H:i:s'),
            'next_check_at' => date('Y-m-d H:i:s', time() + $certificate['check_interval']),
            'last_error' => $result['error_message'] ?? null,
        ];

        if ($result['success']) {
            $updateData['issuer'] = $result['issuer'];
            $updateData['subject'] = $result['subject'];
            $updateData['serial_number'] = $result['serial_number'];
            $updateData['valid_from'] = $result['valid_from'];
            $updateData['valid_until'] = $result['valid_until'];
            $updateData['days_until_expiry'] = $result['days_until_expiry'];
            $updateData['fingerprint_sha256'] = $result['fingerprint_sha256'];
            $updateData['fingerprint_sha1'] = $result['fingerprint_sha1'];
            $updateData['chain_valid'] = $result['chain_valid'];
            $updateData['chain_depth'] = $result['chain_depth'];
            $updateData['chain_info'] = json_encode($result['chain_info']);
            $updateData['san_domains'] = json_encode($result['san_domains']);

            // Check for renewal (fingerprint changed and days increased)
            $oldFingerprint = $certificate['fingerprint_sha256'];
            if ($oldFingerprint && $oldFingerprint !== $result['fingerprint_sha256']) {
                $oldDays = $certificate['days_until_expiry'] ?? 0;
                if ($result['days_until_expiry'] > $oldDays) {
                    // Certificate was renewed
                    if ($certificate['notify_on_renewed']) {
                        $this->recordNotification($certificateId, 'renewed', $result['days_until_expiry']);
                    }
                }
            }

            // Check if notification needed
            $notificationType = $this->sslChecker->getNotificationType(
                $result['days_until_expiry'],
                (int) $certificate['warn_days_before'],
                (int) $certificate['critical_days_before']
            );

            if ($notificationType && $this->shouldNotify($certificate, $notificationType)) {
                $this->recordNotification($certificateId, $notificationType, $result['days_until_expiry']);
            }
        }

        $this->db->update('ssl_certificates', $updateData, ['id' => $certificateId]);

        return $result;
    }

    private function shouldNotify(array $certificate, string $type): bool
    {
        // Check if this type of notification is enabled
        $enabled = match ($type) {
            'warning' => $certificate['notify_on_expiry_warning'],
            'critical' => $certificate['notify_on_expiry_critical'],
            'expired' => $certificate['notify_on_expired'],
            default => false,
        };

        if (!$enabled) {
            return false;
        }

        // Check if we already sent this notification type today
        $existingNotification = $this->db->fetchOne(
            'SELECT 1 FROM ssl_notifications
             WHERE certificate_id = ? AND notification_type = ? AND DATE(sent_at) = CURDATE()',
            [$certificate['id'], $type]
        );

        return !$existingNotification;
    }

    private function recordNotification(string $certificateId, string $type, ?int $daysUntilExpiry): void
    {
        $this->db->insert('ssl_notifications', [
            'id' => Uuid::uuid4()->toString(),
            'certificate_id' => $certificateId,
            'notification_type' => $type,
            'days_until_expiry' => $daysUntilExpiry,
        ]);

        $this->db->update('ssl_certificates', [
            'last_notification_at' => date('Y-m-d H:i:s'),
        ], ['id' => $certificateId]);
    }
}
