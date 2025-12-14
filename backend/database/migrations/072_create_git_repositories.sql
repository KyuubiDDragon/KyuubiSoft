-- Migration: 072
-- Description: Create Git Repository Dashboard tables
-- Date: 2024-12-14

-- Git repository connections (GitHub, GitLab, Bitbucket, etc.)
CREATE TABLE IF NOT EXISTS git_repositories (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    project_id VARCHAR(36) NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    provider ENUM('github', 'gitlab', 'bitbucket', 'gitea', 'custom') NOT NULL DEFAULT 'github',
    repo_url VARCHAR(500) NOT NULL,
    api_url VARCHAR(500) NULL,
    api_token TEXT NULL,
    default_branch VARCHAR(100) DEFAULT 'main',
    is_private TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    auto_sync TINYINT(1) DEFAULT 1,
    sync_interval INT DEFAULT 300,
    last_sync_at DATETIME NULL,
    sync_error TEXT NULL,

    -- Cached repository stats
    stars_count INT DEFAULT 0,
    forks_count INT DEFAULT 0,
    open_issues_count INT DEFAULT 0,
    open_prs_count INT DEFAULT 0,
    watchers_count INT DEFAULT 0,

    -- Settings
    notify_on_new_pr TINYINT(1) DEFAULT 1,
    notify_on_new_issue TINYINT(1) DEFAULT 1,
    notify_on_merge TINYINT(1) DEFAULT 1,
    notify_on_release TINYINT(1) DEFAULT 0,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    INDEX idx_git_repos_user (user_id),
    INDEX idx_git_repos_project (project_id),
    INDEX idx_git_repos_provider (provider),
    INDEX idx_git_repos_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cached pull requests
CREATE TABLE IF NOT EXISTS git_pull_requests (
    id VARCHAR(36) PRIMARY KEY,
    repository_id VARCHAR(36) NOT NULL,
    external_id VARCHAR(100) NOT NULL,
    number INT NOT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT NULL,
    state ENUM('open', 'closed', 'merged', 'draft') NOT NULL DEFAULT 'open',
    author VARCHAR(255) NULL,
    author_avatar VARCHAR(500) NULL,
    source_branch VARCHAR(255) NULL,
    target_branch VARCHAR(255) NULL,
    additions INT DEFAULT 0,
    deletions INT DEFAULT 0,
    changed_files INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    reviews_count INT DEFAULT 0,
    is_mergeable TINYINT(1) NULL,
    is_draft TINYINT(1) DEFAULT 0,
    labels JSON NULL,
    reviewers JSON NULL,
    external_url VARCHAR(500) NULL,
    external_created_at DATETIME NULL,
    external_updated_at DATETIME NULL,
    external_merged_at DATETIME NULL,
    external_closed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (repository_id) REFERENCES git_repositories(id) ON DELETE CASCADE,
    UNIQUE KEY uk_repo_external (repository_id, external_id),
    INDEX idx_git_prs_repo (repository_id),
    INDEX idx_git_prs_state (state),
    INDEX idx_git_prs_number (number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cached issues
CREATE TABLE IF NOT EXISTS git_issues (
    id VARCHAR(36) PRIMARY KEY,
    repository_id VARCHAR(36) NOT NULL,
    external_id VARCHAR(100) NOT NULL,
    number INT NOT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT NULL,
    state ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    author VARCHAR(255) NULL,
    author_avatar VARCHAR(500) NULL,
    assignees JSON NULL,
    labels JSON NULL,
    milestone VARCHAR(255) NULL,
    comments_count INT DEFAULT 0,
    is_locked TINYINT(1) DEFAULT 0,
    external_url VARCHAR(500) NULL,
    external_created_at DATETIME NULL,
    external_updated_at DATETIME NULL,
    external_closed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (repository_id) REFERENCES git_repositories(id) ON DELETE CASCADE,
    UNIQUE KEY uk_issue_external (repository_id, external_id),
    INDEX idx_git_issues_repo (repository_id),
    INDEX idx_git_issues_state (state),
    INDEX idx_git_issues_number (number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cached commits (recent only)
CREATE TABLE IF NOT EXISTS git_commits (
    id VARCHAR(36) PRIMARY KEY,
    repository_id VARCHAR(36) NOT NULL,
    sha VARCHAR(64) NOT NULL,
    message TEXT NOT NULL,
    author_name VARCHAR(255) NULL,
    author_email VARCHAR(255) NULL,
    author_avatar VARCHAR(500) NULL,
    committer_name VARCHAR(255) NULL,
    committer_email VARCHAR(255) NULL,
    branch VARCHAR(255) NULL,
    additions INT DEFAULT 0,
    deletions INT DEFAULT 0,
    changed_files INT DEFAULT 0,
    external_url VARCHAR(500) NULL,
    committed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (repository_id) REFERENCES git_repositories(id) ON DELETE CASCADE,
    UNIQUE KEY uk_commit_sha (repository_id, sha),
    INDEX idx_git_commits_repo (repository_id),
    INDEX idx_git_commits_branch (branch),
    INDEX idx_git_commits_date (committed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cached releases
CREATE TABLE IF NOT EXISTS git_releases (
    id VARCHAR(36) PRIMARY KEY,
    repository_id VARCHAR(36) NOT NULL,
    external_id VARCHAR(100) NOT NULL,
    tag_name VARCHAR(100) NOT NULL,
    name VARCHAR(255) NULL,
    description TEXT NULL,
    is_prerelease TINYINT(1) DEFAULT 0,
    is_draft TINYINT(1) DEFAULT 0,
    author VARCHAR(255) NULL,
    author_avatar VARCHAR(500) NULL,
    assets JSON NULL,
    download_count INT DEFAULT 0,
    external_url VARCHAR(500) NULL,
    published_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (repository_id) REFERENCES git_repositories(id) ON DELETE CASCADE,
    UNIQUE KEY uk_release_external (repository_id, external_id),
    INDEX idx_git_releases_repo (repository_id),
    INDEX idx_git_releases_tag (tag_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Repository activity/events log
CREATE TABLE IF NOT EXISTS git_activity (
    id VARCHAR(36) PRIMARY KEY,
    repository_id VARCHAR(36) NOT NULL,
    event_type ENUM('push', 'pull_request', 'issue', 'release', 'branch', 'tag', 'fork', 'star', 'comment', 'review', 'merge') NOT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT NULL,
    actor VARCHAR(255) NULL,
    actor_avatar VARCHAR(500) NULL,
    reference_type VARCHAR(50) NULL,
    reference_id VARCHAR(100) NULL,
    external_url VARCHAR(500) NULL,
    event_data JSON NULL,
    occurred_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (repository_id) REFERENCES git_repositories(id) ON DELETE CASCADE,
    INDEX idx_git_activity_repo (repository_id),
    INDEX idx_git_activity_type (event_type),
    INDEX idx_git_activity_date (occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Folders for organizing repositories
CREATE TABLE IF NOT EXISTS git_repository_folders (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#6366f1',
    icon VARCHAR(50) NULL,
    sort_order INT DEFAULT 0,
    is_collapsed TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_git_folders_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add folder_id to repositories
ALTER TABLE git_repositories ADD COLUMN folder_id VARCHAR(36) NULL AFTER project_id;
ALTER TABLE git_repositories ADD FOREIGN KEY (folder_id) REFERENCES git_repository_folders(id) ON DELETE SET NULL;
ALTER TABLE git_repositories ADD INDEX idx_git_repos_folder (folder_id);
