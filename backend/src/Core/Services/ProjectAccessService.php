<?php

declare(strict_types=1);

namespace App\Core\Services;

use Doctrine\DBAL\Connection;

/**
 * Service to check project-based access control.
 * Users with restricted_to_projects=TRUE can only access items in shared projects.
 */
class ProjectAccessService
{
    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * Check if user is restricted to projects only.
     */
    public function isUserRestricted(string $userId): bool
    {
        $result = $this->db->fetchOne(
            'SELECT restricted_to_projects FROM users WHERE id = ?',
            [$userId]
        );

        return (bool) $result;
    }

    /**
     * Get all project IDs the user has access to.
     * Returns empty array if user has no project access.
     */
    public function getUserAccessibleProjectIds(string $userId): array
    {
        // Get projects shared with the user (where user is invited)
        $projectIds = $this->db->fetchFirstColumn(
            'SELECT project_id FROM project_shares WHERE user_id = ?',
            [$userId]
        );

        return $projectIds;
    }

    /**
     * Check if user has access to a specific project.
     */
    public function canAccessProject(string $userId, string $projectId): bool
    {
        // Check if user is owner
        $isOwner = $this->db->fetchOne(
            'SELECT 1 FROM projects WHERE id = ? AND user_id = ?',
            [$projectId, $userId]
        );

        if ($isOwner) {
            return true;
        }

        // Check if project is shared with user
        $isShared = $this->db->fetchOne(
            'SELECT 1 FROM project_shares WHERE project_id = ? AND user_id = ?',
            [$projectId, $userId]
        );

        return (bool) $isShared;
    }

    /**
     * Get permission level for a project ('owner', 'edit', 'view', or null).
     */
    public function getProjectPermission(string $userId, string $projectId): ?string
    {
        // Check if user is owner
        $isOwner = $this->db->fetchOne(
            'SELECT 1 FROM projects WHERE id = ? AND user_id = ?',
            [$projectId, $userId]
        );

        if ($isOwner) {
            return 'owner';
        }

        // Check shared permission
        $permission = $this->db->fetchOne(
            'SELECT permission FROM project_shares WHERE project_id = ? AND user_id = ?',
            [$projectId, $userId]
        );

        return $permission ?: null;
    }

    /**
     * Build SQL condition for filtering items by project access.
     * Returns array with [sql_condition, params, types].
     *
     * @param string $userId The user ID
     * @param string $tableName The table name (e.g., 'documents', 'lists')
     * @param string $itemType The linkable_type (e.g., 'document', 'list')
     * @param string $idColumn The ID column name (default 'id')
     * @return array{condition: string, params: array, types: array}|null Returns null if user is not restricted
     */
    public function buildProjectAccessCondition(
        string $userId,
        string $tableName,
        string $itemType,
        string $idColumn = 'id'
    ): ?array {
        if (!$this->isUserRestricted($userId)) {
            return null;
        }

        $projectIds = $this->getUserAccessibleProjectIds($userId);

        if (empty($projectIds)) {
            // User has no projects - return impossible condition
            return [
                'condition' => '1 = 0',
                'params' => [],
                'types' => [],
            ];
        }

        // Build IN clause for project IDs
        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));

        return [
            'condition' => "{$tableName}.{$idColumn} IN (
                SELECT pl.linkable_id FROM project_links pl
                WHERE pl.linkable_type = ? AND pl.project_id IN ({$placeholders})
            )",
            'params' => array_merge([$itemType], $projectIds),
            'types' => array_fill(0, count($projectIds) + 1, \PDO::PARAM_STR),
        ];
    }

    /**
     * Check if restricted user can access a specific item.
     */
    public function canAccessItem(string $userId, string $itemType, string $itemId): bool
    {
        if (!$this->isUserRestricted($userId)) {
            return true;
        }

        $projectIds = $this->getUserAccessibleProjectIds($userId);

        if (empty($projectIds)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));

        $hasAccess = $this->db->fetchOne(
            "SELECT 1 FROM project_links
             WHERE linkable_type = ? AND linkable_id = ? AND project_id IN ({$placeholders})",
            array_merge([$itemType, $itemId], $projectIds)
        );

        return (bool) $hasAccess;
    }
}
