<?php

declare(strict_types=1);

namespace App\Modules\Discord\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Modules\Discord\Services\DiscordApiService;
use App\Modules\Discord\Repositories\DiscordAccountRepository;
use App\Modules\Discord\Repositories\DiscordBackupRepository;
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
        private readonly DiscordAccountRepository $accountRepository,
        private readonly DiscordBackupRepository $backupRepository
    ) {
        $this->encryptionKey = $_ENV['APP_KEY'] ?? 'default-key-change-me';
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
            'include_media' => $data['include_media'] ?? true,
            'include_reactions' => $data['include_reactions'] ?? true,
            'include_threads' => $data['include_threads'] ?? false,
            'include_embeds' => $data['include_embeds'] ?? true,
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
        ]);

        // Start backup process (in real implementation, this would be a background job)
        $this->processBackup($backup['id'], $token);

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

            $messageCount = 0;
            $mediaCount = 0;
            $mediaSize = 0;

            $mediaDir = $this->storagePath . '/discord/media/' . $backupId;
            if (!is_dir($mediaDir)) {
                mkdir($mediaDir, 0755, true);
            }

            // Get messages
            foreach ($this->discordApi->getAllChannelMessages($token, $discordChannelId) as $message) {
                // Store message
                $this->backupRepository->insertMessage($backupId, $message);
                $messageCount++;

                // Update progress every 100 messages
                if ($messageCount % 100 === 0) {
                    $this->backupRepository->updateProgress($backupId, $messageCount, $messageCount, "Processing messages...");
                }

                // Download media if enabled
                if ($backup['include_media'] && !empty($message['attachments'])) {
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

            $this->backupRepository->updateResults($backupId, [
                'messages_total' => $messageCount,
                'messages_processed' => $messageCount,
                'media_count' => $mediaCount,
                'media_size' => $mediaSize,
            ]);

            $this->backupRepository->updateStatus($backupId, 'completed');

        } catch (\Exception $e) {
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

    private function encrypt(string $data): string
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    private function decrypt(string $data): string
    {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
    }
}
