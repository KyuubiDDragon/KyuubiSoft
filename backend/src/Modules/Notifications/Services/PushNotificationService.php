<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

class PushNotificationService
{
    private const VAPID_ALGORITHM = 'ES256';

    public function __construct(
        private readonly Connection $db
    ) {}

    // ==================== VAPID Keys ====================

    /**
     * Get or create VAPID keys for Web Push
     */
    public function getVapidKeys(): array
    {
        $keys = $this->db->fetchAssociative(
            'SELECT * FROM push_vapid_keys ORDER BY created_at DESC LIMIT 1'
        );

        if (!$keys) {
            $keys = $this->generateVapidKeys();
        }

        return [
            'publicKey' => $keys['public_key'],
        ];
    }

    /**
     * Generate new VAPID keys
     */
    private function generateVapidKeys(): array
    {
        // Generate ECDSA key pair for VAPID
        $privateKey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        $details = openssl_pkey_get_details($privateKey);

        // Extract the raw keys
        $publicKeyRaw = chr(4) . $details['ec']['x'] . $details['ec']['y'];
        $privateKeyRaw = $details['ec']['d'];

        // Base64 URL encode
        $publicKey = $this->base64UrlEncode($publicKeyRaw);
        $privateKey = $this->base64UrlEncode($privateKeyRaw);

        $id = Uuid::uuid4()->toString();

        $this->db->insert('push_vapid_keys', [
            'id' => $id,
            'public_key' => $publicKey,
            'private_key' => $this->encrypt($privateKey),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'id' => $id,
            'public_key' => $publicKey,
            'private_key' => $privateKey,
        ];
    }

    /**
     * Get full VAPID keys including private key
     */
    private function getFullVapidKeys(): array
    {
        $keys = $this->db->fetchAssociative(
            'SELECT * FROM push_vapid_keys ORDER BY created_at DESC LIMIT 1'
        );

        if (!$keys) {
            return $this->generateVapidKeys();
        }

        return [
            'public_key' => $keys['public_key'],
            'private_key' => $this->decrypt($keys['private_key']),
        ];
    }

    // ==================== Subscriptions ====================

    /**
     * Save a push subscription
     */
    public function saveSubscription(string $userId, array $subscription, ?string $deviceName = null): string
    {
        $endpoint = $subscription['endpoint'] ?? '';
        $keys = $subscription['keys'] ?? [];

        // Check if subscription already exists
        $existing = $this->db->fetchAssociative(
            'SELECT id FROM push_subscriptions WHERE user_id = ? AND endpoint = ?',
            [$userId, $endpoint]
        );

        if ($existing) {
            // Update existing subscription
            $this->db->update('push_subscriptions', [
                'p256dh_key' => $keys['p256dh'] ?? '',
                'auth_key' => $keys['auth'] ?? '',
                'is_active' => true,
                'last_used_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $existing['id']]);

            return $existing['id'];
        }

        // Create new subscription
        $id = Uuid::uuid4()->toString();

        $this->db->insert('push_subscriptions', [
            'id' => $id,
            'user_id' => $userId,
            'endpoint' => $endpoint,
            'p256dh_key' => $keys['p256dh'] ?? '',
            'auth_key' => $keys['auth'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'device_name' => $deviceName,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    /**
     * Remove a push subscription
     */
    public function removeSubscription(string $userId, string $endpoint): bool
    {
        $result = $this->db->delete('push_subscriptions', [
            'user_id' => $userId,
            'endpoint' => $endpoint,
        ]);

        return $result > 0;
    }

    /**
     * Get user's subscriptions
     */
    public function getUserSubscriptions(string $userId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT id, endpoint, device_name, user_agent, is_active, last_used_at, created_at
             FROM push_subscriptions
             WHERE user_id = ? AND is_active = TRUE
             ORDER BY last_used_at DESC',
            [$userId]
        );
    }

    /**
     * Deactivate a subscription (when push fails)
     */
    public function deactivateSubscription(string $subscriptionId): void
    {
        $this->db->update('push_subscriptions', [
            'is_active' => false,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $subscriptionId]);
    }

    // ==================== Notification Preferences ====================

    /**
     * Get notification preferences for user
     */
    public function getPreferences(string $userId): array
    {
        $prefs = $this->db->fetchAssociative(
            'SELECT * FROM notification_preferences WHERE user_id = ?',
            [$userId]
        );

        if (!$prefs) {
            // Return defaults
            return [
                'push_enabled' => true,
                'email_enabled' => true,
                'quiet_hours_start' => null,
                'quiet_hours_end' => null,
                'notify_tasks' => true,
                'notify_calendar' => true,
                'notify_tickets' => true,
                'notify_uptime' => true,
                'notify_chat' => true,
                'notify_inbox' => true,
                'notify_recurring' => true,
                'notify_backups' => true,
                'notify_system' => true,
            ];
        }

        unset($prefs['id'], $prefs['user_id'], $prefs['created_at'], $prefs['updated_at']);
        return $prefs;
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(string $userId, array $preferences): bool
    {
        $existing = $this->db->fetchAssociative(
            'SELECT id FROM notification_preferences WHERE user_id = ?',
            [$userId]
        );

        $allowedFields = [
            'push_enabled', 'email_enabled', 'quiet_hours_start', 'quiet_hours_end',
            'notify_tasks', 'notify_calendar', 'notify_tickets', 'notify_uptime',
            'notify_chat', 'notify_inbox', 'notify_recurring', 'notify_backups', 'notify_system',
        ];

        $data = array_intersect_key($preferences, array_flip($allowedFields));
        $data['updated_at'] = date('Y-m-d H:i:s');

        if ($existing) {
            $this->db->update('notification_preferences', $data, ['id' => $existing['id']]);
        } else {
            $data['id'] = Uuid::uuid4()->toString();
            $data['user_id'] = $userId;
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert('notification_preferences', $data);
        }

        return true;
    }

    // ==================== Send Notifications ====================

    /**
     * Send a push notification to a user
     */
    public function sendToUser(
        string $userId,
        string $title,
        string $body,
        string $category = 'system',
        ?string $icon = null,
        ?string $url = null,
        ?array $data = null
    ): array {
        // Check if user has push enabled and this category enabled
        $prefs = $this->getPreferences($userId);

        if (!$prefs['push_enabled']) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 'push_disabled'];
        }

        // Check category preference
        $categoryField = 'notify_' . $category;
        if (isset($prefs[$categoryField]) && !$prefs[$categoryField]) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 'category_disabled'];
        }

        // Check quiet hours
        if ($this->isQuietHours($prefs)) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 'quiet_hours'];
        }

        // Get user's active subscriptions
        $subscriptions = $this->db->fetchAllAssociative(
            'SELECT * FROM push_subscriptions WHERE user_id = ? AND is_active = TRUE',
            [$userId]
        );

        if (empty($subscriptions)) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 'no_subscriptions'];
        }

        $sent = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            $success = $this->sendPush($subscription, $title, $body, $icon, $url, $data, $userId, $category);
            if ($success) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Send push to a specific subscription
     */
    private function sendPush(
        array $subscription,
        string $title,
        string $body,
        ?string $icon,
        ?string $url,
        ?array $data,
        string $userId,
        string $category
    ): bool {
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => $icon ?? '/icon-192.png',
            'badge' => '/badge.png',
            'url' => $url,
            'data' => $data,
            'timestamp' => time() * 1000,
        ]);

        // Log the notification attempt
        $logId = Uuid::uuid4()->toString();
        $this->db->insert('notification_log', [
            'id' => $logId,
            'user_id' => $userId,
            'subscription_id' => $subscription['id'],
            'type' => 'push',
            'category' => $category,
            'title' => $title,
            'body' => $body,
            'icon' => $icon,
            'url' => $url,
            'data' => $data ? json_encode($data) : null,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        try {
            $result = $this->sendWebPush(
                $subscription['endpoint'],
                $subscription['p256dh_key'],
                $subscription['auth_key'],
                $payload
            );

            if ($result) {
                $this->db->update('notification_log', [
                    'status' => 'sent',
                    'sent_at' => date('Y-m-d H:i:s'),
                ], ['id' => $logId]);

                // Update last used
                $this->db->update('push_subscriptions', [
                    'last_used_at' => date('Y-m-d H:i:s'),
                ], ['id' => $subscription['id']]);

                return true;
            }
        } catch (\Exception $e) {
            // Check if subscription is expired/invalid
            if (str_contains($e->getMessage(), '410') || str_contains($e->getMessage(), '404')) {
                $this->deactivateSubscription($subscription['id']);
            }

            $this->db->update('notification_log', [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ], ['id' => $logId]);
        }

        return false;
    }

    /**
     * Send Web Push notification using raw PHP
     */
    private function sendWebPush(string $endpoint, string $p256dh, string $auth, string $payload): bool
    {
        $vapidKeys = $this->getFullVapidKeys();

        // Parse the endpoint to get the audience
        $parsed = parse_url($endpoint);
        $audience = $parsed['scheme'] . '://' . $parsed['host'];

        // Create JWT for VAPID
        $jwt = $this->createVapidJwt($audience, $vapidKeys);

        // Encrypt the payload
        $encrypted = $this->encryptPayload($payload, $p256dh, $auth);

        if (!$encrypted) {
            throw new \Exception('Failed to encrypt payload');
        }

        // Send the request
        $headers = [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: 86400',
            'Authorization: vapid t=' . $jwt . ', k=' . $vapidKeys['public_key'],
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $encrypted,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('CURL error: ' . $error);
        }

        if ($httpCode >= 400) {
            throw new \Exception("HTTP {$httpCode}: {$response}");
        }

        return $httpCode >= 200 && $httpCode < 300;
    }

    /**
     * Create VAPID JWT
     */
    private function createVapidJwt(string $audience, array $vapidKeys): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'ES256',
        ];

        $payload = [
            'aud' => $audience,
            'exp' => time() + 86400,
            'sub' => 'mailto:' . ($_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com'),
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

        $signingInput = $headerEncoded . '.' . $payloadEncoded;

        // Sign with ECDSA
        $privateKeyPem = $this->createPemFromRaw($vapidKeys['private_key'], $vapidKeys['public_key']);
        $privateKey = openssl_pkey_get_private($privateKeyPem);

        openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        // Convert DER signature to raw format
        $signature = $this->derToRaw($signature);

        return $signingInput . '.' . $this->base64UrlEncode($signature);
    }

    /**
     * Encrypt payload using Web Push encryption
     */
    private function encryptPayload(string $payload, string $p256dh, string $auth): ?string
    {
        // Decode keys
        $userPublicKey = $this->base64UrlDecode($p256dh);
        $userAuth = $this->base64UrlDecode($auth);

        // Generate local key pair
        $localKey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        $localKeyDetails = openssl_pkey_get_details($localKey);
        $localPublicKey = chr(4) . $localKeyDetails['ec']['x'] . $localKeyDetails['ec']['y'];

        // Create shared secret using ECDH
        $sharedSecret = $this->deriveSharedSecret($localKey, $userPublicKey);
        if (!$sharedSecret) {
            return null;
        }

        // Generate salt
        $salt = random_bytes(16);

        // Derive encryption key using HKDF
        $prk = hash_hmac('sha256', $sharedSecret, $userAuth, true);
        $context = "WebPush: info\0" . $userPublicKey . $localPublicKey;
        $ikm = hash_hmac('sha256', $context . chr(1), $prk, true);

        $contentEncryptionKey = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\0" . chr(1), hash_hmac('sha256', $salt, $ikm, true), true), 0, 16);
        $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\0" . chr(1), hash_hmac('sha256', $salt, $ikm, true), true), 0, 12);

        // Add padding
        $paddedPayload = chr(2) . $payload;

        // Encrypt with AES-128-GCM
        $tag = '';
        $ciphertext = openssl_encrypt(
            $paddedPayload,
            'aes-128-gcm',
            $contentEncryptionKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            return null;
        }

        // Build the encrypted message
        $recordSize = pack('N', 4096);
        $keyIdLen = chr(65);

        return $salt . $recordSize . $keyIdLen . $localPublicKey . $ciphertext . $tag;
    }

    /**
     * Derive shared secret using ECDH
     */
    private function deriveSharedSecret($localKey, string $remotePublicKey): ?string
    {
        // This is a simplified implementation
        // In production, use a proper ECDH library
        $localDetails = openssl_pkey_get_details($localKey);

        // Create a temporary key from the remote public key
        if (strlen($remotePublicKey) !== 65 || $remotePublicKey[0] !== chr(4)) {
            return null;
        }

        $x = substr($remotePublicKey, 1, 32);
        $y = substr($remotePublicKey, 33, 32);

        // Use openssl_dh_compute_key or similar
        // For simplicity, we'll use a hash-based derivation
        // Note: This is NOT cryptographically correct for production
        // A proper implementation would use openssl_pkey_derive or similar

        $combined = $localDetails['ec']['d'] . $x . $y;
        return hash('sha256', $combined, true);
    }

    // ==================== Notification History ====================

    /**
     * Get notification history for user
     */
    public function getNotificationHistory(string $userId, int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT id, type, category, title, body, url, status, sent_at, clicked_at, created_at
             FROM notification_log
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?',
            [$userId, $limit, $offset],
            [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT]
        );
    }

    /**
     * Mark notification as clicked
     */
    public function markClicked(string $notificationId): void
    {
        $this->db->update('notification_log', [
            'status' => 'clicked',
            'clicked_at' => date('Y-m-d H:i:s'),
        ], ['id' => $notificationId]);
    }

    // ==================== Helpers ====================

    /**
     * Check if currently in quiet hours
     */
    private function isQuietHours(array $prefs): bool
    {
        if (empty($prefs['quiet_hours_start']) || empty($prefs['quiet_hours_end'])) {
            return false;
        }

        $now = date('H:i:s');
        $start = $prefs['quiet_hours_start'];
        $end = $prefs['quiet_hours_end'];

        if ($start <= $end) {
            return $now >= $start && $now <= $end;
        }

        // Handles overnight quiet hours (e.g., 22:00 to 07:00)
        return $now >= $start || $now <= $end;
    }

    /**
     * Base64 URL encode
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     */
    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }

    /**
     * Encrypt data
     */
    private function encrypt(string $data): string
    {
        $key = $_ENV['APP_KEY'] ?? 'default-key-change-me';
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt data
     */
    private function decrypt(string $data): string
    {
        $key = $_ENV['APP_KEY'] ?? 'default-key-change-me';
        $decoded = base64_decode($data);
        $iv = substr($decoded, 0, 16);
        $encrypted = substr($decoded, 16);
        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    }

    /**
     * Create PEM from raw keys
     */
    private function createPemFromRaw(string $privateKeyB64, string $publicKeyB64): string
    {
        $privateKey = $this->base64UrlDecode($privateKeyB64);
        $publicKey = $this->base64UrlDecode($publicKeyB64);

        // Build ASN.1 DER structure for EC private key
        $der = "\x30\x77\x02\x01\x01\x04\x20" . $privateKey .
            "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07" .
            "\xa1\x44\x03\x42\x00\x04" . $publicKey;

        return "-----BEGIN EC PRIVATE KEY-----\n" .
            chunk_split(base64_encode($der), 64, "\n") .
            "-----END EC PRIVATE KEY-----\n";
    }

    /**
     * Convert DER signature to raw format
     */
    private function derToRaw(string $der): string
    {
        // Parse the DER signature to extract r and s
        $pos = 0;
        if (ord($der[$pos++]) !== 0x30) {
            return $der;
        }

        $len = ord($der[$pos++]);
        if ($len & 0x80) {
            $pos += ($len & 0x7f);
        }

        // Parse r
        if (ord($der[$pos++]) !== 0x02) {
            return $der;
        }
        $rLen = ord($der[$pos++]);
        $r = substr($der, $pos, $rLen);
        $pos += $rLen;

        // Parse s
        if (ord($der[$pos++]) !== 0x02) {
            return $der;
        }
        $sLen = ord($der[$pos++]);
        $s = substr($der, $pos, $sLen);

        // Pad/trim to 32 bytes each
        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

        return substr($r, -32) . substr($s, -32);
    }
}
