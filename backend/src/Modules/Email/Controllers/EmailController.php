<?php

declare(strict_types=1);

namespace App\Modules\Email\Controllers;

use App\Core\Http\JsonResponse;
use App\Core\Services\MailService;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class EmailController
{
    public function __construct(
        private readonly Connection $db,
        private readonly MailService $mailService
    ) {}

    /**
     * Extract route argument from request
     */
    private function getRouteArg(ServerRequestInterface $request, string $name): ?string
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        return $route ? $route->getArgument($name) : null;
    }

    /**
     * Encrypt a password
     */
    private function encryptPassword(string $password): string
    {
        $key = $_ENV['APP_KEY'] ?? 'default-key';
        return openssl_encrypt($password, 'aes-256-cbc', $key, 0, str_repeat('0', 16));
    }

    /**
     * Decrypt a password
     */
    private function decryptPassword(string $encrypted): string
    {
        $key = $_ENV['APP_KEY'] ?? 'default-key';
        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, str_repeat('0', 16));
    }

    // ==================== Accounts ====================

    /**
     * List user's email accounts
     */
    public function getAccounts(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $accounts = $this->db->fetchAllAssociative(
            'SELECT id, user_id, name, email, imap_host, imap_port, imap_encryption,
                    smtp_host, smtp_port, smtp_encryption, username, is_default, is_active,
                    last_sync_at, created_at, updated_at
             FROM email_accounts
             WHERE user_id = ?
             ORDER BY is_default DESC, created_at ASC',
            [$userId]
        );

        // Cast booleans
        foreach ($accounts as &$account) {
            $account['is_default'] = (bool) $account['is_default'];
            $account['is_active'] = (bool) $account['is_active'];
        }

        return JsonResponse::success(['items' => $accounts]);
    }

    /**
     * Create a new email account
     */
    public function createAccount(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        // Validate required fields
        $required = ['name', 'email', 'imap_host', 'smtp_host', 'username', 'password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return JsonResponse::error("Feld '{$field}' ist erforderlich", 400);
            }
        }

        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return JsonResponse::error('Ungültiges E-Mail-Format', 400);
        }

        $id = Uuid::uuid4()->toString();
        $now = date('Y-m-d H:i:s');

        // If this is the first account, make it default
        $existingCount = $this->db->fetchOne(
            'SELECT COUNT(*) FROM email_accounts WHERE user_id = ?',
            [$userId]
        );
        $isDefault = (int) $existingCount === 0;

        $this->db->insert('email_accounts', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'email' => $data['email'],
            'imap_host' => $data['imap_host'],
            'imap_port' => (int) ($data['imap_port'] ?? 993),
            'imap_encryption' => $data['imap_encryption'] ?? 'ssl',
            'smtp_host' => $data['smtp_host'],
            'smtp_port' => (int) ($data['smtp_port'] ?? 587),
            'smtp_encryption' => $data['smtp_encryption'] ?? 'tls',
            'username' => $data['username'],
            'password_encrypted' => $this->encryptPassword($data['password']),
            'is_default' => $isDefault,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $account = $this->db->fetchAssociative(
            'SELECT id, user_id, name, email, imap_host, imap_port, imap_encryption,
                    smtp_host, smtp_port, smtp_encryption, username, is_default, is_active,
                    last_sync_at, created_at, updated_at
             FROM email_accounts WHERE id = ?',
            [$id]
        );

        $account['is_default'] = (bool) $account['is_default'];
        $account['is_active'] = (bool) $account['is_active'];

        return JsonResponse::created($account);
    }

    /**
     * Update an email account
     */
    public function updateAccount(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');
        $data = $request->getParsedBody() ?? [];

        // Verify ownership
        $account = $this->db->fetchAssociative(
            'SELECT id FROM email_accounts WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$account) {
            return JsonResponse::error('Konto nicht gefunden', 404);
        }

        $updateData = [];
        $allowedFields = ['name', 'email', 'imap_host', 'imap_port', 'imap_encryption',
                          'smtp_host', 'smtp_port', 'smtp_encryption', 'username', 'is_active'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        // Handle password separately (needs encryption)
        if (!empty($data['password'])) {
            $updateData['password_encrypted'] = $this->encryptPassword($data['password']);
        }

        // Handle default flag
        if (isset($data['is_default']) && $data['is_default']) {
            // Unset other defaults first
            $this->db->executeStatement(
                'UPDATE email_accounts SET is_default = FALSE WHERE user_id = ?',
                [$userId]
            );
            $updateData['is_default'] = true;
        }

        if (!empty($updateData)) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $this->db->update('email_accounts', $updateData, ['id' => $id, 'user_id' => $userId]);
        }

        $updated = $this->db->fetchAssociative(
            'SELECT id, user_id, name, email, imap_host, imap_port, imap_encryption,
                    smtp_host, smtp_port, smtp_encryption, username, is_default, is_active,
                    last_sync_at, created_at, updated_at
             FROM email_accounts WHERE id = ?',
            [$id]
        );

        $updated['is_default'] = (bool) $updated['is_default'];
        $updated['is_active'] = (bool) $updated['is_active'];

        return JsonResponse::success($updated);
    }

    /**
     * Delete an email account
     */
    public function deleteAccount(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $account = $this->db->fetchAssociative(
            'SELECT id FROM email_accounts WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$account) {
            return JsonResponse::error('Konto nicht gefunden', 404);
        }

        // Cascade will handle messages
        $this->db->delete('email_accounts', ['id' => $id, 'user_id' => $userId]);

        return JsonResponse::success(['message' => 'Konto gelöscht']);
    }

    /**
     * Test IMAP/SMTP connection
     */
    public function testConnection(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $account = $this->db->fetchAssociative(
            'SELECT id, imap_host, imap_port, imap_encryption,
                    smtp_host, smtp_port, smtp_encryption,
                    username, password_encrypted
             FROM email_accounts WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$account) {
            return JsonResponse::error('Konto nicht gefunden', 404);
        }

        $password = $this->decryptPassword($account['password_encrypted']);

        $imapResult = $this->mailService->testImapConnection(
            $account['imap_host'],
            (int) $account['imap_port'],
            $account['imap_encryption'],
            $account['username'],
            $password
        );

        $smtpResult = $this->mailService->testSmtpConnection(
            $account['smtp_host'],
            (int) $account['smtp_port'],
            $account['smtp_encryption'],
            $account['username'],
            $password
        );

        return JsonResponse::success([
            'imap' => $imapResult,
            'smtp' => $smtpResult,
        ]);
    }

    // ==================== Messages ====================

    /**
     * List messages with pagination
     */
    public function getMessages(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $folder = $params['folder'] ?? 'INBOX';
        $accountId = $params['account_id'] ?? null;
        $search = $params['search'] ?? null;
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $conditions = ['m.user_id = ?'];
        $bindings = [$userId];

        if ($accountId) {
            $conditions[] = 'm.account_id = ?';
            $bindings[] = $accountId;
        }

        if ($folder) {
            $conditions[] = 'm.folder = ?';
            $bindings[] = $folder;
        }

        if ($search) {
            $conditions[] = '(m.subject LIKE ? OR m.from_name LIKE ? OR m.from_address LIKE ? OR m.body_text LIKE ?)';
            $searchTerm = '%' . $search . '%';
            $bindings[] = $searchTerm;
            $bindings[] = $searchTerm;
            $bindings[] = $searchTerm;
            $bindings[] = $searchTerm;
        }

        $where = implode(' AND ', $conditions);

        $total = $this->db->fetchOne(
            "SELECT COUNT(*) FROM email_messages m WHERE {$where}",
            $bindings
        );

        $messages = $this->db->fetchAllAssociative(
            "SELECT m.id, m.account_id, m.message_id, m.folder, m.from_address, m.from_name,
                    m.to_addresses, m.cc_addresses, m.subject,
                    SUBSTRING(m.body_text, 1, 200) as body_preview,
                    m.is_read, m.is_starred, m.is_draft, m.has_attachments, m.received_at, m.created_at
             FROM email_messages m
             WHERE {$where}
             ORDER BY m.received_at DESC
             LIMIT ? OFFSET ?",
            [...$bindings, $limit, $offset]
        );

        // Cast booleans and decode JSON
        foreach ($messages as &$msg) {
            $msg['is_read'] = (bool) $msg['is_read'];
            $msg['is_starred'] = (bool) $msg['is_starred'];
            $msg['is_draft'] = (bool) $msg['is_draft'];
            $msg['has_attachments'] = (bool) $msg['has_attachments'];
            $msg['to_addresses'] = json_decode($msg['to_addresses'], true);
            $msg['cc_addresses'] = $msg['cc_addresses'] ? json_decode($msg['cc_addresses'], true) : [];
        }

        return JsonResponse::success([
            'items' => $messages,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int) $total,
                'pages' => (int) ceil((int) $total / $limit),
            ],
        ]);
    }

    /**
     * Get a single message with full body
     */
    public function getMessage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $message = $this->db->fetchAssociative(
            'SELECT * FROM email_messages WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$message) {
            return JsonResponse::error('Nachricht nicht gefunden', 404);
        }

        // Mark as read
        if (!$message['is_read']) {
            $this->db->update('email_messages', ['is_read' => true], ['id' => $id]);
            $message['is_read'] = true;
        }

        // Cast booleans and decode JSON
        $message['is_read'] = (bool) $message['is_read'];
        $message['is_starred'] = (bool) $message['is_starred'];
        $message['is_draft'] = (bool) $message['is_draft'];
        $message['has_attachments'] = (bool) $message['has_attachments'];
        $message['to_addresses'] = json_decode($message['to_addresses'], true);
        $message['cc_addresses'] = $message['cc_addresses'] ? json_decode($message['cc_addresses'], true) : [];

        return JsonResponse::success($message);
    }

    /**
     * Send (compose) a message via SMTP
     */
    public function sendMessage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['account_id'])) {
            return JsonResponse::error('Konto ist erforderlich', 400);
        }

        if (empty($data['to'])) {
            return JsonResponse::error('Empfänger ist erforderlich', 400);
        }

        // Verify account ownership and get SMTP settings
        $account = $this->db->fetchAssociative(
            'SELECT id, email, name, smtp_host, smtp_port, smtp_encryption,
                    username, password_encrypted
             FROM email_accounts WHERE id = ? AND user_id = ?',
            [$data['account_id'], $userId]
        );

        if (!$account) {
            return JsonResponse::error('Konto nicht gefunden', 404);
        }

        $id = Uuid::uuid4()->toString();
        $now = date('Y-m-d H:i:s');

        // Normalize to_addresses
        $toAddresses = is_array($data['to']) ? $data['to'] : [['email' => $data['to']]];
        $ccAddresses = isset($data['cc']) ? (is_array($data['cc']) ? $data['cc'] : [['email' => $data['cc']]]) : [];

        $isDraft = (bool) ($data['is_draft'] ?? false);

        // Actually send via SMTP if not a draft
        if (!$isDraft) {
            try {
                $password = $this->decryptPassword($account['password_encrypted']);
                $this->mailService->send(
                    [
                        'host' => $account['smtp_host'],
                        'port' => $account['smtp_port'],
                        'encryption' => $account['smtp_encryption'],
                        'username' => $account['username'],
                        'password' => $password,
                    ],
                    $account['email'],
                    $account['name'],
                    $toAddresses,
                    $ccAddresses,
                    $data['subject'] ?? '',
                    $data['body'] ?? '',
                    strip_tags($data['body'] ?? '')
                );
            } catch (\RuntimeException $e) {
                return JsonResponse::error($e->getMessage(), 500);
            }
        }

        $this->db->insert('email_messages', [
            'id' => $id,
            'account_id' => $data['account_id'],
            'user_id' => $userId,
            'message_id' => '<' . Uuid::uuid4()->toString() . '@kyuubisoft>',
            'folder' => $isDraft ? 'Entwürfe' : 'Gesendet',
            'from_address' => $account['email'],
            'from_name' => $account['name'],
            'to_addresses' => json_encode($toAddresses),
            'cc_addresses' => !empty($ccAddresses) ? json_encode($ccAddresses) : null,
            'subject' => $data['subject'] ?? '',
            'body_text' => strip_tags($data['body'] ?? ''),
            'body_html' => $data['body'] ?? '',
            'is_read' => true,
            'is_starred' => false,
            'is_draft' => $isDraft,
            'has_attachments' => false,
            'received_at' => $now,
            'created_at' => $now,
        ]);

        $message = $this->db->fetchAssociative(
            'SELECT * FROM email_messages WHERE id = ?',
            [$id]
        );

        $message['is_read'] = (bool) $message['is_read'];
        $message['is_starred'] = (bool) $message['is_starred'];
        $message['is_draft'] = (bool) $message['is_draft'];
        $message['has_attachments'] = (bool) $message['has_attachments'];
        $message['to_addresses'] = json_decode($message['to_addresses'], true);
        $message['cc_addresses'] = $message['cc_addresses'] ? json_decode($message['cc_addresses'], true) : [];

        return JsonResponse::created($message);
    }

    /**
     * Delete a message
     */
    public function deleteMessage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $message = $this->db->fetchAssociative(
            'SELECT id, folder FROM email_messages WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$message) {
            return JsonResponse::error('Nachricht nicht gefunden', 404);
        }

        // If already in trash, permanently delete
        if ($message['folder'] === 'Papierkorb') {
            $this->db->delete('email_messages', ['id' => $id, 'user_id' => $userId]);
            return JsonResponse::success(['message' => 'Nachricht endgültig gelöscht']);
        }

        // Move to trash
        $this->db->update('email_messages', ['folder' => 'Papierkorb'], ['id' => $id, 'user_id' => $userId]);

        return JsonResponse::success(['message' => 'Nachricht in Papierkorb verschoben']);
    }

    /**
     * Toggle read status
     */
    public function toggleRead(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $message = $this->db->fetchAssociative(
            'SELECT id, is_read FROM email_messages WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$message) {
            return JsonResponse::error('Nachricht nicht gefunden', 404);
        }

        $newStatus = !((bool) $message['is_read']);
        $this->db->update('email_messages', ['is_read' => $newStatus], ['id' => $id, 'user_id' => $userId]);

        return JsonResponse::success(['is_read' => $newStatus]);
    }

    /**
     * Toggle star status
     */
    public function toggleStar(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $message = $this->db->fetchAssociative(
            'SELECT id, is_starred FROM email_messages WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$message) {
            return JsonResponse::error('Nachricht nicht gefunden', 404);
        }

        $newStatus = !((bool) $message['is_starred']);
        $this->db->update('email_messages', ['is_starred' => $newStatus], ['id' => $id, 'user_id' => $userId]);

        return JsonResponse::success(['is_starred' => $newStatus]);
    }

    // ==================== Folders & Stats ====================

    /**
     * Get standard folders
     */
    public function getFolders(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $folders = [
            ['id' => 'INBOX', 'name' => 'Posteingang', 'icon' => 'inbox'],
            ['id' => 'Gesendet', 'name' => 'Gesendet', 'icon' => 'send'],
            ['id' => 'Entwürfe', 'name' => 'Entwürfe', 'icon' => 'draft'],
            ['id' => 'Papierkorb', 'name' => 'Papierkorb', 'icon' => 'trash'],
            ['id' => 'Spam', 'name' => 'Spam', 'icon' => 'spam'],
        ];

        // Add unread counts
        foreach ($folders as &$folder) {
            $count = $this->db->fetchOne(
                'SELECT COUNT(*) FROM email_messages WHERE user_id = ? AND folder = ? AND is_read = FALSE',
                [$userId, $folder['id']]
            );
            $folder['unread'] = (int) $count;
        }

        return JsonResponse::success(['items' => $folders]);
    }

    /**
     * Get email stats
     */
    public function getStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();
        $accountId = $params['account_id'] ?? null;

        $conditions = ['user_id = ?'];
        $bindings = [$userId];

        if ($accountId) {
            $conditions[] = 'account_id = ?';
            $bindings[] = $accountId;
        }

        $where = implode(' AND ', $conditions);

        $totalUnread = $this->db->fetchOne(
            "SELECT COUNT(*) FROM email_messages WHERE {$where} AND is_read = FALSE",
            $bindings
        );

        $totalMessages = $this->db->fetchOne(
            "SELECT COUNT(*) FROM email_messages WHERE {$where}",
            $bindings
        );

        $inboxUnread = $this->db->fetchOne(
            "SELECT COUNT(*) FROM email_messages WHERE {$where} AND folder = 'INBOX' AND is_read = FALSE",
            $bindings
        );

        // Per-folder counts
        $folderCounts = $this->db->fetchAllAssociative(
            "SELECT folder, COUNT(*) as total, SUM(CASE WHEN is_read = FALSE THEN 1 ELSE 0 END) as unread
             FROM email_messages WHERE {$where} GROUP BY folder",
            $bindings
        );

        $folders = [];
        foreach ($folderCounts as $fc) {
            $folders[$fc['folder']] = [
                'total' => (int) $fc['total'],
                'unread' => (int) $fc['unread'],
            ];
        }

        return JsonResponse::success([
            'total_unread' => (int) $totalUnread,
            'total_messages' => (int) $totalMessages,
            'inbox_unread' => (int) $inboxUnread,
            'folders' => $folders,
        ]);
    }
}
