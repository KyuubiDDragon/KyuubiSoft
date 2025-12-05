<?php

declare(strict_types=1);

namespace App\Core\Services;

use Doctrine\DBAL\Connection;
use Psr\Http\Message\ServerRequestInterface;

class AuditLogger
{
    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * Log an action to the audit log
     */
    public function log(
        ?string $userId,
        string $action,
        string $entityType,
        ?string $entityId = null,
        ?ServerRequestInterface $request = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $this->db->insert('audit_logs', [
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => $request ? $this->getClientIp($request) : null,
            'user_agent' => $request ? substr($request->getHeaderLine('User-Agent'), 0, 500) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log a login action
     */
    public function logLogin(string $userId, ServerRequestInterface $request): void
    {
        $this->log($userId, 'user.login', 'user', $userId, $request);
    }

    /**
     * Log a logout action
     */
    public function logLogout(string $userId, ServerRequestInterface $request): void
    {
        $this->log($userId, 'user.logout', 'user', $userId, $request);
    }

    /**
     * Log a registration action
     */
    public function logRegister(string $userId, ServerRequestInterface $request): void
    {
        $this->log($userId, 'user.register', 'user', $userId, $request);
    }

    /**
     * Log a failed login attempt
     */
    public function logFailedLogin(string $email, ServerRequestInterface $request): void
    {
        $this->log(null, 'user.login_failed', 'user', null, $request, null, ['email' => $email]);
    }

    /**
     * Log 2FA enabled
     */
    public function log2FAEnabled(string $userId, ServerRequestInterface $request): void
    {
        $this->log($userId, '2fa.enabled', 'user', $userId, $request);
    }

    /**
     * Log 2FA disabled
     */
    public function log2FADisabled(string $userId, ServerRequestInterface $request): void
    {
        $this->log($userId, '2fa.disabled', 'user', $userId, $request);
    }

    /**
     * Log sensitive operation 2FA verification
     */
    public function logSensitiveOperation(string $userId, string $operation, ServerRequestInterface $request): void
    {
        $this->log($userId, '2fa.sensitive_operation', 'user', $userId, $request, null, ['operation' => $operation]);
    }

    private function getClientIp(ServerRequestInterface $request): ?string
    {
        $serverParams = $request->getServerParams();

        if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $serverParams['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        if (!empty($serverParams['HTTP_X_REAL_IP'])) {
            return $serverParams['HTTP_X_REAL_IP'];
        }

        return $serverParams['REMOTE_ADDR'] ?? null;
    }
}
