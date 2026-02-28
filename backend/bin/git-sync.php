#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Git Repository Auto-Sync
 * Syncs all active repositories with auto_sync enabled.
 * Run via cron: 0 * * * * php /var/www/html/bin/git-sync.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Modules\GitRepository\Services\GitProviderService;
use Doctrine\DBAL\DriverManager;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Database connection
$db = DriverManager::getConnection([
    'dbname' => $_ENV['DB_DATABASE'] ?? 'kyuubisoft',
    'user' => $_ENV['DB_USERNAME'] ?? 'kyuubisoft',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'host' => $_ENV['DB_HOST'] ?? 'db',
    'port' => $_ENV['DB_PORT'] ?? 3306,
    'driver' => 'pdo_mysql',
    'charset' => 'utf8mb4',
]);

echo "[" . date('Y-m-d H:i:s') . "] Starting git repository sync...\n";

$gitProvider = new GitProviderService();

// Fetch all active repos with auto_sync enabled that are due for sync
$repositories = $db->fetchAllAssociative(
    'SELECT * FROM git_repositories
     WHERE is_active = 1 AND auto_sync = 1
     AND (last_sync_at IS NULL OR last_sync_at <= DATE_SUB(NOW(), INTERVAL sync_interval SECOND))'
);

echo "Found " . count($repositories) . " repositories to sync.\n";

$synced = 0;
$failed = 0;

foreach ($repositories as $repository) {
    $name = $repository['name'];
    try {
        // Fetch repo info
        $repoInfo = $gitProvider->fetchRepositoryInfo($repository);
        if ($repoInfo) {
            $db->update('git_repositories', [
                'stars_count' => (int) ($repoInfo['stars_count'] ?? 0),
                'forks_count' => (int) ($repoInfo['forks_count'] ?? 0),
                'open_issues_count' => (int) ($repoInfo['open_issues_count'] ?? 0),
                'watchers_count' => (int) ($repoInfo['watchers_count'] ?? 0),
                'default_branch' => $repoInfo['default_branch'] ?? 'main',
                'is_private' => !empty($repoInfo['is_private']) ? 1 : 0,
                'last_sync_at' => date('Y-m-d H:i:s'),
                'sync_error' => null,
            ], ['id' => $repository['id']]);
        }

        // Sync PRs, issues, commits, releases
        $prs = $gitProvider->fetchPullRequests($repository, 'all');
        syncPullRequests($db, $repository['id'], $prs);

        $issues = $gitProvider->fetchIssues($repository, 'all');
        syncIssues($db, $repository['id'], $issues);

        $commits = $gitProvider->fetchCommits($repository, 50);
        syncCommits($db, $repository['id'], $commits);

        $releases = $gitProvider->fetchReleases($repository, 20);
        syncReleases($db, $repository['id'], $releases);

        // Update PR count
        $openPrCount = $db->fetchOne(
            "SELECT COUNT(*) FROM git_pull_requests WHERE repository_id = ? AND state = 'open'",
            [$repository['id']]
        );
        $db->update('git_repositories', ['open_prs_count' => $openPrCount], ['id' => $repository['id']]);

        echo "  [OK] {$name}\n";
        $synced++;
    } catch (\Exception $e) {
        $db->update('git_repositories', [
            'sync_error' => $e->getMessage(),
        ], ['id' => $repository['id']]);
        echo "  [FAIL] {$name}: {$e->getMessage()}\n";
        $failed++;
    }
}

echo "\nDone. Synced: {$synced} | Failed: {$failed}\n";

// --- Helper functions (mirror the controller logic) ---

function formatDateTime(?string $datetime): ?string
{
    if (empty($datetime)) return null;
    try { return (new \DateTime($datetime))->format('Y-m-d H:i:s'); }
    catch (\Exception $e) { return null; }
}

function syncPullRequests(\Doctrine\DBAL\Connection $db, string $repositoryId, array $prs): void
{
    foreach ($prs as $pr) {
        $existing = $db->fetchOne(
            'SELECT id FROM git_pull_requests WHERE repository_id = ? AND external_id = ?',
            [$repositoryId, $pr['external_id']]
        );
        $data = [
            'repository_id' => $repositoryId,
            'external_id' => $pr['external_id'] ?? '',
            'number' => (int) ($pr['number'] ?? 0),
            'title' => $pr['title'] ?? '',
            'description' => $pr['description'] ?? null,
            'state' => $pr['state'] ?? 'open',
            'author' => $pr['author'] ?? null,
            'author_avatar' => $pr['author_avatar'] ?? null,
            'source_branch' => $pr['source_branch'] ?? null,
            'target_branch' => $pr['target_branch'] ?? null,
            'additions' => (int) ($pr['additions'] ?? 0),
            'deletions' => (int) ($pr['deletions'] ?? 0),
            'changed_files' => (int) ($pr['changed_files'] ?? 0),
            'comments_count' => (int) ($pr['comments_count'] ?? 0),
            'is_mergeable' => !empty($pr['is_mergeable']) ? 1 : 0,
            'is_draft' => !empty($pr['is_draft']) ? 1 : 0,
            'labels' => json_encode($pr['labels'] ?? []),
            'reviewers' => json_encode($pr['reviewers'] ?? []),
            'external_url' => $pr['external_url'] ?? null,
            'external_created_at' => formatDateTime($pr['external_created_at'] ?? null),
            'external_updated_at' => formatDateTime($pr['external_updated_at'] ?? null),
            'external_merged_at' => formatDateTime($pr['external_merged_at'] ?? null),
            'external_closed_at' => formatDateTime($pr['external_closed_at'] ?? null),
        ];
        if ($existing) {
            $db->update('git_pull_requests', $data, ['id' => $existing]);
        } else {
            $data['id'] = \Ramsey\Uuid\Uuid::uuid4()->toString();
            $db->insert('git_pull_requests', $data);
        }
    }
}

function syncIssues(\Doctrine\DBAL\Connection $db, string $repositoryId, array $issues): void
{
    foreach ($issues as $issue) {
        $existing = $db->fetchOne(
            'SELECT id FROM git_issues WHERE repository_id = ? AND external_id = ?',
            [$repositoryId, $issue['external_id']]
        );
        $data = [
            'repository_id' => $repositoryId,
            'external_id' => $issue['external_id'] ?? '',
            'number' => (int) ($issue['number'] ?? 0),
            'title' => $issue['title'] ?? '',
            'description' => $issue['description'] ?? null,
            'state' => $issue['state'] ?? 'open',
            'author' => $issue['author'] ?? null,
            'author_avatar' => $issue['author_avatar'] ?? null,
            'assignees' => json_encode($issue['assignees'] ?? []),
            'labels' => json_encode($issue['labels'] ?? []),
            'milestone' => $issue['milestone'] ?? null,
            'comments_count' => (int) ($issue['comments_count'] ?? 0),
            'is_locked' => !empty($issue['is_locked']) ? 1 : 0,
            'external_url' => $issue['external_url'] ?? null,
            'external_created_at' => formatDateTime($issue['external_created_at'] ?? null),
            'external_updated_at' => formatDateTime($issue['external_updated_at'] ?? null),
            'external_closed_at' => formatDateTime($issue['external_closed_at'] ?? null),
        ];
        if ($existing) {
            $db->update('git_issues', $data, ['id' => $existing]);
        } else {
            $data['id'] = \Ramsey\Uuid\Uuid::uuid4()->toString();
            $db->insert('git_issues', $data);
        }
    }
}

function syncCommits(\Doctrine\DBAL\Connection $db, string $repositoryId, array $commits): void
{
    foreach ($commits as $commit) {
        $existing = $db->fetchOne(
            'SELECT id FROM git_commits WHERE repository_id = ? AND sha = ?',
            [$repositoryId, $commit['sha']]
        );
        if (!$existing) {
            $db->insert('git_commits', [
                'id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
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
                'committed_at' => formatDateTime($commit['committed_at'] ?? null),
            ]);
        }
    }
}

function syncReleases(\Doctrine\DBAL\Connection $db, string $repositoryId, array $releases): void
{
    foreach ($releases as $release) {
        $existing = $db->fetchOne(
            'SELECT id FROM git_releases WHERE repository_id = ? AND external_id = ?',
            [$repositoryId, $release['external_id']]
        );
        $data = [
            'repository_id' => $repositoryId,
            'external_id' => $release['external_id'] ?? '',
            'tag_name' => $release['tag_name'] ?? '',
            'name' => $release['name'] ?? null,
            'description' => $release['description'] ?? null,
            'is_prerelease' => !empty($release['is_prerelease']) ? 1 : 0,
            'is_draft' => !empty($release['is_draft']) ? 1 : 0,
            'author' => $release['author'] ?? null,
            'author_avatar' => $release['author_avatar'] ?? null,
            'assets' => json_encode($release['assets'] ?? []),
            'download_count' => (int) ($release['download_count'] ?? 0),
            'external_url' => $release['external_url'] ?? null,
            'published_at' => formatDateTime($release['published_at'] ?? null),
        ];
        if ($existing) {
            $db->update('git_releases', $data, ['id' => $existing]);
        } else {
            $data['id'] = \Ramsey\Uuid\Uuid::uuid4()->toString();
            $db->insert('git_releases', $data);
        }
    }
}
