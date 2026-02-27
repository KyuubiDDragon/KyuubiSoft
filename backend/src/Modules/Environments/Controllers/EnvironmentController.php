<?php

declare(strict_types=1);

namespace App\Modules\Environments\Controllers;

use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class EnvironmentController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * List all environments for the authenticated user, with variable counts.
     */
    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $environments = $this->db->fetchAllAssociative(
            'SELECT e.*, p.name as project_name,
                    (SELECT COUNT(*) FROM environment_variables ev WHERE ev.environment_id = e.id) as variable_count
             FROM environments e
             LEFT JOIN projects p ON e.project_id = p.id
             WHERE e.user_id = ?
             ORDER BY e.project_id, e.name ASC',
            [$userId]
        );

        return JsonResponse::success($environments);
    }

    /**
     * Create a new environment.
     */
    public function create(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        $name = trim((string) ($body['name'] ?? ''));
        $slug = trim((string) ($body['slug'] ?? ''));
        $description = trim((string) ($body['description'] ?? ''));
        $projectId = !empty($body['project_id']) ? (string) $body['project_id'] : null;

        if ($name === '') {
            return JsonResponse::validationError([
                'name' => ['Name ist erforderlich'],
            ]);
        }

        if ($slug === '') {
            $slug = $this->generateSlug($name);
        }

        // Check for duplicate slug within the user's environments
        $existing = $this->db->fetchAssociative(
            'SELECT id FROM environments WHERE user_id = ? AND slug = ?',
            [$userId, $slug]
        );

        if ($existing) {
            return JsonResponse::validationError([
                'slug' => ['Ein Environment mit diesem Slug existiert bereits'],
            ]);
        }

        $id = Uuid::uuid4()->toString();

        $this->db->insert('environments', [
            'id' => $id,
            'user_id' => $userId,
            'project_id' => $projectId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description ?: null,
        ]);

        // Record history
        $this->recordHistory($id, $userId, 'created', [
            'name' => $name,
            'slug' => $slug,
            'description' => $description ?: null,
            'project_id' => $projectId,
        ]);

        $env = $this->db->fetchAssociative(
            'SELECT e.*, p.name as project_name,
                    0 as variable_count
             FROM environments e
             LEFT JOIN projects p ON e.project_id = p.id
             WHERE e.id = ?',
            [$id]
        );

        return JsonResponse::created($env);
    }

    /**
     * Get a single environment with all its variables.
     */
    public function show(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $env = $this->db->fetchAssociative(
            'SELECT e.*, p.name as project_name
             FROM environments e
             LEFT JOIN projects p ON e.project_id = p.id
             WHERE e.id = ? AND e.user_id = ?',
            [$id, $userId]
        );

        if (!$env) {
            return JsonResponse::error('Umgebung nicht gefunden', 404);
        }

        $variables = $this->db->fetchAllAssociative(
            'SELECT id, environment_id, var_key, var_value, is_secret, sort_order, created_at, updated_at
             FROM environment_variables
             WHERE environment_id = ?
             ORDER BY sort_order ASC, var_key ASC',
            [$id]
        );

        // Mask secret values
        $variables = array_map(function (array $var): array {
            $var['is_secret'] = (bool) $var['is_secret'];
            if ($var['is_secret'] && $var['var_value'] !== null && $var['var_value'] !== '') {
                $var['var_value'] = $this->maskValue($var['var_value']);
            }
            return $var;
        }, $variables);

        $env['variables'] = $variables;

        return JsonResponse::success($env);
    }

    /**
     * Update environment metadata.
     */
    public function update(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $existing = $this->db->fetchAssociative(
            'SELECT * FROM environments WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$existing) {
            return JsonResponse::error('Umgebung nicht gefunden', 404);
        }

        $body = (array) $request->getParsedBody();
        $updates = [];
        $changes = [];

        if (isset($body['name'])) {
            $name = trim((string) $body['name']);
            if ($name === '') {
                return JsonResponse::validationError([
                    'name' => ['Name darf nicht leer sein'],
                ]);
            }
            $updates['name'] = $name;
            $changes['name'] = ['old' => $existing['name'], 'new' => $name];
        }

        if (isset($body['slug'])) {
            $slug = trim((string) $body['slug']);
            if ($slug === '') {
                return JsonResponse::validationError([
                    'slug' => ['Slug darf nicht leer sein'],
                ]);
            }
            // Check for duplicate slug (excluding current)
            $duplicate = $this->db->fetchAssociative(
                'SELECT id FROM environments WHERE user_id = ? AND slug = ? AND id != ?',
                [$userId, $slug, $id]
            );
            if ($duplicate) {
                return JsonResponse::validationError([
                    'slug' => ['Ein Environment mit diesem Slug existiert bereits'],
                ]);
            }
            $updates['slug'] = $slug;
            $changes['slug'] = ['old' => $existing['slug'], 'new' => $slug];
        }

        if (array_key_exists('description', $body)) {
            $description = trim((string) $body['description']) ?: null;
            $updates['description'] = $description;
            $changes['description'] = ['old' => $existing['description'], 'new' => $description];
        }

        if (array_key_exists('project_id', $body)) {
            $projectId = !empty($body['project_id']) ? (string) $body['project_id'] : null;
            $updates['project_id'] = $projectId;
            $changes['project_id'] = ['old' => $existing['project_id'], 'new' => $projectId];
        }

        if (!empty($updates)) {
            $this->db->update('environments', $updates, ['id' => $id]);
            $this->recordHistory($id, $userId, 'updated', $changes);
        }

        $env = $this->db->fetchAssociative(
            'SELECT e.*, p.name as project_name,
                    (SELECT COUNT(*) FROM environment_variables ev WHERE ev.environment_id = e.id) as variable_count
             FROM environments e
             LEFT JOIN projects p ON e.project_id = p.id
             WHERE e.id = ?',
            [$id]
        );

        return JsonResponse::success($env);
    }

    /**
     * Delete an environment and all its variables.
     */
    public function delete(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $existing = $this->db->fetchAssociative(
            'SELECT id, name FROM environments WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$existing) {
            return JsonResponse::error('Umgebung nicht gefunden', 404);
        }

        // Cascade delete will handle variables and history via FK constraints
        $this->db->delete('environments', ['id' => $id]);

        return JsonResponse::success(null, 'Umgebung geloescht');
    }

    /**
     * Get variables for an environment (mask secret values).
     */
    public function getVariables(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        // Verify ownership
        $env = $this->db->fetchAssociative(
            'SELECT id FROM environments WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$env) {
            return JsonResponse::error('Umgebung nicht gefunden', 404);
        }

        $variables = $this->db->fetchAllAssociative(
            'SELECT id, environment_id, var_key, var_value, is_secret, sort_order, created_at, updated_at
             FROM environment_variables
             WHERE environment_id = ?
             ORDER BY sort_order ASC, var_key ASC',
            [$id]
        );

        $variables = array_map(function (array $var): array {
            $var['is_secret'] = (bool) $var['is_secret'];
            if ($var['is_secret'] && $var['var_value'] !== null && $var['var_value'] !== '') {
                $var['var_value'] = $this->maskValue($var['var_value']);
            }
            return $var;
        }, $variables);

        return JsonResponse::success($variables);
    }

    /**
     * Bulk upsert variables for an environment.
     * Accepts array of {key, value, is_secret, sort_order}.
     */
    public function setVariables(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        // Verify ownership
        $env = $this->db->fetchAssociative(
            'SELECT id FROM environments WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$env) {
            return JsonResponse::error('Umgebung nicht gefunden', 404);
        }

        $body = (array) $request->getParsedBody();
        $variablesInput = (array) ($body['variables'] ?? []);

        if (empty($variablesInput)) {
            return JsonResponse::validationError([
                'variables' => ['Mindestens eine Variable ist erforderlich'],
            ]);
        }

        // Fetch existing variables for this environment
        $existingVars = $this->db->fetchAllAssociative(
            'SELECT id, var_key, var_value, is_secret FROM environment_variables WHERE environment_id = ?',
            [$id]
        );
        $existingByKey = [];
        foreach ($existingVars as $ev) {
            $existingByKey[$ev['var_key']] = $ev;
        }

        $changes = [];
        $sortOrder = 0;

        foreach ($variablesInput as $varInput) {
            $key = trim((string) ($varInput['key'] ?? ''));
            $value = (string) ($varInput['value'] ?? '');
            $isSecret = (bool) ($varInput['is_secret'] ?? false);

            if ($key === '') {
                continue;
            }

            if (isset($existingByKey[$key])) {
                // Update existing variable
                $existingVar = $existingByKey[$key];
                $updateData = [
                    'is_secret' => $isSecret ? 1 : 0,
                    'sort_order' => $sortOrder,
                ];

                // If the submitted value matches the masked pattern, don't overwrite
                if ($this->isMaskedValue($value)) {
                    // Value is masked, keep the existing value in DB
                } else {
                    $updateData['var_value'] = $value;
                    if ($existingVar['var_value'] !== $value) {
                        $changes[] = [
                            'action' => 'variable_updated',
                            'key' => $key,
                            'old_value' => $isSecret || (bool) $existingVar['is_secret'] ? '***' : $existingVar['var_value'],
                            'new_value' => $isSecret ? '***' : $value,
                        ];
                    }
                }

                $this->db->update('environment_variables', $updateData, ['id' => $existingVar['id']]);
                unset($existingByKey[$key]);
            } else {
                // Insert new variable
                $varId = Uuid::uuid4()->toString();
                $this->db->insert('environment_variables', [
                    'id' => $varId,
                    'environment_id' => $id,
                    'var_key' => $key,
                    'var_value' => $value,
                    'is_secret' => $isSecret ? 1 : 0,
                    'sort_order' => $sortOrder,
                ]);

                $changes[] = [
                    'action' => 'variable_added',
                    'key' => $key,
                    'value' => $isSecret ? '***' : $value,
                ];
            }

            $sortOrder++;
        }

        // Record history if there were changes
        if (!empty($changes)) {
            $this->recordHistory($id, $userId, 'variables_updated', $changes);
        }

        // Return updated variables
        $variables = $this->db->fetchAllAssociative(
            'SELECT id, environment_id, var_key, var_value, is_secret, sort_order, created_at, updated_at
             FROM environment_variables
             WHERE environment_id = ?
             ORDER BY sort_order ASC, var_key ASC',
            [$id]
        );

        $variables = array_map(function (array $var): array {
            $var['is_secret'] = (bool) $var['is_secret'];
            if ($var['is_secret'] && $var['var_value'] !== null && $var['var_value'] !== '') {
                $var['var_value'] = $this->maskValue($var['var_value']);
            }
            return $var;
        }, $variables);

        return JsonResponse::success($variables);
    }

    /**
     * Delete a single variable.
     */
    public function deleteVariable(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $varId = RouteContext::fromRequest($request)->getRoute()->getArgument('varId');

        // Verify ownership of environment
        $env = $this->db->fetchAssociative(
            'SELECT id FROM environments WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$env) {
            return JsonResponse::error('Umgebung nicht gefunden', 404);
        }

        $variable = $this->db->fetchAssociative(
            'SELECT id, var_key, var_value, is_secret FROM environment_variables WHERE id = ? AND environment_id = ?',
            [$varId, $id]
        );

        if (!$variable) {
            return JsonResponse::error('Variable nicht gefunden', 404);
        }

        $this->db->delete('environment_variables', ['id' => $varId]);

        $this->recordHistory($id, $userId, 'variable_deleted', [
            'key' => $variable['var_key'],
            'value' => (bool) $variable['is_secret'] ? '***' : $variable['var_value'],
        ]);

        return JsonResponse::success(null, 'Variable geloescht');
    }

    /**
     * Get change history for an environment.
     */
    public function getHistory(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        // Verify ownership
        $env = $this->db->fetchAssociative(
            'SELECT id FROM environments WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$env) {
            return JsonResponse::error('Umgebung nicht gefunden', 404);
        }

        $history = $this->db->fetchAllAssociative(
            'SELECT eh.*, u.name as user_name
             FROM environment_history eh
             LEFT JOIN users u ON eh.user_id = u.id
             WHERE eh.environment_id = ?
             ORDER BY eh.created_at DESC
             LIMIT 100',
            [$id]
        );

        // Decode JSON changes
        $history = array_map(function (array $entry): array {
            if (is_string($entry['changes'])) {
                $entry['changes'] = json_decode($entry['changes'], true);
            }
            return $entry;
        }, $history);

        return JsonResponse::success($history);
    }

    /**
     * Clone an environment with all its variables.
     */
    public function duplicate(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $env = $this->db->fetchAssociative(
            'SELECT * FROM environments WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$env) {
            return JsonResponse::error('Umgebung nicht gefunden', 404);
        }

        $body = (array) $request->getParsedBody();
        $newName = trim((string) ($body['name'] ?? ($env['name'] . ' (Kopie)')));
        $newSlug = trim((string) ($body['slug'] ?? ($env['slug'] . '-copy')));

        // Ensure unique slug
        $counter = 0;
        $baseSlug = $newSlug;
        while (true) {
            $slugToCheck = $counter > 0 ? $baseSlug . '-' . $counter : $baseSlug;
            $duplicate = $this->db->fetchAssociative(
                'SELECT id FROM environments WHERE user_id = ? AND slug = ?',
                [$userId, $slugToCheck]
            );
            if (!$duplicate) {
                $newSlug = $slugToCheck;
                break;
            }
            $counter++;
        }

        $newId = Uuid::uuid4()->toString();

        $this->db->insert('environments', [
            'id' => $newId,
            'user_id' => $userId,
            'project_id' => $env['project_id'],
            'name' => $newName,
            'slug' => $newSlug,
            'description' => $env['description'],
        ]);

        // Copy all variables
        $variables = $this->db->fetchAllAssociative(
            'SELECT var_key, var_value, is_secret, sort_order FROM environment_variables WHERE environment_id = ?',
            [$id]
        );

        foreach ($variables as $var) {
            $this->db->insert('environment_variables', [
                'id' => Uuid::uuid4()->toString(),
                'environment_id' => $newId,
                'var_key' => $var['var_key'],
                'var_value' => $var['var_value'],
                'is_secret' => $var['is_secret'],
                'sort_order' => $var['sort_order'],
            ]);
        }

        $this->recordHistory($newId, $userId, 'duplicated', [
            'source_id' => $id,
            'source_name' => $env['name'],
            'variables_copied' => count($variables),
        ]);

        $newEnv = $this->db->fetchAssociative(
            'SELECT e.*, p.name as project_name,
                    (SELECT COUNT(*) FROM environment_variables ev WHERE ev.environment_id = e.id) as variable_count
             FROM environments e
             LEFT JOIN projects p ON e.project_id = p.id
             WHERE e.id = ?',
            [$newId]
        );

        return JsonResponse::created($newEnv);
    }

    /**
     * Export as .env format string.
     */
    public function exportEnv(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $env = $this->db->fetchAssociative(
            'SELECT id, name FROM environments WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$env) {
            return JsonResponse::error('Umgebung nicht gefunden', 404);
        }

        $variables = $this->db->fetchAllAssociative(
            'SELECT var_key, var_value, is_secret FROM environment_variables WHERE environment_id = ? ORDER BY sort_order ASC, var_key ASC',
            [$id]
        );

        $lines = [];
        $lines[] = '# Environment: ' . $env['name'];
        $lines[] = '# Exportiert am: ' . date('Y-m-d H:i:s');
        $lines[] = '';

        foreach ($variables as $var) {
            $value = $var['var_value'] ?? '';
            // Quote values that contain spaces, special chars, or are empty
            if ($value === '' || preg_match('/[\s#"\'\\\\]/', $value)) {
                $value = '"' . addcslashes($value, '"\\') . '"';
            }
            $lines[] = $var['var_key'] . '=' . $value;
        }

        return JsonResponse::success([
            'content' => implode("\n", $lines),
            'filename' => $env['name'] . '.env',
        ]);
    }

    /**
     * Import from .env format string.
     */
    public function importEnv(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $env = $this->db->fetchAssociative(
            'SELECT id FROM environments WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$env) {
            return JsonResponse::error('Umgebung nicht gefunden', 404);
        }

        $body = (array) $request->getParsedBody();
        $content = trim((string) ($body['content'] ?? ''));
        $overwrite = (bool) ($body['overwrite'] ?? false);

        if ($content === '') {
            return JsonResponse::validationError([
                'content' => ['Inhalt ist erforderlich'],
            ]);
        }

        // Parse .env content
        $parsed = $this->parseEnvContent($content);

        if (empty($parsed)) {
            return JsonResponse::validationError([
                'content' => ['Keine gueltigen Variablen gefunden'],
            ]);
        }

        // Fetch existing variables
        $existingVars = $this->db->fetchAllAssociative(
            'SELECT id, var_key, var_value, is_secret, sort_order FROM environment_variables WHERE environment_id = ?',
            [$id]
        );
        $existingByKey = [];
        foreach ($existingVars as $ev) {
            $existingByKey[$ev['var_key']] = $ev;
        }

        $maxSortOrder = 0;
        if (!empty($existingVars)) {
            $maxSortOrder = (int) max(array_column($existingVars, 'sort_order')) + 1;
        }

        $changes = [];
        $imported = 0;

        foreach ($parsed as $key => $value) {
            if (isset($existingByKey[$key])) {
                if ($overwrite) {
                    $this->db->update('environment_variables', [
                        'var_value' => $value,
                    ], ['id' => $existingByKey[$key]['id']]);
                    $changes[] = [
                        'action' => 'variable_updated',
                        'key' => $key,
                    ];
                    $imported++;
                }
                // If not overwrite, skip existing keys
            } else {
                $this->db->insert('environment_variables', [
                    'id' => Uuid::uuid4()->toString(),
                    'environment_id' => $id,
                    'var_key' => $key,
                    'var_value' => $value,
                    'is_secret' => 0,
                    'sort_order' => $maxSortOrder++,
                ]);
                $changes[] = [
                    'action' => 'variable_added',
                    'key' => $key,
                ];
                $imported++;
            }
        }

        if (!empty($changes)) {
            $this->recordHistory($id, $userId, 'imported', [
                'variables_imported' => $imported,
                'overwrite' => $overwrite,
                'details' => $changes,
            ]);
        }

        // Return updated variables
        $variables = $this->db->fetchAllAssociative(
            'SELECT id, environment_id, var_key, var_value, is_secret, sort_order, created_at, updated_at
             FROM environment_variables
             WHERE environment_id = ?
             ORDER BY sort_order ASC, var_key ASC',
            [$id]
        );

        $variables = array_map(function (array $var): array {
            $var['is_secret'] = (bool) $var['is_secret'];
            if ($var['is_secret'] && $var['var_value'] !== null && $var['var_value'] !== '') {
                $var['var_value'] = $this->maskValue($var['var_value']);
            }
            return $var;
        }, $variables);

        return JsonResponse::success([
            'variables' => $variables,
            'imported' => $imported,
        ]);
    }

    /**
     * Mask a secret value, showing only the last 4 characters.
     */
    private function maskValue(string $value): string
    {
        $len = mb_strlen($value);
        if ($len <= 4) {
            return '****';
        }
        return '****' . mb_substr($value, -4);
    }

    /**
     * Check if a value matches the masked pattern (starts with ****).
     */
    private function isMaskedValue(string $value): bool
    {
        return str_starts_with($value, '****');
    }

    /**
     * Generate a URL-safe slug from a name.
     */
    private function generateSlug(string $name): string
    {
        $slug = mb_strtolower($name);
        $slug = preg_replace('/[^a-z0-9\-_]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Record an entry in the environment_history table.
     */
    private function recordHistory(string $environmentId, string $userId, string $action, mixed $changes): void
    {
        $this->db->insert('environment_history', [
            'id' => Uuid::uuid4()->toString(),
            'environment_id' => $environmentId,
            'user_id' => $userId,
            'action' => $action,
            'changes' => json_encode($changes, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Parse .env file content into key-value pairs.
     */
    private function parseEnvContent(string $content): array
    {
        $result = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Find the first = sign
            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $eqPos));
            $value = substr($line, $eqPos + 1);

            // Skip invalid keys
            if ($key === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                continue;
            }

            // Remove surrounding quotes from value
            $value = trim($value);
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            // Process escaped characters in double-quoted values
            $value = stripcslashes($value);

            $result[$key] = $value;
        }

        return $result;
    }
}
