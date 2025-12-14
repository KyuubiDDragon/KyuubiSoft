<?php

declare(strict_types=1);

namespace App\Modules\GitRepository\Services;

/**
 * Service for interacting with Git providers (GitHub, GitLab, etc.)
 */
class GitProviderService
{
    private const GITHUB_API = 'https://api.github.com';
    private const GITLAB_API = 'https://gitlab.com/api/v4';

    /**
     * Fetch repository info from provider
     */
    public function fetchRepositoryInfo(array $repository): ?array
    {
        return match ($repository['provider']) {
            'github' => $this->fetchGitHubRepo($repository),
            'gitlab' => $this->fetchGitLabRepo($repository),
            default => null,
        };
    }

    /**
     * Fetch pull requests from provider
     */
    public function fetchPullRequests(array $repository, string $state = 'open'): array
    {
        return match ($repository['provider']) {
            'github' => $this->fetchGitHubPRs($repository, $state),
            'gitlab' => $this->fetchGitLabMRs($repository, $state),
            default => [],
        };
    }

    /**
     * Fetch issues from provider
     */
    public function fetchIssues(array $repository, string $state = 'open'): array
    {
        return match ($repository['provider']) {
            'github' => $this->fetchGitHubIssues($repository, $state),
            'gitlab' => $this->fetchGitLabIssues($repository, $state),
            default => [],
        };
    }

    /**
     * Fetch recent commits from provider
     */
    public function fetchCommits(array $repository, int $limit = 20): array
    {
        return match ($repository['provider']) {
            'github' => $this->fetchGitHubCommits($repository, $limit),
            'gitlab' => $this->fetchGitLabCommits($repository, $limit),
            default => [],
        };
    }

    /**
     * Fetch releases from provider
     */
    public function fetchReleases(array $repository, int $limit = 10): array
    {
        return match ($repository['provider']) {
            'github' => $this->fetchGitHubReleases($repository, $limit),
            'gitlab' => $this->fetchGitLabReleases($repository, $limit),
            default => [],
        };
    }

    // ==================== GitHub Methods ====================

    private function fetchGitHubRepo(array $repository): ?array
    {
        $repoPath = $this->extractGitHubRepoPath($repository['repo_url']);
        if (!$repoPath) {
            return null;
        }

        $response = $this->makeGitHubRequest("/repos/{$repoPath}", $repository['api_token']);
        if (!$response) {
            return null;
        }

        return [
            'stars_count' => $response['stargazers_count'] ?? 0,
            'forks_count' => $response['forks_count'] ?? 0,
            'open_issues_count' => $response['open_issues_count'] ?? 0,
            'watchers_count' => $response['watchers_count'] ?? 0,
            'default_branch' => $response['default_branch'] ?? 'main',
            'description' => $response['description'],
            'is_private' => $response['private'] ?? false,
        ];
    }

    private function fetchGitHubPRs(array $repository, string $state): array
    {
        $repoPath = $this->extractGitHubRepoPath($repository['repo_url']);
        if (!$repoPath) {
            return [];
        }

        $response = $this->makeGitHubRequest(
            "/repos/{$repoPath}/pulls?state={$state}&per_page=50",
            $repository['api_token']
        );

        if (!$response || !is_array($response)) {
            return [];
        }

        return array_map(fn($pr) => [
            'external_id' => (string) $pr['id'],
            'number' => $pr['number'],
            'title' => $pr['title'],
            'description' => $pr['body'] ?? '',
            'state' => $pr['merged_at'] ? 'merged' : ($pr['draft'] ? 'draft' : $pr['state']),
            'author' => $pr['user']['login'] ?? '',
            'author_avatar' => $pr['user']['avatar_url'] ?? '',
            'source_branch' => $pr['head']['ref'] ?? '',
            'target_branch' => $pr['base']['ref'] ?? '',
            'additions' => $pr['additions'] ?? 0,
            'deletions' => $pr['deletions'] ?? 0,
            'changed_files' => $pr['changed_files'] ?? 0,
            'comments_count' => $pr['comments'] ?? 0,
            'is_mergeable' => $pr['mergeable'] ?? null,
            'is_draft' => $pr['draft'] ?? false,
            'labels' => array_map(fn($l) => ['name' => $l['name'], 'color' => $l['color']], $pr['labels'] ?? []),
            'reviewers' => array_map(fn($r) => $r['login'], $pr['requested_reviewers'] ?? []),
            'external_url' => $pr['html_url'],
            'external_created_at' => $pr['created_at'],
            'external_updated_at' => $pr['updated_at'],
            'external_merged_at' => $pr['merged_at'],
            'external_closed_at' => $pr['closed_at'],
        ], $response);
    }

    private function fetchGitHubIssues(array $repository, string $state): array
    {
        $repoPath = $this->extractGitHubRepoPath($repository['repo_url']);
        if (!$repoPath) {
            return [];
        }

        $response = $this->makeGitHubRequest(
            "/repos/{$repoPath}/issues?state={$state}&per_page=50&filter=all",
            $repository['api_token']
        );

        if (!$response || !is_array($response)) {
            return [];
        }

        // Filter out pull requests (GitHub includes PRs in issues endpoint)
        $issues = array_filter($response, fn($issue) => !isset($issue['pull_request']));

        return array_map(fn($issue) => [
            'external_id' => (string) $issue['id'],
            'number' => $issue['number'],
            'title' => $issue['title'],
            'description' => $issue['body'] ?? '',
            'state' => $issue['state'],
            'author' => $issue['user']['login'] ?? '',
            'author_avatar' => $issue['user']['avatar_url'] ?? '',
            'assignees' => array_map(fn($a) => $a['login'], $issue['assignees'] ?? []),
            'labels' => array_map(fn($l) => ['name' => $l['name'], 'color' => $l['color']], $issue['labels'] ?? []),
            'milestone' => $issue['milestone']['title'] ?? null,
            'comments_count' => $issue['comments'] ?? 0,
            'is_locked' => $issue['locked'] ?? false,
            'external_url' => $issue['html_url'],
            'external_created_at' => $issue['created_at'],
            'external_updated_at' => $issue['updated_at'],
            'external_closed_at' => $issue['closed_at'],
        ], array_values($issues));
    }

    private function fetchGitHubCommits(array $repository, int $limit): array
    {
        $repoPath = $this->extractGitHubRepoPath($repository['repo_url']);
        if (!$repoPath) {
            return [];
        }

        $branch = $repository['default_branch'] ?? 'main';
        $response = $this->makeGitHubRequest(
            "/repos/{$repoPath}/commits?sha={$branch}&per_page={$limit}",
            $repository['api_token']
        );

        if (!$response || !is_array($response)) {
            return [];
        }

        return array_map(fn($commit) => [
            'sha' => $commit['sha'],
            'message' => $commit['commit']['message'],
            'author_name' => $commit['commit']['author']['name'] ?? '',
            'author_email' => $commit['commit']['author']['email'] ?? '',
            'author_avatar' => $commit['author']['avatar_url'] ?? '',
            'committer_name' => $commit['commit']['committer']['name'] ?? '',
            'committer_email' => $commit['commit']['committer']['email'] ?? '',
            'branch' => $branch,
            'external_url' => $commit['html_url'],
            'committed_at' => $commit['commit']['author']['date'],
        ], $response);
    }

    private function fetchGitHubReleases(array $repository, int $limit): array
    {
        $repoPath = $this->extractGitHubRepoPath($repository['repo_url']);
        if (!$repoPath) {
            return [];
        }

        $response = $this->makeGitHubRequest(
            "/repos/{$repoPath}/releases?per_page={$limit}",
            $repository['api_token']
        );

        if (!$response || !is_array($response)) {
            return [];
        }

        return array_map(fn($release) => [
            'external_id' => (string) $release['id'],
            'tag_name' => $release['tag_name'],
            'name' => $release['name'] ?? $release['tag_name'],
            'description' => $release['body'] ?? '',
            'is_prerelease' => $release['prerelease'] ?? false,
            'is_draft' => $release['draft'] ?? false,
            'author' => $release['author']['login'] ?? '',
            'author_avatar' => $release['author']['avatar_url'] ?? '',
            'assets' => array_map(fn($a) => [
                'name' => $a['name'],
                'size' => $a['size'],
                'download_count' => $a['download_count'],
                'url' => $a['browser_download_url'],
            ], $release['assets'] ?? []),
            'download_count' => array_sum(array_column($release['assets'] ?? [], 'download_count')),
            'external_url' => $release['html_url'],
            'published_at' => $release['published_at'],
        ], $response);
    }

    private function extractGitHubRepoPath(string $url): ?string
    {
        // Handle various GitHub URL formats
        $patterns = [
            '#github\.com[:/]([^/]+/[^/]+?)(?:\.git)?$#',
            '#github\.com/([^/]+/[^/]+)#',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return rtrim($matches[1], '/');
            }
        }

        return null;
    }

    private function makeGitHubRequest(string $endpoint, ?string $token): mixed
    {
        $url = self::GITHUB_API . $endpoint;

        $headers = [
            'Accept: application/vnd.github.v3+json',
            'User-Agent: KyuubiSoft-GitDashboard',
        ];

        if ($token) {
            $headers[] = "Authorization: Bearer {$token}";
        }

        return $this->makeHttpRequest($url, $headers);
    }

    // ==================== GitLab Methods ====================

    private function fetchGitLabRepo(array $repository): ?array
    {
        $projectId = $this->extractGitLabProjectId($repository['repo_url']);
        if (!$projectId) {
            return null;
        }

        $apiUrl = $repository['api_url'] ?? self::GITLAB_API;
        $response = $this->makeGitLabRequest(
            "{$apiUrl}/projects/{$projectId}",
            $repository['api_token']
        );

        if (!$response) {
            return null;
        }

        return [
            'stars_count' => $response['star_count'] ?? 0,
            'forks_count' => $response['forks_count'] ?? 0,
            'open_issues_count' => $response['open_issues_count'] ?? 0,
            'watchers_count' => 0,
            'default_branch' => $response['default_branch'] ?? 'main',
            'description' => $response['description'],
            'is_private' => $response['visibility'] !== 'public',
        ];
    }

    private function fetchGitLabMRs(array $repository, string $state): array
    {
        $projectId = $this->extractGitLabProjectId($repository['repo_url']);
        if (!$projectId) {
            return [];
        }

        $gitlabState = match ($state) {
            'open' => 'opened',
            'closed' => 'closed',
            'merged' => 'merged',
            default => 'all',
        };

        $apiUrl = $repository['api_url'] ?? self::GITLAB_API;
        $response = $this->makeGitLabRequest(
            "{$apiUrl}/projects/{$projectId}/merge_requests?state={$gitlabState}&per_page=50",
            $repository['api_token']
        );

        if (!$response || !is_array($response)) {
            return [];
        }

        return array_map(fn($mr) => [
            'external_id' => (string) $mr['id'],
            'number' => $mr['iid'],
            'title' => $mr['title'],
            'description' => $mr['description'] ?? '',
            'state' => $mr['state'] === 'opened' ? 'open' : $mr['state'],
            'author' => $mr['author']['username'] ?? '',
            'author_avatar' => $mr['author']['avatar_url'] ?? '',
            'source_branch' => $mr['source_branch'] ?? '',
            'target_branch' => $mr['target_branch'] ?? '',
            'additions' => 0,
            'deletions' => 0,
            'changed_files' => 0,
            'comments_count' => $mr['user_notes_count'] ?? 0,
            'is_mergeable' => $mr['merge_status'] === 'can_be_merged',
            'is_draft' => $mr['draft'] ?? false,
            'labels' => array_map(fn($l) => ['name' => $l, 'color' => ''], $mr['labels'] ?? []),
            'reviewers' => array_map(fn($r) => $r['username'], $mr['reviewers'] ?? []),
            'external_url' => $mr['web_url'],
            'external_created_at' => $mr['created_at'],
            'external_updated_at' => $mr['updated_at'],
            'external_merged_at' => $mr['merged_at'],
            'external_closed_at' => $mr['closed_at'],
        ], $response);
    }

    private function fetchGitLabIssues(array $repository, string $state): array
    {
        $projectId = $this->extractGitLabProjectId($repository['repo_url']);
        if (!$projectId) {
            return [];
        }

        $gitlabState = match ($state) {
            'open' => 'opened',
            'closed' => 'closed',
            default => 'all',
        };

        $apiUrl = $repository['api_url'] ?? self::GITLAB_API;
        $response = $this->makeGitLabRequest(
            "{$apiUrl}/projects/{$projectId}/issues?state={$gitlabState}&per_page=50",
            $repository['api_token']
        );

        if (!$response || !is_array($response)) {
            return [];
        }

        return array_map(fn($issue) => [
            'external_id' => (string) $issue['id'],
            'number' => $issue['iid'],
            'title' => $issue['title'],
            'description' => $issue['description'] ?? '',
            'state' => $issue['state'] === 'opened' ? 'open' : 'closed',
            'author' => $issue['author']['username'] ?? '',
            'author_avatar' => $issue['author']['avatar_url'] ?? '',
            'assignees' => array_map(fn($a) => $a['username'], $issue['assignees'] ?? []),
            'labels' => array_map(fn($l) => ['name' => $l, 'color' => ''], $issue['labels'] ?? []),
            'milestone' => $issue['milestone']['title'] ?? null,
            'comments_count' => $issue['user_notes_count'] ?? 0,
            'is_locked' => $issue['discussion_locked'] ?? false,
            'external_url' => $issue['web_url'],
            'external_created_at' => $issue['created_at'],
            'external_updated_at' => $issue['updated_at'],
            'external_closed_at' => $issue['closed_at'],
        ], $response);
    }

    private function fetchGitLabCommits(array $repository, int $limit): array
    {
        $projectId = $this->extractGitLabProjectId($repository['repo_url']);
        if (!$projectId) {
            return [];
        }

        $branch = $repository['default_branch'] ?? 'main';
        $apiUrl = $repository['api_url'] ?? self::GITLAB_API;
        $response = $this->makeGitLabRequest(
            "{$apiUrl}/projects/{$projectId}/repository/commits?ref_name={$branch}&per_page={$limit}",
            $repository['api_token']
        );

        if (!$response || !is_array($response)) {
            return [];
        }

        return array_map(fn($commit) => [
            'sha' => $commit['id'],
            'message' => $commit['message'],
            'author_name' => $commit['author_name'] ?? '',
            'author_email' => $commit['author_email'] ?? '',
            'author_avatar' => '',
            'committer_name' => $commit['committer_name'] ?? '',
            'committer_email' => $commit['committer_email'] ?? '',
            'branch' => $branch,
            'external_url' => $commit['web_url'],
            'committed_at' => $commit['committed_date'],
        ], $response);
    }

    private function fetchGitLabReleases(array $repository, int $limit): array
    {
        $projectId = $this->extractGitLabProjectId($repository['repo_url']);
        if (!$projectId) {
            return [];
        }

        $apiUrl = $repository['api_url'] ?? self::GITLAB_API;
        $response = $this->makeGitLabRequest(
            "{$apiUrl}/projects/{$projectId}/releases?per_page={$limit}",
            $repository['api_token']
        );

        if (!$response || !is_array($response)) {
            return [];
        }

        return array_map(fn($release) => [
            'external_id' => $release['tag_name'],
            'tag_name' => $release['tag_name'],
            'name' => $release['name'] ?? $release['tag_name'],
            'description' => $release['description'] ?? '',
            'is_prerelease' => false,
            'is_draft' => false,
            'author' => $release['author']['username'] ?? '',
            'author_avatar' => $release['author']['avatar_url'] ?? '',
            'assets' => array_map(fn($a) => [
                'name' => $a['name'],
                'size' => 0,
                'download_count' => 0,
                'url' => $a['url'],
            ], $release['assets']['links'] ?? []),
            'download_count' => 0,
            'external_url' => $release['_links']['self'] ?? '',
            'published_at' => $release['released_at'],
        ], $response);
    }

    private function extractGitLabProjectId(string $url): ?string
    {
        // Handle various GitLab URL formats
        if (preg_match('#gitlab\.com/([^/]+/[^/]+)#', $url, $matches)) {
            return urlencode($matches[1]);
        }

        // Handle custom GitLab instances
        if (preg_match('#/([^/]+/[^/]+?)(?:\.git)?$#', $url, $matches)) {
            return urlencode($matches[1]);
        }

        return null;
    }

    private function makeGitLabRequest(string $url, ?string $token): mixed
    {
        $headers = [
            'Accept: application/json',
            'User-Agent: KyuubiSoft-GitDashboard',
        ];

        if ($token) {
            $headers[] = "PRIVATE-TOKEN: {$token}";
        }

        return $this->makeHttpRequest($url, $headers);
    }

    // ==================== Common HTTP ====================

    private function makeHttpRequest(string $url, array $headers): mixed
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        return json_decode($response, true);
    }
}
