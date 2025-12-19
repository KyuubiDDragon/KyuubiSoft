<?php

declare(strict_types=1);

namespace App\Modules\Discord\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Modules\Discord\Services\DiscordApiService;
use App\Modules\Discord\Services\DiscordBotApiService;
use App\Modules\Discord\Repositories\DiscordAccountRepository;
use App\Modules\Discord\Repositories\DiscordBackupRepository;
use App\Modules\Discord\Repositories\DiscordBotRepository;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class DiscordController
{
    private string $encryptionKey;
    private string $storagePath;

    public function __construct(
        private readonly Connection $db,
        private readonly DiscordApiService $discordApi,
        private readonly DiscordBotApiService $botApi,
        private readonly DiscordAccountRepository $accountRepository,
        private readonly DiscordBackupRepository $backupRepository,
        private readonly DiscordBotRepository $botRepository
    ) {
        // Hash the APP_KEY to ensure it's exactly 32 bytes for AES-256-CBC
        $appKey = $_ENV['APP_KEY'] ?? 'default-key-change-me';
        $this->encryptionKey = hash('sha256', $appKey, true);
        $this->storagePath = $_ENV['STORAGE_PATH'] ?? '/var/www/storage';
    }

    // ========================================================================
    // Account Management
    // ========================================================================

    public function getAccounts(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $accounts = $this->accountRepository->findAllByUser($userId);

        // Add avatar URLs
        foreach ($accounts as &$account) {
            $account['avatar_url'] = $this->discordApi->getAvatarUrl(
                $account['discord_user_id'],
                $account['discord_avatar']
            );
        }

        return JsonResponse::success(['items' => $accounts]);
    }

    public function addAccount(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['token'])) {
            throw new ValidationException('Discord token is required');
        }

        $token = trim($data['token']);

        // Validate token with Discord API
        $discordUser = $this->discordApi->validateToken($token);

        if (!$discordUser) {
            throw new ValidationException('Invalid Discord token. Please check your token and try again.');
        }

        // Check if account already exists
        $existing = $this->db->fetchAssociative(
            'SELECT id FROM discord_accounts WHERE user_id = ? AND discord_user_id = ?',
            [$userId, $discordUser['id']]
        );

        if ($existing) {
            // Update existing account
            $this->accountRepository->update($existing['id'], [
                'discord_username' => $discordUser['username'],
                'discord_discriminator' => $discordUser['discriminator'] ?? '0',
                'discord_avatar' => $discordUser['avatar'],
                'discord_email' => $discordUser['email'] ?? null,
                'token_encrypted' => $this->encrypt($token),
                'is_active' => 1,
            ]);

            $account = $this->accountRepository->findById($existing['id']);
            unset($account['token_encrypted']);
            $account['avatar_url'] = $this->discordApi->getAvatarUrl($account['discord_user_id'], $account['discord_avatar']);

            return JsonResponse::success($account, 'Discord account updated');
        }

        // Create new account
        $account = $this->accountRepository->create([
            'user_id' => $userId,
            'discord_user_id' => $discordUser['id'],
            'discord_username' => $discordUser['username'],
            'discord_discriminator' => $discordUser['discriminator'] ?? '0',
            'discord_avatar' => $discordUser['avatar'],
            'discord_email' => $discordUser['email'] ?? null,
            'token_encrypted' => $this->encrypt($token),
        ]);

        unset($account['token_encrypted']);
        $account['avatar_url'] = $this->discordApi->getAvatarUrl($account['discord_user_id'], $account['discord_avatar']);

        return JsonResponse::created($account, 'Discord account added successfully');
    }

    public function deleteAccount(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $accountId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $account = $this->accountRepository->findByIdAndUser($accountId, $userId);
        if (!$account) {
            throw new NotFoundException('Discord account not found');
        }

        $this->accountRepository->delete($accountId);

        return JsonResponse::success(null, 'Discord account deleted');
    }

    public function syncAccount(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $accountId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $account = $this->accountRepository->findByIdAndUser($accountId, $userId);
        if (!$account) {
            throw new NotFoundException('Discord account not found');
        }

        $token = $this->decrypt($account['token_encrypted']);

        // Sync guilds (servers only - channels are lazy loaded per server)
        $guilds = $this->discordApi->getGuilds($token);
        $guildIds = [];

        foreach ($guilds as $guild) {
            $this->accountRepository->upsertServer($accountId, [
                'discord_guild_id' => $guild['id'],
                'name' => $guild['name'],
                'icon' => $guild['icon'],
                'owner_id' => $guild['owner'] ? $guild['id'] : null,
                'member_count' => $guild['approximate_member_count'] ?? null,
            ]);

            $guildIds[] = $guild['id'];
        }

        // Sync DM channels
        $dmChannels = $this->discordApi->getDMChannels($token);
        foreach ($dmChannels as $dm) {
            $recipientName = 'Unknown';
            $recipientAvatar = null;
            $recipientId = null;

            if (!empty($dm['recipients'])) {
                $recipient = $dm['recipients'][0];
                $recipientName = $recipient['username'] ?? 'Unknown';
                $recipientAvatar = $recipient['avatar'] ?? null;
                $recipientId = $recipient['id'] ?? null;
            }

            $this->accountRepository->upsertChannel($accountId, null, [
                'discord_channel_id' => $dm['id'],
                'name' => $dm['name'] ?? $recipientName,
                'type' => $dm['type'],
                'recipient_username' => $recipientName,
                'recipient_avatar' => $recipientAvatar,
                'recipient_id' => $recipientId,
                'last_message_id' => $dm['last_message_id'] ?? null,
            ]);
        }

        // Cleanup old servers
        $this->accountRepository->cleanupOldServers($accountId, $guildIds);

        $this->accountRepository->updateLastSync($accountId);

        return JsonResponse::success([
            'servers_synced' => count($guilds),
            'dm_channels_synced' => count($dmChannels),
        ], 'Account synced successfully');
    }

    /**
     * Sync channels for a specific server (lazy loading)
     */
    public function syncServerChannels(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $serverId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $server = $this->accountRepository->findServerById($serverId);
        if (!$server) {
            throw new NotFoundException('Server not found');
        }

        $account = $this->accountRepository->findByIdAndUser($server['account_id'], $userId);
        if (!$account) {
            throw new NotFoundException('Discord account not found');
        }

        $token = $this->decrypt($account['token_encrypted']);

        try {
            $channels = $this->discordApi->getGuildChannels($token, $server['discord_guild_id']);
            $channelCount = 0;

            foreach ($channels as $channel) {
                if (in_array($channel['type'], [0, 5, 15])) { // text, announcement, forum
                    $this->accountRepository->upsertChannel($account['id'], $serverId, [
                        'discord_channel_id' => $channel['id'],
                        'discord_guild_id' => $server['discord_guild_id'],
                        'name' => $channel['name'],
                        'type' => $channel['type'],
                        'parent_id' => $channel['parent_id'] ?? null,
                        'position' => $channel['position'] ?? 0,
                    ]);
                    $channelCount++;
                }
            }

            return JsonResponse::success([
                'channels_synced' => $channelCount,
            ], 'Server channels synced successfully');
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to sync channels: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // Servers & Channels
    // ========================================================================

    public function getServers(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $accountId = $request->getQueryParams()['account_id'] ?? null;

        if ($accountId) {
            $account = $this->accountRepository->findByIdAndUser($accountId, $userId);
            if (!$account) {
                throw new NotFoundException('Discord account not found');
            }
            $servers = $this->accountRepository->findServersByAccount($accountId);
        } else {
            // Get all servers from all accounts
            $accounts = $this->accountRepository->findAllByUser($userId);
            $servers = [];
            foreach ($accounts as $account) {
                $accountServers = $this->accountRepository->findServersByAccount($account['id']);
                foreach ($accountServers as &$server) {
                    $server['account_name'] = $account['discord_username'];
                }
                $servers = array_merge($servers, $accountServers);
            }
        }

        // Add icon URLs
        foreach ($servers as &$server) {
            $server['icon_url'] = $this->discordApi->getGuildIconUrl(
                $server['discord_guild_id'],
                $server['icon']
            );
        }

        return JsonResponse::success(['items' => $servers]);
    }

    public function getServerChannels(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $serverId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $server = $this->accountRepository->findServerById($serverId);
        if (!$server) {
            throw new NotFoundException('Server not found');
        }

        // Verify ownership
        $account = $this->accountRepository->findByIdAndUser($server['account_id'], $userId);
        if (!$account) {
            throw new NotFoundException('Server not found');
        }

        $channels = $this->accountRepository->findChannelsByServer($serverId);

        return JsonResponse::success([
            'server' => $server,
            'channels' => $channels,
        ]);
    }

    public function getDMChannels(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $accountId = $request->getQueryParams()['account_id'] ?? null;

        if ($accountId) {
            $account = $this->accountRepository->findByIdAndUser($accountId, $userId);
            if (!$account) {
                throw new NotFoundException('Discord account not found');
            }
            $channels = $this->accountRepository->findDMChannelsByAccount($accountId);
        } else {
            $accounts = $this->accountRepository->findAllByUser($userId);
            $channels = [];
            foreach ($accounts as $account) {
                $accountChannels = $this->accountRepository->findDMChannelsByAccount($account['id']);
                foreach ($accountChannels as &$channel) {
                    $channel['account_name'] = $account['discord_username'];
                }
                $channels = array_merge($channels, $accountChannels);
            }
        }

        // Add avatar URLs
        foreach ($channels as &$channel) {
            $channel['recipient_avatar_url'] = $this->discordApi->getAvatarUrl(
                null, // We don't have the user ID for DM recipients
                $channel['recipient_avatar']
            );
        }

        return JsonResponse::success(['items' => $channels]);
    }

    public function toggleServerFavorite(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $serverId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $server = $this->accountRepository->findServerById($serverId);
        if (!$server) {
            throw new NotFoundException('Server not found');
        }

        $account = $this->accountRepository->findByIdAndUser($server['account_id'], $userId);
        if (!$account) {
            throw new NotFoundException('Server not found');
        }

        $this->accountRepository->toggleServerFavorite($serverId);

        return JsonResponse::success(null, 'Favorite toggled');
    }

    // ========================================================================
    // Backups
    // ========================================================================

    public function getBackups(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        $backups = $this->backupRepository->findAllByUser($userId, $perPage, $offset);
        $total = $this->backupRepository->countByUser($userId);

        return JsonResponse::paginated($backups, $total, $page, $perPage);
    }

    public function createBackup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['account_id'])) {
            throw new ValidationException('Account ID is required');
        }

        $account = $this->accountRepository->findByIdAndUser($data['account_id'], $userId);
        if (!$account) {
            throw new NotFoundException('Discord account not found');
        }

        $token = $this->decrypt($account['token_encrypted']);

        // Determine backup target
        $targetName = 'Unknown';
        $serverId = null;
        $channelId = null;
        $discordGuildId = null;
        $discordChannelId = null;
        $type = $data['type'] ?? 'channel';

        if (!empty($data['channel_id'])) {
            $channel = $this->accountRepository->findChannelById($data['channel_id']);
            if (!$channel) {
                throw new NotFoundException('Channel not found');
            }

            $channelId = $channel['id'];
            $discordChannelId = $channel['discord_channel_id'];
            $targetName = $channel['name'];

            if ($channel['server_id']) {
                $server = $this->accountRepository->findServerById($channel['server_id']);
                $serverId = $server['id'];
                $discordGuildId = $server['discord_guild_id'];
                $targetName = $server['name'] . ' / #' . $channel['name'];
            }

            $type = in_array($channel['type'], ['dm', 'group_dm']) ? 'dm' : 'channel';
        } elseif (!empty($data['server_id'])) {
            $server = $this->accountRepository->findServerById($data['server_id']);
            if (!$server) {
                throw new NotFoundException('Server not found');
            }

            $serverId = $server['id'];
            $discordGuildId = $server['discord_guild_id'];
            $targetName = $server['name'];
            $type = 'full_server';
        } else {
            throw new ValidationException('Either channel_id or server_id is required');
        }

        $backup = $this->backupRepository->create([
            'account_id' => $account['id'],
            'server_id' => $serverId,
            'channel_id' => $channelId,
            'discord_guild_id' => $discordGuildId,
            'discord_channel_id' => $discordChannelId,
            'target_name' => $targetName,
            'type' => $type,
            'backup_mode' => $data['backup_mode'] ?? 'full',
            'include_media' => $data['include_media'] ?? true,
            'include_reactions' => $data['include_reactions'] ?? true,
            'include_threads' => $data['include_threads'] ?? false,
            'include_embeds' => $data['include_embeds'] ?? true,
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
        ]);

        // Spawn background process for backup (avoids PHP-FPM timeout)
        $encryptedToken = $account['token_encrypted'];
        $backupId = $backup['id'];
        $scriptPath = dirname(__DIR__, 4) . '/bin/process-discord-backup.php';
        $logPath = dirname(__DIR__, 4) . '/storage/logs/backup-' . $backupId . '.log';

        // Run backup processor in background with nohup to survive PHP-FPM shutdown
        $cmd = sprintf(
            'nohup php %s %s %s > %s 2>&1 &',
            escapeshellarg($scriptPath),
            escapeshellarg($backupId),
            escapeshellarg($encryptedToken),
            escapeshellarg($logPath)
        );
        exec($cmd);

        error_log("Started backup process: $cmd");

        // Return immediately with pending status
        return JsonResponse::created($backup, 'Backup started');
    }

    public function getBackup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $backupId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $backup = $this->backupRepository->findById($backupId);
        if (!$backup) {
            throw new NotFoundException('Backup not found');
        }

        $account = $this->accountRepository->findByIdAndUser($backup['account_id'], $userId);
        if (!$account) {
            throw new NotFoundException('Backup not found');
        }

        $backup['message_count'] = $this->backupRepository->countMessagesByBackup($backupId);

        return JsonResponse::success($backup);
    }

    public function deleteBackup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $backupId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $backup = $this->backupRepository->findById($backupId);
        if (!$backup) {
            throw new NotFoundException('Backup not found');
        }

        $account = $this->accountRepository->findByIdAndUser($backup['account_id'], $userId);
        if (!$account) {
            throw new NotFoundException('Backup not found');
        }

        // Delete files
        if ($backup['file_path'] && file_exists($backup['file_path'])) {
            unlink($backup['file_path']);
        }

        // Delete media files
        $mediaDir = $this->storagePath . '/discord/media/' . $backupId;
        if (is_dir($mediaDir)) {
            $this->deleteDirectory($mediaDir);
        }

        $this->backupRepository->delete($backupId);

        return JsonResponse::success(null, 'Backup deleted');
    }

    public function getBackupMessages(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $backupId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $queryParams = $request->getQueryParams();

        $backup = $this->backupRepository->findById($backupId);
        if (!$backup) {
            throw new NotFoundException('Backup not found');
        }

        $account = $this->accountRepository->findByIdAndUser($backup['account_id'], $userId);
        if (!$account) {
            throw new NotFoundException('Backup not found');
        }

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;
        $search = $queryParams['search'] ?? null;

        $messages = $this->backupRepository->findMessagesByBackup($backupId, $perPage, $offset, $search);
        $total = $this->backupRepository->countMessagesByBackup($backupId);

        // Decode raw_data for each message
        foreach ($messages as &$msg) {
            if ($msg['raw_data']) {
                $msg['raw_data'] = json_decode($msg['raw_data'], true);
            }
        }

        return JsonResponse::success([
            'items' => $messages,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'owner_discord_id' => $account['discord_user_id'],
        ]);
    }

    /**
     * Search across all backed up messages
     */
    public function searchMessages(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();

        $search = $queryParams['q'] ?? $queryParams['search'] ?? '';
        if (strlen($search) < 2) {
            return JsonResponse::success(['items' => [], 'total' => 0], 'Search query too short');
        }

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        $messages = $this->backupRepository->searchAllMessages($userId, $search, $perPage, $offset);
        $total = $this->backupRepository->countSearchResults($userId, $search);

        // Decode raw_data for each message
        foreach ($messages as &$msg) {
            if (!empty($msg['raw_data'])) {
                $msg['raw_data'] = json_decode($msg['raw_data'], true);
            }
        }

        return JsonResponse::paginated($messages, $total, $page, $perPage);
    }

    // ========================================================================
    // Media
    // ========================================================================

    public function getMedia(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        $media = $this->backupRepository->findAllMediaByUser($userId, $perPage, $offset);

        return JsonResponse::success(['items' => $media]);
    }

    public function serveMedia(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $mediaId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $media = $this->backupRepository->findMediaById($mediaId);
        if (!$media || $media['user_id'] !== $userId) {
            throw new NotFoundException('Media not found');
        }

        if (!file_exists($media['local_path'])) {
            throw new NotFoundException('Media file not found');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($media['local_path']);

        $response = $response
            ->withHeader('Content-Type', $mimeType)
            ->withHeader('Content-Disposition', 'inline; filename="' . $media['filename'] . '"')
            ->withHeader('Content-Length', (string) filesize($media['local_path']));

        $response->getBody()->write(file_get_contents($media['local_path']));

        return $response;
    }

    /**
     * Serve media file using signed URL (no JWT required)
     * This endpoint is public but protected by HMAC signature
     */
    public function serveSignedMedia(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $mediaId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $queryParams = $request->getQueryParams();

        $expires = (int) ($queryParams['expires'] ?? 0);
        $signature = $queryParams['signature'] ?? '';

        if (empty($signature) || empty($expires)) {
            throw new ValidationException('Missing signature or expiration');
        }

        if (!$this->validateMediaSignature($mediaId, $expires, $signature)) {
            throw new ValidationException('Invalid or expired signature');
        }

        $media = $this->backupRepository->findMediaById($mediaId);
        if (!$media) {
            throw new NotFoundException('Media not found');
        }

        if (!file_exists($media['local_path'])) {
            throw new NotFoundException('Media file not found');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($media['local_path']);

        // Add cache headers since signed URLs are time-limited
        $response = $response
            ->withHeader('Content-Type', $mimeType)
            ->withHeader('Content-Disposition', 'inline; filename="' . $media['filename'] . '"')
            ->withHeader('Content-Length', (string) filesize($media['local_path']))
            ->withHeader('Cache-Control', 'private, max-age=86400');

        $response->getBody()->write(file_get_contents($media['local_path']));

        return $response;
    }

    // ========================================================================
    // Message Deletion
    // ========================================================================

    public function searchOwnMessages(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();

        if (empty($queryParams['account_id']) || empty($queryParams['channel_id'])) {
            throw new ValidationException('account_id and channel_id are required');
        }

        $account = $this->accountRepository->findByIdAndUser($queryParams['account_id'], $userId);
        if (!$account) {
            throw new NotFoundException('Discord account not found');
        }

        $token = $this->decrypt($account['token_encrypted']);
        $channelId = $queryParams['channel_id'];

        // Search for own messages in the channel
        $messages = [];
        $beforeId = $queryParams['before'] ?? null;
        $limit = min(100, (int) ($queryParams['limit'] ?? 100));

        $options = ['limit' => $limit];
        if ($beforeId) {
            $options['before'] = $beforeId;
        }

        $channelMessages = $this->discordApi->getChannelMessages($token, $channelId, $options);

        foreach ($channelMessages as $msg) {
            if ($msg['author']['id'] === $account['discord_user_id']) {
                $messages[] = [
                    'id' => $msg['id'],
                    'content' => $msg['content'],
                    'timestamp' => $msg['timestamp'],
                    'has_attachments' => !empty($msg['attachments']),
                    'attachment_count' => count($msg['attachments'] ?? []),
                ];
            }
        }

        return JsonResponse::success([
            'items' => $messages,
            'has_more' => count($channelMessages) >= $limit,
            'last_id' => !empty($channelMessages) ? end($channelMessages)['id'] : null,
        ]);
    }

    public function createDeleteJob(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['account_id']) || empty($data['discord_channel_id'])) {
            throw new ValidationException('account_id and discord_channel_id are required');
        }

        $account = $this->accountRepository->findByIdAndUser($data['account_id'], $userId);
        if (!$account) {
            throw new NotFoundException('Discord account not found');
        }

        // Get channel info
        $token = $this->decrypt($account['token_encrypted']);
        $channelInfo = $this->discordApi->getChannel($token, $data['discord_channel_id']);

        $channelName = $channelInfo['name'] ?? 'DM';
        $serverName = null;

        if (!empty($channelInfo['guild_id'])) {
            $guildInfo = $this->discordApi->getGuild($token, $channelInfo['guild_id']);
            $serverName = $guildInfo['name'] ?? null;
        }

        $job = $this->backupRepository->createDeleteJob([
            'account_id' => $account['id'],
            'discord_channel_id' => $data['discord_channel_id'],
            'channel_name' => $channelName,
            'server_name' => $serverName,
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
            'keyword_filter' => $data['keyword_filter'] ?? null,
            'delete_attachments_only' => $data['delete_attachments_only'] ?? false,
        ]);

        // Start deletion process (in real implementation, this would be a background job)
        $this->processDeleteJob($job['id'], $token, $account['discord_user_id']);

        return JsonResponse::created($job, 'Delete job started');
    }

    public function getDeleteJobs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $jobs = $this->backupRepository->findDeleteJobsByUser($userId);

        return JsonResponse::success(['items' => $jobs]);
    }

    public function getDeleteJob(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $jobId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $job = $this->backupRepository->findDeleteJobById($jobId);
        if (!$job) {
            throw new NotFoundException('Delete job not found');
        }

        $account = $this->accountRepository->findByIdAndUser($job['account_id'], $userId);
        if (!$account) {
            throw new NotFoundException('Delete job not found');
        }

        return JsonResponse::success($job);
    }

    public function cancelDeleteJob(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $jobId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $job = $this->backupRepository->findDeleteJobById($jobId);
        if (!$job) {
            throw new NotFoundException('Delete job not found');
        }

        $account = $this->accountRepository->findByIdAndUser($job['account_id'], $userId);
        if (!$account) {
            throw new NotFoundException('Delete job not found');
        }

        if (!in_array($job['status'], ['pending', 'running', 'paused'])) {
            throw new ValidationException('Job cannot be cancelled');
        }

        $this->backupRepository->updateDeleteJobStatus($jobId, 'cancelled');

        return JsonResponse::success(null, 'Delete job cancelled');
    }

    // ========================================================================
    // Bot Management
    // ========================================================================

    public function getBots(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $bots = $this->botRepository->findAllBotsByUser($userId);

        foreach ($bots as &$bot) {
            $bot['avatar_url'] = $this->botApi->getAvatarUrl(
                $bot['bot_user_id'],
                $bot['bot_avatar']
            );
        }

        return JsonResponse::success(['items' => $bots]);
    }

    public function addBot(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['client_id']) || empty($data['bot_token'])) {
            throw new ValidationException('Client ID and Bot Token are required');
        }

        $clientId = trim($data['client_id']);
        $botToken = trim($data['bot_token']);
        $clientSecret = isset($data['client_secret']) ? trim($data['client_secret']) : null;

        // Validate bot token with Discord API
        $botUser = $this->botApi->validateToken($botToken);

        if (!$botUser) {
            throw new ValidationException('Invalid Bot Token. Please check your token and try again.');
        }

        // Check if bot already exists
        $existing = $this->botRepository->findBotByClientId($userId, $clientId);

        if ($existing) {
            // Update existing bot
            $this->botRepository->updateBot($existing['id'], [
                'bot_token_encrypted' => $this->encrypt($botToken),
                'client_secret_encrypted' => $clientSecret ? $this->encrypt($clientSecret) : null,
                'bot_user_id' => $botUser['id'],
                'bot_username' => $botUser['username'],
                'bot_discriminator' => $botUser['discriminator'] ?? '0',
                'bot_avatar' => $botUser['avatar'],
                'is_active' => 1,
            ]);

            // Auto-sync servers after update
            $this->syncBotServersInternal($existing['id'], $botToken);

            $bot = $this->botRepository->findBotById($existing['id']);
            unset($bot['bot_token_encrypted'], $bot['client_secret_encrypted']);
            $bot['avatar_url'] = $this->botApi->getAvatarUrl($bot['bot_user_id'], $bot['bot_avatar']);
            $bot['servers'] = $this->botRepository->findServersByBot($existing['id']);

            return JsonResponse::success($bot, 'Discord bot updated');
        }

        // Create new bot
        $bot = $this->botRepository->createBot([
            'user_id' => $userId,
            'client_id' => $clientId,
            'client_secret_encrypted' => $clientSecret ? $this->encrypt($clientSecret) : null,
            'bot_token_encrypted' => $this->encrypt($botToken),
            'bot_user_id' => $botUser['id'],
            'bot_username' => $botUser['username'],
            'bot_discriminator' => $botUser['discriminator'] ?? '0',
            'bot_avatar' => $botUser['avatar'],
        ]);

        // Auto-sync servers after creation
        $this->syncBotServersInternal($bot['id'], $botToken);

        unset($bot['bot_token_encrypted'], $bot['client_secret_encrypted']);
        $bot['avatar_url'] = $this->botApi->getAvatarUrl($bot['bot_user_id'], $bot['bot_avatar']);
        $bot['servers'] = $this->botRepository->findServersByBot($bot['id']);

        return JsonResponse::created($bot, 'Discord bot added successfully');
    }

    public function getBot(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $botId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $bot = $this->botRepository->findBotByIdAndUser($botId, $userId);
        if (!$bot) {
            throw new NotFoundException('Discord bot not found');
        }

        unset($bot['bot_token_encrypted'], $bot['client_secret_encrypted']);
        $bot['avatar_url'] = $this->botApi->getAvatarUrl($bot['bot_user_id'], $bot['bot_avatar']);
        $bot['servers'] = $this->botRepository->findServersByBot($botId);

        foreach ($bot['servers'] as &$server) {
            $server['icon_url'] = $this->botApi->getGuildIconUrl(
                $server['discord_guild_id'],
                $server['icon']
            );
        }

        return JsonResponse::success($bot);
    }

    public function deleteBot(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $botId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $bot = $this->botRepository->findBotByIdAndUser($botId, $userId);
        if (!$bot) {
            throw new NotFoundException('Discord bot not found');
        }

        $this->botRepository->deleteBot($botId);

        return JsonResponse::success(null, 'Discord bot deleted');
    }

    public function validateBot(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody() ?? [];

        if (empty($data['bot_token'])) {
            throw new ValidationException('Bot Token is required');
        }

        $botUser = $this->botApi->validateToken(trim($data['bot_token']));

        if (!$botUser) {
            throw new ValidationException('Invalid Bot Token');
        }

        return JsonResponse::success([
            'id' => $botUser['id'],
            'username' => $botUser['username'],
            'discriminator' => $botUser['discriminator'] ?? '0',
            'avatar' => $botUser['avatar'],
            'avatar_url' => $this->botApi->getAvatarUrl($botUser['id'], $botUser['avatar']),
        ], 'Bot token is valid');
    }

    public function syncBot(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $botId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $bot = $this->botRepository->findBotByIdAndUser($botId, $userId);
        if (!$bot) {
            throw new NotFoundException('Discord bot not found');
        }

        $token = $this->decrypt($bot['bot_token_encrypted']);

        // Get all guilds the bot is in
        $guilds = $this->botApi->getGuilds($token);
        $guildIds = [];

        foreach ($guilds as $guild) {
            $this->botRepository->upsertBotServer($botId, [
                'discord_guild_id' => $guild['id'],
                'name' => $guild['name'],
                'icon' => $guild['icon'],
                'owner_id' => $guild['owner'] ? $guild['id'] : null,
                'permissions' => $guild['permissions'] ?? 0,
            ]);

            $guildIds[] = $guild['id'];
        }

        // Cleanup old servers that bot is no longer in
        $this->botRepository->cleanupOldBotServers($botId, $guildIds);

        $this->botRepository->updateBotLastSync($botId);

        return JsonResponse::success([
            'servers_synced' => count($guilds),
        ], 'Bot synced successfully');
    }

    /**
     * Internal method to sync bot servers (used by addBot for auto-sync)
     */
    private function syncBotServersInternal(string $botId, string $token): int
    {
        try {
            $guilds = $this->botApi->getGuilds($token);
            $guildIds = [];

            foreach ($guilds as $guild) {
                $this->botRepository->upsertBotServer($botId, [
                    'discord_guild_id' => $guild['id'],
                    'name' => $guild['name'],
                    'icon' => $guild['icon'],
                    'owner_id' => $guild['owner'] ? $guild['id'] : null,
                    'permissions' => $guild['permissions'] ?? 0,
                ]);
                $guildIds[] = $guild['id'];
            }

            $this->botRepository->cleanupOldBotServers($botId, $guildIds);
            $this->botRepository->updateBotLastSync($botId);

            return count($guilds);
        } catch (\Exception $e) {
            // Log error but don't fail the bot creation
            error_log("Failed to auto-sync bot servers: " . $e->getMessage());
            return 0;
        }
    }

    public function getBotInviteUrl(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $botId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $queryParams = $request->getQueryParams();

        $bot = $this->botRepository->findBotByIdAndUser($botId, $userId);
        if (!$bot) {
            throw new NotFoundException('Discord bot not found');
        }

        // Default permissions for backup functionality
        $defaultPermissions = [
            'VIEW_CHANNEL',
            'READ_MESSAGE_HISTORY',
        ];

        // Extended permissions if requested
        if (!empty($queryParams['extended'])) {
            $defaultPermissions = array_merge($defaultPermissions, [
                'MANAGE_WEBHOOKS',
                'MANAGE_ROLES',
                'MANAGE_CHANNELS',
                'MANAGE_EMOJIS',
            ]);
        }

        $guildId = $queryParams['guild_id'] ?? null;
        $inviteUrl = $this->botApi->generateInviteUrl($bot['client_id'], $defaultPermissions, $guildId);

        return JsonResponse::success([
            'invite_url' => $inviteUrl,
            'permissions' => $defaultPermissions,
        ]);
    }

    // ========================================================================
    // Bot Server Management
    // ========================================================================

    public function getBotServers(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $botId = RouteContext::fromRequest($request)->getRoute()->getArgument('botId');

        $bot = $this->botRepository->findBotByIdAndUser($botId, $userId);
        if (!$bot) {
            throw new NotFoundException('Discord bot not found');
        }

        $servers = $this->botRepository->findServersByBot($botId);

        foreach ($servers as &$server) {
            $server['icon_url'] = $this->botApi->getGuildIconUrl(
                $server['discord_guild_id'],
                $server['icon']
            );
            $server['permissions_list'] = $this->botApi->parsePermissions((int) $server['permissions']);
        }

        return JsonResponse::success(['items' => $servers]);
    }

    public function getBotServer(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $botId = RouteContext::fromRequest($request)->getRoute()->getArgument('botId');
        $serverId = RouteContext::fromRequest($request)->getRoute()->getArgument('serverId');

        $bot = $this->botRepository->findBotByIdAndUser($botId, $userId);
        if (!$bot) {
            throw new NotFoundException('Discord bot not found');
        }

        $server = $this->botRepository->findBotServerById($serverId);
        if (!$server || $server['bot_id'] !== $botId) {
            throw new NotFoundException('Server not found');
        }

        $server['icon_url'] = $this->botApi->getGuildIconUrl(
            $server['discord_guild_id'],
            $server['icon']
        );
        $server['permissions_list'] = $this->botApi->parsePermissions((int) $server['permissions']);
        $server['channels'] = $this->botRepository->findChannelsByBotServer($serverId);

        return JsonResponse::success($server);
    }

    public function syncBotServerChannels(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $botId = RouteContext::fromRequest($request)->getRoute()->getArgument('botId');
        $serverId = RouteContext::fromRequest($request)->getRoute()->getArgument('serverId');

        $bot = $this->botRepository->findBotByIdAndUser($botId, $userId);
        if (!$bot) {
            throw new NotFoundException('Discord bot not found');
        }

        $server = $this->botRepository->findBotServerById($serverId);
        if (!$server || $server['bot_id'] !== $botId) {
            throw new NotFoundException('Server not found');
        }

        $token = $this->decrypt($bot['bot_token_encrypted']);

        try {
            $channels = $this->botApi->getGuildChannels($token, $server['discord_guild_id']);
            $channelCount = 0;

            // Clear old channels first
            $this->botRepository->deleteChannelsByBotServer($serverId);

            foreach ($channels as $channel) {
                // Only sync text-based channels
                if (in_array($channel['type'], [0, 5, 15, 10, 11, 12])) { // text, announcement, forum, threads
                    $this->botRepository->upsertBotChannel($botId, $serverId, [
                        'discord_channel_id' => $channel['id'],
                        'name' => $channel['name'],
                        'type' => $channel['type'],
                        'parent_id' => $channel['parent_id'] ?? null,
                        'position' => $channel['position'] ?? 0,
                        'topic' => $channel['topic'] ?? null,
                        'permission_overwrites' => $channel['permission_overwrites'] ?? null,
                    ]);
                    $channelCount++;
                }
            }

            // Also get guild details with member count
            $guildDetails = $this->botApi->getGuild($token, $server['discord_guild_id']);
            $this->botRepository->upsertBotServer($botId, [
                'discord_guild_id' => $server['discord_guild_id'],
                'name' => $guildDetails['name'],
                'icon' => $guildDetails['icon'],
                'owner_id' => $guildDetails['owner_id'] ?? null,
                'member_count' => $guildDetails['approximate_member_count'] ?? null,
                'permissions' => $server['permissions'],
            ]);

            return JsonResponse::success([
                'channels_synced' => $channelCount,
            ], 'Server channels synced successfully');
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to sync channels: ' . $e->getMessage());
        }
    }

    public function toggleBotServerFavorite(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $botId = RouteContext::fromRequest($request)->getRoute()->getArgument('botId');
        $serverId = RouteContext::fromRequest($request)->getRoute()->getArgument('serverId');

        $bot = $this->botRepository->findBotByIdAndUser($botId, $userId);
        if (!$bot) {
            throw new NotFoundException('Discord bot not found');
        }

        $server = $this->botRepository->findBotServerById($serverId);
        if (!$server || $server['bot_id'] !== $botId) {
            throw new NotFoundException('Server not found');
        }

        $this->botRepository->toggleBotServerFavorite($serverId);

        return JsonResponse::success(null, 'Favorite toggled');
    }

    // ========================================================================
    // Bot Backups
    // ========================================================================

    public function createBotBackup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $botId = RouteContext::fromRequest($request)->getRoute()->getArgument('botId');
        $data = $request->getParsedBody() ?? [];

        $bot = $this->botRepository->findBotByIdAndUser($botId, $userId);
        if (!$bot) {
            throw new NotFoundException('Discord bot not found');
        }

        if (empty($data['server_id']) && empty($data['discord_guild_id'])) {
            throw new ValidationException('Server ID or Discord Guild ID is required');
        }

        $token = $this->decrypt($bot['bot_token_encrypted']);

        // Get server info
        $server = null;
        if (!empty($data['server_id'])) {
            $server = $this->botRepository->findBotServerById($data['server_id']);
            if (!$server || $server['bot_id'] !== $botId) {
                throw new NotFoundException('Server not found');
            }
        }

        $discordGuildId = $server ? $server['discord_guild_id'] : $data['discord_guild_id'];
        $targetName = $server ? $server['name'] : 'Unknown Server';
        $type = $data['type'] ?? 'full_server';

        // Determine channel for channel backup
        $discordChannelId = null;
        $channelId = null;

        if ($type === 'channel' && !empty($data['channel_id'])) {
            $channels = $this->botRepository->findChannelsByBotServer($data['server_id']);
            foreach ($channels as $ch) {
                if ($ch['id'] === $data['channel_id']) {
                    $discordChannelId = $ch['discord_channel_id'];
                    $targetName = $server['name'] . ' / #' . $ch['name'];
                    $channelId = $ch['id'];
                    break;
                }
            }
        }

        $backup = $this->backupRepository->create([
            'account_id' => null, // No user account for bot backups
            'bot_id' => $botId,
            'server_id' => null,      // Not used for bot backups (references discord_servers)
            'bot_server_id' => $server ? $server['id'] : null,  // References discord_bot_servers
            'channel_id' => null,     // Not used for bot backups (references discord_channels)
            'bot_channel_id' => $channelId,  // References discord_bot_channels
            'discord_guild_id' => $discordGuildId,
            'discord_channel_id' => $discordChannelId,
            'target_name' => $targetName,
            'type' => $type,
            'source_type' => 'bot',
            'backup_mode' => $data['backup_mode'] ?? 'full',
            'include_media' => $data['include_media'] ?? true,
            'include_reactions' => $data['include_reactions'] ?? true,
            'include_threads' => $data['include_threads'] ?? false,
            'include_embeds' => $data['include_embeds'] ?? true,
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
        ]);

        // Spawn background process for backup
        $encryptedToken = $bot['bot_token_encrypted'];
        $backupId = $backup['id'];
        $scriptPath = dirname(__DIR__, 4) . '/bin/process-discord-backup.php';
        $logPath = dirname(__DIR__, 4) . '/storage/logs/backup-' . $backupId . '.log';

        // Run backup processor in background
        $cmd = sprintf(
            'nohup php %s %s %s bot > %s 2>&1 &',
            escapeshellarg($scriptPath),
            escapeshellarg($backupId),
            escapeshellarg($encryptedToken),
            escapeshellarg($logPath)
        );
        exec($cmd);

        return JsonResponse::created($backup, 'Bot backup started');
    }

    public function getBotBackups(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $botId = RouteContext::fromRequest($request)->getRoute()->getArgument('botId');
        $queryParams = $request->getQueryParams();

        $bot = $this->botRepository->findBotByIdAndUser($botId, $userId);
        if (!$bot) {
            throw new NotFoundException('Discord bot not found');
        }

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        $backups = $this->backupRepository->findByBotId($botId, $perPage, $offset);
        $total = $this->backupRepository->countByBotId($botId);

        return JsonResponse::paginated($backups, $total, $page, $perPage);
    }

    // ========================================================================
    // Links
    // ========================================================================

    public function getLinks(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        // Optional filter by backup ID
        $backupId = $queryParams['backup_id'] ?? null;

        if ($backupId) {
            // Verify backup belongs to user
            $backup = $this->backupRepository->findById($backupId);
            if (!$backup) {
                throw new NotFoundException('Backup not found');
            }
            $account = $this->accountRepository->findByIdAndUser($backup['account_id'], $userId);
            if (!$account) {
                throw new NotFoundException('Backup not found');
            }
            $links = $this->backupRepository->getLinksFromBackup($backupId, $perPage, $offset);
        } else {
            $links = $this->backupRepository->getAllLinksForUser($userId, $perPage, $offset);
        }

        return JsonResponse::success(['items' => $links]);
    }

    public function getChannelMedia(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $channelId = RouteContext::fromRequest($request)->getRoute()->getArgument('channelId');
        $queryParams = $request->getQueryParams();

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(200, max(1, (int) ($queryParams['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        $media = $this->backupRepository->findMediaByChannel($channelId, $userId, $perPage, $offset);
        $total = $this->backupRepository->countMediaByChannel($channelId, $userId);

        // Add signed URLs to each media item (valid for 24 hours)
        foreach ($media as &$item) {
            $item['signed_url'] = $this->generateSignedMediaUrl($item['id']);
        }

        return JsonResponse::success([
            'items' => $media,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'has_more' => ($offset + count($media)) < $total,
        ]);
    }

    public function getChannelLinks(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $channelId = RouteContext::fromRequest($request)->getRoute()->getArgument('channelId');
        $queryParams = $request->getQueryParams();

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(200, max(1, (int) ($queryParams['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        $links = $this->backupRepository->getLinksFromChannel($channelId, $userId, $perPage, $offset);
        $total = $this->backupRepository->countLinksInChannel($channelId, $userId);

        return JsonResponse::success([
            'items' => $links,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'has_more' => ($offset + count($links)) < $total,
        ]);
    }

    // ========================================================================
    // Private Methods
    // ========================================================================

    private function processBackup(string $backupId, string $token): void
    {
        $backup = $this->backupRepository->findById($backupId);
        if (!$backup || $backup['status'] !== 'pending') {
            return;
        }

        $this->backupRepository->updateStatus($backupId, 'running');

        try {
            $discordChannelId = $backup['discord_channel_id'];
            if (!$discordChannelId) {
                throw new \RuntimeException('No channel specified for backup');
            }

            // Parse date filters
            $dateFrom = $backup['date_from'] ? strtotime($backup['date_from']) : null;
            $dateTo = $backup['date_to'] ? strtotime($backup['date_to']) : null;

            $messageCount = 0;
            $mediaCount = 0;
            $mediaSize = 0;
            $skippedByDate = 0;
            $backupMode = $backup['backup_mode'] ?? 'full';

            // Create media directory if needed (for full with media or media_only mode)
            $mediaDir = $this->storagePath . '/discord/media/' . $backupId;
            $needsMediaDir = $backupMode === 'media_only' || ($backupMode === 'full' && $backup['include_media']);

            if ($needsMediaDir) {
                if (!is_dir($this->storagePath)) {
                    if (!@mkdir($this->storagePath, 0755, true)) {
                        throw new \RuntimeException('Cannot create storage directory: ' . $this->storagePath);
                    }
                }
                if (!is_dir($mediaDir)) {
                    if (!@mkdir($mediaDir, 0755, true)) {
                        throw new \RuntimeException('Cannot create media directory: ' . $mediaDir);
                    }
                }
            }
            $linksCount = 0;

            $this->backupRepository->updateProgress($backupId, 0, 0, "Fetching messages from Discord...");

            // Get messages
            foreach ($this->discordApi->getAllChannelMessages($token, $discordChannelId) as $message) {
                // Apply date filter
                $messageTime = strtotime($message['timestamp']);

                if ($dateFrom && $messageTime < $dateFrom) {
                    $skippedByDate++;
                    continue;
                }

                if ($dateTo && $messageTime > $dateTo) {
                    $skippedByDate++;
                    continue;
                }

                // Store message only for full backup mode
                if ($backupMode === 'full') {
                    $this->backupRepository->insertMessage($backupId, $message);
                }
                $messageCount++;

                // Update progress every 50 messages
                if ($messageCount % 50 === 0) {
                    $this->backupRepository->updateProgress($backupId, $messageCount, $messageCount, "Processed {$messageCount} messages...");
                }

                // Download media for full or media_only mode
                if (($backupMode === 'full' && $backup['include_media']) || $backupMode === 'media_only') {
                    if (!empty($message['attachments'])) {
                        foreach ($message['attachments'] as $attachment) {
                            $filename = $attachment['id'] . '_' . $attachment['filename'];
                            $localPath = $mediaDir . '/' . $filename;

                            if ($this->discordApi->downloadAttachment($attachment['url'], $localPath)) {
                                $this->backupRepository->insertMedia($backupId, [
                                    'message_id' => $message['id'],
                                    'attachment_id' => $attachment['id'],
                                    'url' => $attachment['url'],
                                    'local_path' => $localPath,
                                    'filename' => $attachment['filename'],
                                    'size' => $attachment['size'] ?? null,
                                    'content_type' => $attachment['content_type'] ?? null,
                                    'width' => $attachment['width'] ?? null,
                                    'height' => $attachment['height'] ?? null,
                                    'spoiler' => str_starts_with($attachment['filename'], 'SPOILER_'),
                                ]);

                                $mediaCount++;
                                $mediaSize += $attachment['size'] ?? 0;
                            }
                        }
                    }
                }

                // For links_only mode, we still need to store minimal message data for link extraction
                if ($backupMode === 'links_only' && !empty($message['content'])) {
                    // Check if message contains links
                    if (preg_match('/https?:\/\/[^\s]+/i', $message['content'])) {
                        // Store only messages with links
                        $this->backupRepository->insertMessage($backupId, $message);
                        $linksCount++;
                    }
                }
            }

            $this->backupRepository->updateResults($backupId, [
                'messages_total' => $messageCount,
                'messages_processed' => $messageCount,
                'media_count' => $mediaCount,
                'media_size' => $mediaSize,
            ]);

            $this->backupRepository->updateStatus($backupId, 'completed');

        } catch (\Exception $e) {
            error_log('Discord backup failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->backupRepository->updateStatus($backupId, 'failed', $e->getMessage());
        }
    }

    private function processDeleteJob(string $jobId, string $token, string $ownUserId): void
    {
        $job = $this->backupRepository->findDeleteJobById($jobId);
        if (!$job || $job['status'] !== 'pending') {
            return;
        }

        $this->backupRepository->updateDeleteJobStatus($jobId, 'running');

        try {
            $channelId = $job['discord_channel_id'];
            $totalMessages = 0;
            $deletedMessages = 0;
            $failedMessages = 0;

            // Get all own messages in the channel
            foreach ($this->discordApi->getAllChannelMessages($token, $channelId) as $message) {
                // Check if it's our own message
                if ($message['author']['id'] !== $ownUserId) {
                    continue;
                }

                // Apply filters
                if ($job['date_from']) {
                    $msgTime = strtotime($message['timestamp']);
                    if ($msgTime < strtotime($job['date_from'])) {
                        continue;
                    }
                }

                if ($job['date_to']) {
                    $msgTime = strtotime($message['timestamp']);
                    if ($msgTime > strtotime($job['date_to'])) {
                        continue;
                    }
                }

                if ($job['keyword_filter']) {
                    if (stripos($message['content'] ?? '', $job['keyword_filter']) === false) {
                        continue;
                    }
                }

                if ($job['delete_attachments_only'] && empty($message['attachments'])) {
                    continue;
                }

                $totalMessages++;

                // Delete the message
                if ($this->discordApi->deleteMessage($token, $channelId, $message['id'])) {
                    $deletedMessages++;
                } else {
                    $failedMessages++;
                }

                $this->backupRepository->updateDeleteJobProgress($jobId, $totalMessages, $deletedMessages, $failedMessages, $message['id']);

                // Rate limit: Discord allows ~5 deletes per second
                usleep(250000); // 250ms
            }

            $this->backupRepository->updateDeleteJobStatus($jobId, 'completed');

        } catch (\Exception $e) {
            $this->backupRepository->updateDeleteJobStatus($jobId, 'failed', $e->getMessage());
        }
    }

    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        return rmdir($dir);
    }

    /**
     * Generate a signed URL for media access (no JWT required)
     * URLs are valid for 24 hours by default
     */
    private function generateSignedMediaUrl(string $mediaId, int $validitySeconds = 86400): string
    {
        $expires = time() + $validitySeconds;
        $signature = $this->generateMediaSignature($mediaId, $expires);

        $baseUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
        return sprintf(
            '%s/api/v1/discord/media/%s/signed?expires=%d&signature=%s',
            $baseUrl,
            urlencode($mediaId),
            $expires,
            urlencode($signature)
        );
    }

    /**
     * Generate HMAC signature for media access
     */
    private function generateMediaSignature(string $mediaId, int $expires): string
    {
        $data = $mediaId . ':' . $expires;
        return hash_hmac('sha256', $data, $this->encryptionKey);
    }

    /**
     * Validate media signature
     */
    private function validateMediaSignature(string $mediaId, int $expires, string $signature): bool
    {
        // Check if signature has expired
        if ($expires < time()) {
            return false;
        }

        $expectedSignature = $this->generateMediaSignature($mediaId, $expires);
        return hash_equals($expectedSignature, $signature);
    }

    private function encrypt(string $data): string
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryptionKey, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    private function decrypt(string $data): string
    {
        $decoded = base64_decode($data);
        if ($decoded === false || strlen($decoded) < 17) {
            throw new \RuntimeException('Invalid encrypted data');
        }

        $iv = substr($decoded, 0, 16);
        $encrypted = substr($decoded, 16);

        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed - check APP_KEY');
        }

        return $decrypted;
    }
}
