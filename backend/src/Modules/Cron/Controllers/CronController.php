<?php

declare(strict_types=1);

namespace App\Modules\Cron\Controllers;

use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class CronController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * List user's cron jobs, LEFT JOIN with connections table for server name.
     */
    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $jobs = $this->db->fetchAllAssociative(
            'SELECT cj.*, c.name as connection_name
             FROM cron_jobs cj
             LEFT JOIN connections c ON cj.connection_id = c.id
             WHERE cj.user_id = ?
             ORDER BY cj.connection_id, cj.created_at ASC',
            [$userId]
        );

        // Add human-readable description for each job
        $jobs = array_map(function (array $job): array {
            $job['is_active'] = (bool) $job['is_active'];
            $job['readable_expression'] = $this->describeCronExpression($job['expression']);
            return $job;
        }, $jobs);

        return JsonResponse::success($jobs);
    }

    /**
     * Create a new cron job.
     */
    public function create(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        $expression = trim((string) ($body['expression'] ?? ''));
        $command = trim((string) ($body['command'] ?? ''));
        $description = trim((string) ($body['description'] ?? ''));
        $connectionId = !empty($body['connection_id']) ? (string) $body['connection_id'] : null;
        $isActive = (bool) ($body['is_active'] ?? true);

        // Validate required fields
        if ($expression === '' || $command === '') {
            return JsonResponse::validationError([
                'expression' => $expression === '' ? ['Cron-Ausdruck ist erforderlich'] : [],
                'command' => $command === '' ? ['Befehl ist erforderlich'] : [],
            ]);
        }

        // Validate cron expression format (5 parts)
        $parts = explode(' ', $expression);
        if (count($parts) !== 5) {
            return JsonResponse::validationError([
                'expression' => ['Ungültiger Cron-Ausdruck. Es werden 5 Felder erwartet (Minute Stunde Tag Monat Wochentag).'],
            ]);
        }

        $id = Uuid::uuid4()->toString();

        $this->db->insert('cron_jobs', [
            'id' => $id,
            'user_id' => $userId,
            'connection_id' => $connectionId,
            'expression' => $expression,
            'command' => $command,
            'description' => $description ?: null,
            'is_active' => $isActive ? 1 : 0,
        ]);

        $job = $this->db->fetchAssociative(
            'SELECT cj.*, c.name as connection_name
             FROM cron_jobs cj
             LEFT JOIN connections c ON cj.connection_id = c.id
             WHERE cj.id = ?',
            [$id]
        );

        $job['is_active'] = (bool) $job['is_active'];
        $job['readable_expression'] = $this->describeCronExpression($job['expression']);

        return JsonResponse::created($job);
    }

    /**
     * Get a single cron job by ID, verify user ownership.
     */
    public function show(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $job = $this->db->fetchAssociative(
            'SELECT cj.*, c.name as connection_name
             FROM cron_jobs cj
             LEFT JOIN connections c ON cj.connection_id = c.id
             WHERE cj.id = ? AND cj.user_id = ?',
            [$id, $userId]
        );

        if (!$job) {
            return JsonResponse::error('Cron-Job nicht gefunden', 404);
        }

        $job['is_active'] = (bool) $job['is_active'];
        $job['readable_expression'] = $this->describeCronExpression($job['expression']);

        return JsonResponse::success($job);
    }

    /**
     * Update a cron job (expression, command, description, connection_id, is_active).
     */
    public function update(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $existing = $this->db->fetchAssociative(
            'SELECT id FROM cron_jobs WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$existing) {
            return JsonResponse::error('Cron-Job nicht gefunden', 404);
        }

        $body = (array) $request->getParsedBody();
        $updates = [];
        $params = [];

        if (isset($body['expression'])) {
            $expression = trim((string) $body['expression']);
            $parts = explode(' ', $expression);
            if (count($parts) !== 5) {
                return JsonResponse::validationError([
                    'expression' => ['Ungültiger Cron-Ausdruck. Es werden 5 Felder erwartet.'],
                ]);
            }
            $updates['expression'] = $expression;
        }

        if (isset($body['command'])) {
            $command = trim((string) $body['command']);
            if ($command === '') {
                return JsonResponse::validationError([
                    'command' => ['Befehl darf nicht leer sein.'],
                ]);
            }
            $updates['command'] = $command;
        }

        if (array_key_exists('description', $body)) {
            $updates['description'] = trim((string) $body['description']) ?: null;
        }

        if (array_key_exists('connection_id', $body)) {
            $updates['connection_id'] = !empty($body['connection_id']) ? (string) $body['connection_id'] : null;
        }

        if (isset($body['is_active'])) {
            $updates['is_active'] = ((bool) $body['is_active']) ? 1 : 0;
        }

        if (!empty($updates)) {
            $this->db->update('cron_jobs', $updates, ['id' => $id]);
        }

        $job = $this->db->fetchAssociative(
            'SELECT cj.*, c.name as connection_name
             FROM cron_jobs cj
             LEFT JOIN connections c ON cj.connection_id = c.id
             WHERE cj.id = ?',
            [$id]
        );

        $job['is_active'] = (bool) $job['is_active'];
        $job['readable_expression'] = $this->describeCronExpression($job['expression']);

        return JsonResponse::success($job);
    }

    /**
     * Delete a cron job, verify user ownership.
     */
    public function delete(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $existing = $this->db->fetchAssociative(
            'SELECT id FROM cron_jobs WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$existing) {
            return JsonResponse::error('Cron-Job nicht gefunden', 404);
        }

        $this->db->delete('cron_jobs', ['id' => $id]);

        return JsonResponse::success(null, 'Cron-Job gelöscht');
    }

    /**
     * Toggle is_active boolean for a cron job.
     */
    public function toggle(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $job = $this->db->fetchAssociative(
            'SELECT id, is_active FROM cron_jobs WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$job) {
            return JsonResponse::error('Cron-Job nicht gefunden', 404);
        }

        $newState = $job['is_active'] ? 0 : 1;
        $this->db->update('cron_jobs', ['is_active' => $newState], ['id' => $id]);

        $updated = $this->db->fetchAssociative(
            'SELECT cj.*, c.name as connection_name
             FROM cron_jobs cj
             LEFT JOIN connections c ON cj.connection_id = c.id
             WHERE cj.id = ?',
            [$id]
        );

        $updated['is_active'] = (bool) $updated['is_active'];
        $updated['readable_expression'] = $this->describeCronExpression($updated['expression']);

        return JsonResponse::success($updated);
    }

    /**
     * Paginated history for a cron job from cron_job_history table.
     */
    public function history(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        // Verify ownership
        $job = $this->db->fetchAssociative(
            'SELECT id FROM cron_jobs WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$job) {
            return JsonResponse::error('Cron-Job nicht gefunden', 404);
        }

        $queryParams = $request->getQueryParams();
        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM cron_job_history WHERE cron_job_id = ?',
            [$id]
        );

        $history = $this->db->fetchAllAssociative(
            'SELECT id, cron_job_id, started_at, finished_at, exit_code, stdout, stderr, duration_ms
             FROM cron_job_history
             WHERE cron_job_id = ?
             ORDER BY started_at DESC
             LIMIT ? OFFSET ?',
            [$id, $perPage, $offset],
            [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT]
        );

        return JsonResponse::paginated($history, $total, $page, $perPage);
    }

    /**
     * Parse a cron expression and return human-readable description + next 5 run times.
     */
    public function parseExpression(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $expression = trim((string) ($body['expression'] ?? ''));

        if ($expression === '') {
            return JsonResponse::validationError([
                'expression' => ['Cron-Ausdruck ist erforderlich'],
            ]);
        }

        $parts = explode(' ', $expression);
        if (count($parts) !== 5) {
            return JsonResponse::validationError([
                'expression' => ['Ungültiger Cron-Ausdruck. Es werden 5 Felder erwartet.'],
            ]);
        }

        $description = $this->describeCronExpression($expression);
        $nextRuns = $this->calculateNextRuns($expression, 5);

        return JsonResponse::success([
            'description' => $description,
            'next_runs' => $nextRuns,
        ]);
    }

    /**
     * Describe a cron expression in human-readable German text.
     */
    private function describeCronExpression(string $expression): string
    {
        $parts = explode(' ', trim($expression));
        if (count($parts) !== 5) {
            return $expression;
        }

        [$min, $hour, $dom, $month, $dow] = $parts;

        if ($expression === '* * * * *') {
            return 'Jede Minute';
        }

        if ($min === '0' && $hour === '*') {
            return 'Jede Stunde';
        }

        if ($min === '0' && $hour === '0' && $dom === '*' && $month === '*' && $dow === '*') {
            return 'Taeglich um Mitternacht';
        }

        if ($min !== '*' && $hour !== '*' && $dom === '*' && $month === '*' && $dow === '*') {
            return sprintf(
                'Taeglich um %s:%s Uhr',
                str_pad($hour, 2, '0', STR_PAD_LEFT),
                str_pad($min, 2, '0', STR_PAD_LEFT)
            );
        }

        if ($dow !== '*' && $dom === '*') {
            $days = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
            $dayName = $days[(int) $dow] ?? $dow;
            return sprintf(
                'Jeden %s um %s:%s Uhr',
                $dayName,
                str_pad($hour, 2, '0', STR_PAD_LEFT),
                str_pad($min, 2, '0', STR_PAD_LEFT)
            );
        }

        if ($dom !== '*' && $month === '*' && $dow === '*') {
            return sprintf(
                'Monatlich am %s. um %s:%s Uhr',
                $dom,
                str_pad($hour, 2, '0', STR_PAD_LEFT),
                str_pad($min, 2, '0', STR_PAD_LEFT)
            );
        }

        // Check for interval patterns like */5
        if (str_starts_with($min, '*/') && $hour === '*') {
            $interval = substr($min, 2);
            return sprintf('Alle %s Minuten', $interval);
        }

        if (str_starts_with($hour, '*/') && $min === '0') {
            $interval = substr($hour, 2);
            return sprintf('Alle %s Stunden', $interval);
        }

        return $expression;
    }

    /**
     * Calculate the next N theoretical run times for a cron expression.
     */
    private function calculateNextRuns(string $expression, int $count): array
    {
        $parts = explode(' ', trim($expression));
        if (count($parts) !== 5) {
            return [];
        }

        [$minExpr, $hourExpr, $domExpr, $monthExpr, $dowExpr] = $parts;

        $runs = [];
        $current = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin'));
        // Start from next minute
        $current = $current->setTime(
            (int) $current->format('H'),
            (int) $current->format('i') + 1,
            0
        );

        $maxIterations = 525600; // 1 year in minutes

        for ($i = 0; $i < $maxIterations && count($runs) < $count; $i++) {
            $minute = (int) $current->format('i');
            $hour = (int) $current->format('G');
            $day = (int) $current->format('j');
            $month = (int) $current->format('n');
            $dow = (int) $current->format('w');

            if (
                $this->matchesCronField($minExpr, $minute, 0, 59) &&
                $this->matchesCronField($hourExpr, $hour, 0, 23) &&
                $this->matchesCronField($domExpr, $day, 1, 31) &&
                $this->matchesCronField($monthExpr, $month, 1, 12) &&
                $this->matchesCronField($dowExpr, $dow, 0, 7)
            ) {
                $runs[] = $current->format('Y-m-d H:i');
            }

            $current = $current->modify('+1 minute');
        }

        return $runs;
    }

    /**
     * Check if a value matches a cron field expression.
     */
    private function matchesCronField(string $expr, int $value, int $min, int $max): bool
    {
        if ($expr === '*') {
            return true;
        }

        // Handle comma-separated values
        $segments = explode(',', $expr);
        foreach ($segments as $segment) {
            $segment = trim($segment);

            // Handle step values like */5 or 1-30/5
            if (str_contains($segment, '/')) {
                [$range, $step] = explode('/', $segment, 2);
                $step = (int) $step;
                if ($step <= 0) {
                    continue;
                }

                if ($range === '*') {
                    if (($value - $min) % $step === 0) {
                        return true;
                    }
                } elseif (str_contains($range, '-')) {
                    [$start, $end] = explode('-', $range, 2);
                    $start = (int) $start;
                    $end = (int) $end;
                    if ($value >= $start && $value <= $end && ($value - $start) % $step === 0) {
                        return true;
                    }
                }
                continue;
            }

            // Handle range like 1-5
            if (str_contains($segment, '-')) {
                [$start, $end] = explode('-', $segment, 2);
                if ($value >= (int) $start && $value <= (int) $end) {
                    return true;
                }
                continue;
            }

            // Handle exact value
            if ((int) $segment === $value) {
                return true;
            }

            // Handle day-of-week 7 = Sunday (same as 0)
            if ($max === 7 && (int) $segment === 7 && $value === 0) {
                return true;
            }
        }

        return false;
    }
}
