<?php

declare(strict_types=1);

namespace App\Modules\GitRepository\Controllers;

use App\Core\Http\JsonResponse;
use App\Modules\GitRepository\Services\GitProviderService;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class GitRepositoryController
{
    public function __construct(
        private readonly Connection $db,
        private readonly GitProviderService $gitProvider
    ) {}

    private function getRouteArg(ServerRequestInterface $request, string $name): ?string
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        return $route ? $route->getArgument($name) : null;
    }

    /**
     * List all repositories
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $sql = 'SELECT r.*, f.name as folder_name, f.color as folder_color, p.name as project_name
                FROM git_repositories r
                LEFT JOIN git_repository_folders f ON r.folder_id = f.id
                LEFT JOIN projects p ON r.project_id = p.id
                WHERE r.user_id = ?';
        $sqlParams = [$userId];

        if (!empty($params['project_id'])) {
            $sql .= ' AND r.project_id = ?';
            $sqlParams[] = $params['project_id'];
        }

        if (!empty($params['provider'])) {
            $sql .= ' AND r.provider = ?';
            $sqlParams[] = $params['provider'];
        }

        if (!empty($params['folder_id'])) {
            $sql .= ' AND r.folder_id = ?';
            $sqlParams[] = $params['folder_id'];
        }

        $sql .= ' ORDER BY r.name ASC';

        $repositories = $this->db->fetchAllAssociative($sql, $sqlParams);

        // Cast booleans
        foreach ($repositories as &$repo) {
            $repo['is_private'] = (bool) $repo['is_private'];
            $repo['is_active'] = (bool) $repo['is_active'];
            $repo['auto_sync'] = (bool) $repo['auto_sync'];
            $repo['notify_on_new_pr'] = (bool) $repo['notify_on_new_pr'];
            $repo['notify_on_new_issue'] = (bool) $repo['notify_on_new_issue'];
            $repo['notify_on_merge'] = (bool) $repo['notify_on_merge'];
            $repo['notify_on_release'] = (bool) $repo['notify_on_release'];
        }

        // Get folders
        $folders = $this->db->fetchAllAssociative(
            'SELECT * FROM git_repository_folders WHERE user_id = ? ORDER BY sort_order, name',
            [$userId]
        );

        foreach ($folders as &$folder) {
            $folder['is_collapsed'] = (bool) $folder['is_collapsed'];
        }

        return JsonResponse::success([
            'items' => $repositories,
            'folders' => $folders,
        ]);
    }

    /**
     * Get a single repository with details
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $repository = $this->db->fetchAssociative(
            'SELECT * FROM git_repositories WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$repository) {
            return JsonResponse::notFound('Repository not found');
        }

        // Get recent PRs
        $pullRequests = $this->db->fetchAllAssociative(
            'SELECT * FROM git_pull_requests WHERE repository_id = ? ORDER BY external_updated_at DESC LIMIT 10',
            [$id]
        );

        // Get recent issues
        $issues = $this->db->fetchAllAssociative(
            'SELECT * FROM git_issues WHERE repository_id = ? ORDER BY external_updated_at DESC LIMIT 10',
            [$id]
        );

        // Get recent commits
        $commits = $this->db->fetchAllAssociative(
            'SELECT * FROM git_commits WHERE repository_id = ? ORDER BY committed_at DESC LIMIT 20',
            [$id]
        );

        // Get releases
        $releases = $this->db->fetchAllAssociative(
            'SELECT * FROM git_releases WHERE repository_id = ? ORDER BY published_at DESC LIMIT 5',
            [$id]
        );

        // Get recent activity
        $activity = $this->db->fetchAllAssociative(
            'SELECT * FROM git_activity WHERE repository_id = ? ORDER BY occurred_at DESC LIMIT 20',
            [$id]
        );

        // Decode JSON fields
        foreach ($pullRequests as &$pr) {
            $pr['labels'] = json_decode($pr['labels'] ?? '[]', true);
            $pr['reviewers'] = json_decode($pr['reviewers'] ?? '[]', true);
            $pr['is_mergeable'] = $pr['is_mergeable'] === null ? null : (bool) $pr['is_mergeable'];
            $pr['is_draft'] = (bool) $pr['is_draft'];
        }

        foreach ($issues as &$issue) {
            $issue['labels'] = json_decode($issue['labels'] ?? '[]', true);
            $issue['assignees'] = json_decode($issue['assignees'] ?? '[]', true);
            $issue['is_locked'] = (bool) $issue['is_locked'];
        }

        foreach ($releases as &$release) {
            $release['assets'] = json_decode($release['assets'] ?? '[]', true);
            $release['is_prerelease'] = (bool) $release['is_prerelease'];
            $release['is_draft'] = (bool) $release['is_draft'];
        }

        foreach ($activity as &$act) {
            $act['event_data'] = json_decode($act['event_data'] ?? '{}', true);
        }

        $repository['is_private'] = (bool) $repository['is_private'];
        $repository['is_active'] = (bool) $repository['is_active'];
        $repository['auto_sync'] = (bool) $repository['auto_sync'];

        return JsonResponse::success([
            'repository' => $repository,
            'pull_requests' => $pullRequests,
            'issues' => $issues,
            'commits' => $commits,
            'releases' => $releases,
            'activity' => $activity,
        ]);
    }

    /**
     * Create a new repository
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['name'])) {
            return JsonResponse::error('Name is required', 400);
        }

        if (empty($data['repo_url'])) {
            return JsonResponse::error('Repository URL is required', 400);
        }

        $id = Uuid::uuid4()->toString();
        $provider = $data['provider'] ?? $this->detectProvider($data['repo_url']);

        $this->db->insert('git_repositories', [
            'id' => $id,
            'user_id' => $userId,
            'project_id' => $data['project_id'] ?? null,
            'folder_id' => $data['folder_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'provider' => $provider,
            'repo_url' => $data['repo_url'],
            'api_url' => $data['api_url'] ?? null,
            'api_token' => $data['api_token'] ?? null,
            'default_branch' => $data['default_branch'] ?? 'main',
            'is_private' => $data['is_private'] ?? 0,
            'is_active' => 1,
            'auto_sync' => $data['auto_sync'] ?? 1,
            'sync_interval' => $data['sync_interval'] ?? 300,
            'notify_on_new_pr' => $data['notify_on_new_pr'] ?? 1,
            'notify_on_new_issue' => $data['notify_on_new_issue'] ?? 1,
            'notify_on_merge' => $data['notify_on_merge'] ?? 1,
            'notify_on_release' => $data['notify_on_release'] ?? 0,
        ]);

        $repository = $this->db->fetchAssociative(
            'SELECT * FROM git_repositories WHERE id = ?',
            [$id]
        );

        return JsonResponse::created($repository, 'Repository added');
    }

    /**
     * Update a repository
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');
        $data = $request->getParsedBody() ?? [];

        $exists = $this->db->fetchOne(
            'SELECT 1 FROM git_repositories WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$exists) {
            return JsonResponse::notFound('Repository not found');
        }

        $updateFields = [
            'name', 'description', 'provider', 'repo_url', 'api_url', 'api_token',
            'default_branch', 'is_private', 'is_active', 'auto_sync', 'sync_interval',
            'notify_on_new_pr', 'notify_on_new_issue', 'notify_on_merge', 'notify_on_release',
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
                'UPDATE git_repositories SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE id = ?',
                $params
            );
        }

        return JsonResponse::success(null, 'Repository updated');
    }

    /**
     * Delete a repository
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $deleted = $this->db->delete('git_repositories', [
            'id' => $id,
            'user_id' => $userId,
        ]);

        if (!$deleted) {
            return JsonResponse::notFound('Repository not found');
        }

        return JsonResponse::success(null, 'Repository deleted');
    }

    /**
     * Sync repository data from provider
     */
    public function sync(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $repository = $this->db->fetchAssociative(
            'SELECT * FROM git_repositories WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$repository) {
            return JsonResponse::notFound('Repository not found');
        }

        try {
            // Fetch repo info
            $repoInfo = $this->gitProvider->fetchRepositoryInfo($repository);
            if ($repoInfo) {
                $this->db->update('git_repositories', [
                    'stars_count' => $repoInfo['stars_count'],
                    'forks_count' => $repoInfo['forks_count'],
                    'open_issues_count' => $repoInfo['open_issues_count'],
                    'watchers_count' => $repoInfo['watchers_count'],
                    'default_branch' => $repoInfo['default_branch'],
                    'is_private' => $repoInfo['is_private'],
                    'last_sync_at' => date('Y-m-d H:i:s'),
                    'sync_error' => null,
                ], ['id' => $id]);
            }

            // Sync PRs
            $prs = $this->gitProvider->fetchPullRequests($repository, 'all');
            $this->syncPullRequests($id, $prs);

            // Sync issues
            $issues = $this->gitProvider->fetchIssues($repository, 'all');
            $this->syncIssues($id, $issues);

            // Sync commits
            $commits = $this->gitProvider->fetchCommits($repository, 50);
            $this->syncCommits($id, $commits);

            // Sync releases
            $releases = $this->gitProvider->fetchReleases($repository, 20);
            $this->syncReleases($id, $releases);

            // Update PR count
            $openPrCount = $this->db->fetchOne(
                "SELECT COUNT(*) FROM git_pull_requests WHERE repository_id = ? AND state = 'open'",
                [$id]
            );
            $this->db->update('git_repositories', ['open_prs_count' => $openPrCount], ['id' => $id]);

            return JsonResponse::success(['synced_at' => date('Y-m-d H:i:s')], 'Repository synced');

        } catch (\Exception $e) {
            $this->db->update('git_repositories', [
                'sync_error' => $e->getMessage(),
            ], ['id' => $id]);

            return JsonResponse::error('Sync failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get repository statistics
     */
    public function stats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $stats = [
            'total_repositories' => $this->db->fetchOne(
                'SELECT COUNT(*) FROM git_repositories WHERE user_id = ?',
                [$userId]
            ),
            'total_open_prs' => $this->db->fetchOne(
                "SELECT COUNT(*) FROM git_pull_requests pr
                 JOIN git_repositories r ON pr.repository_id = r.id
                 WHERE r.user_id = ? AND pr.state = 'open'",
                [$userId]
            ),
            'total_open_issues' => $this->db->fetchOne(
                "SELECT COUNT(*) FROM git_issues i
                 JOIN git_repositories r ON i.repository_id = r.id
                 WHERE r.user_id = ? AND i.state = 'open'",
                [$userId]
            ),
            'by_provider' => $this->db->fetchAllAssociative(
                'SELECT provider, COUNT(*) as count FROM git_repositories WHERE user_id = ? GROUP BY provider',
                [$userId]
            ),
            'recent_activity' => $this->db->fetchAllAssociative(
                'SELECT a.*, r.name as repository_name
                 FROM git_activity a
                 JOIN git_repositories r ON a.repository_id = r.id
                 WHERE r.user_id = ?
                 ORDER BY a.occurred_at DESC
                 LIMIT 20',
                [$userId]
            ),
        ];

        // Decode event_data
        foreach ($stats['recent_activity'] as &$activity) {
            $activity['event_data'] = json_decode($activity['event_data'] ?? '{}', true);
        }

        return JsonResponse::success($stats);
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

        $this->db->insert('git_repository_folders', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'color' => $data['color'] ?? '#6366f1',
            'icon' => $data['icon'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        $folder = $this->db->fetchAssociative(
            'SELECT * FROM git_repository_folders WHERE id = ?',
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

        $updated = $this->db->update('git_repository_folders', [
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

        $deleted = $this->db->delete('git_repository_folders', [
            'id' => $id,
            'user_id' => $userId,
        ]);

        if (!$deleted) {
            return JsonResponse::notFound('Folder not found');
        }

        return JsonResponse::success(null, 'Folder deleted');
    }

    // ==================== Helper Methods ====================

    private function detectProvider(string $url): string
    {
        if (str_contains($url, 'github.com')) {
            return 'github';
        }
        if (str_contains($url, 'gitlab.com')) {
            return 'gitlab';
        }
        if (str_contains($url, 'bitbucket.org')) {
            return 'bitbucket';
        }
        if (str_contains($url, 'gitea')) {
            return 'gitea';
        }
        return 'custom';
    }

    private function syncPullRequests(string $repositoryId, array $prs): void
    {
        foreach ($prs as $pr) {
            $existing = $this->db->fetchOne(
                'SELECT id FROM git_pull_requests WHERE repository_id = ? AND external_id = ?',
                [$repositoryId, $pr['external_id']]
            );

            $data = [
                'repository_id' => $repositoryId,
                'external_id' => $pr['external_id'],
                'number' => $pr['number'],
                'title' => $pr['title'],
                'description' => $pr['description'],
                'state' => $pr['state'],
                'author' => $pr['author'],
                'author_avatar' => $pr['author_avatar'],
                'source_branch' => $pr['source_branch'],
                'target_branch' => $pr['target_branch'],
                'additions' => $pr['additions'],
                'deletions' => $pr['deletions'],
                'changed_files' => $pr['changed_files'],
                'comments_count' => $pr['comments_count'],
                'is_mergeable' => $pr['is_mergeable'],
                'is_draft' => $pr['is_draft'],
                'labels' => json_encode($pr['labels']),
                'reviewers' => json_encode($pr['reviewers']),
                'external_url' => $pr['external_url'],
                'external_created_at' => $pr['external_created_at'],
                'external_updated_at' => $pr['external_updated_at'],
                'external_merged_at' => $pr['external_merged_at'],
                'external_closed_at' => $pr['external_closed_at'],
            ];

            if ($existing) {
                $this->db->update('git_pull_requests', $data, ['id' => $existing]);
            } else {
                $data['id'] = Uuid::uuid4()->toString();
                $this->db->insert('git_pull_requests', $data);
            }
        }
    }

    private function syncIssues(string $repositoryId, array $issues): void
    {
        foreach ($issues as $issue) {
            $existing = $this->db->fetchOne(
                'SELECT id FROM git_issues WHERE repository_id = ? AND external_id = ?',
                [$repositoryId, $issue['external_id']]
            );

            $data = [
                'repository_id' => $repositoryId,
                'external_id' => $issue['external_id'],
                'number' => $issue['number'],
                'title' => $issue['title'],
                'description' => $issue['description'],
                'state' => $issue['state'],
                'author' => $issue['author'],
                'author_avatar' => $issue['author_avatar'],
                'assignees' => json_encode($issue['assignees']),
                'labels' => json_encode($issue['labels']),
                'milestone' => $issue['milestone'],
                'comments_count' => $issue['comments_count'],
                'is_locked' => $issue['is_locked'],
                'external_url' => $issue['external_url'],
                'external_created_at' => $issue['external_created_at'],
                'external_updated_at' => $issue['external_updated_at'],
                'external_closed_at' => $issue['external_closed_at'],
            ];

            if ($existing) {
                $this->db->update('git_issues', $data, ['id' => $existing]);
            } else {
                $data['id'] = Uuid::uuid4()->toString();
                $this->db->insert('git_issues', $data);
            }
        }
    }

    private function syncCommits(string $repositoryId, array $commits): void
    {
        foreach ($commits as $commit) {
            $existing = $this->db->fetchOne(
                'SELECT id FROM git_commits WHERE repository_id = ? AND sha = ?',
                [$repositoryId, $commit['sha']]
            );

            if (!$existing) {
                $this->db->insert('git_commits', [
                    'id' => Uuid::uuid4()->toString(),
                    'repository_id' => $repositoryId,
                    'sha' => $commit['sha'],
                    'message' => $commit['message'],
                    'author_name' => $commit['author_name'],
                    'author_email' => $commit['author_email'],
                    'author_avatar' => $commit['author_avatar'],
                    'committer_name' => $commit['committer_name'],
                    'committer_email' => $commit['committer_email'],
                    'branch' => $commit['branch'],
                    'external_url' => $commit['external_url'],
                    'committed_at' => $commit['committed_at'],
                ]);
            }
        }
    }

    private function syncReleases(string $repositoryId, array $releases): void
    {
        foreach ($releases as $release) {
            $existing = $this->db->fetchOne(
                'SELECT id FROM git_releases WHERE repository_id = ? AND external_id = ?',
                [$repositoryId, $release['external_id']]
            );

            $data = [
                'repository_id' => $repositoryId,
                'external_id' => $release['external_id'],
                'tag_name' => $release['tag_name'],
                'name' => $release['name'],
                'description' => $release['description'],
                'is_prerelease' => $release['is_prerelease'],
                'is_draft' => $release['is_draft'],
                'author' => $release['author'],
                'author_avatar' => $release['author_avatar'],
                'assets' => json_encode($release['assets']),
                'download_count' => $release['download_count'],
                'external_url' => $release['external_url'],
                'published_at' => $release['published_at'],
            ];

            if ($existing) {
                $this->db->update('git_releases', $data, ['id' => $existing]);
            } else {
                $data['id'] = Uuid::uuid4()->toString();
                $this->db->insert('git_releases', $data);
            }
        }
    }
}
