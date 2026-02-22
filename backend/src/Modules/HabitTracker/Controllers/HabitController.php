<?php

declare(strict_types=1);

namespace App\Modules\HabitTracker\Controllers;

use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;

class HabitController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $habits = $this->db->fetchAllAssociative(
            'SELECT * FROM habits WHERE user_id = ? ORDER BY created_at ASC',
            [$userId]
        );

        $today = date('Y-m-d');

        foreach ($habits as &$habit) {
            $habit['is_active'] = (bool) $habit['is_active'];
            $habit['streak'] = $this->calculateStreak($habit['id'], $habit['frequency']);
            $habit['completed_today'] = $this->isCompletedOn($habit['id'], $today);
            $habit['completions_this_week'] = $this->getCompletionsCount($habit['id'], date('Y-m-d', strtotime('monday this week')), $today);
        }

        return JsonResponse::success($habits);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        $name = trim($body['name'] ?? '');
        if (empty($name)) {
            return JsonResponse::validationError(['name' => 'Name ist erforderlich']);
        }

        $id = Uuid::uuid4()->toString();
        $now = date('Y-m-d H:i:s');

        $this->db->insert('habits', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $name,
            'description' => $body['description'] ?? null,
            'frequency' => in_array($body['frequency'] ?? '', ['daily', 'weekly', 'monthly']) ? $body['frequency'] : 'daily',
            'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', $body['color'] ?? '') ? $body['color'] : '#3B82F6',
            'icon' => $body['icon'] ?? 'sparkles',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $habit = $this->db->fetchAssociative('SELECT * FROM habits WHERE id = ?', [$id]);
        $habit['is_active'] = (bool) $habit['is_active'];
        $habit['streak'] = 0;
        $habit['completed_today'] = false;
        $habit['completions_this_week'] = 0;

        return JsonResponse::created($habit);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $habitId = $args['id'];
        $body = (array) $request->getParsedBody();

        $habit = $this->db->fetchAssociative('SELECT * FROM habits WHERE id = ? AND user_id = ?', [$habitId, $userId]);
        if (!$habit) {
            return JsonResponse::notFound('Habit nicht gefunden');
        }

        $updates = ['updated_at' => date('Y-m-d H:i:s')];

        if (isset($body['name'])) {
            $name = trim($body['name']);
            if (empty($name)) {
                return JsonResponse::validationError(['name' => 'Name ist erforderlich']);
            }
            $updates['name'] = $name;
        }
        if (array_key_exists('description', $body)) $updates['description'] = $body['description'];
        if (isset($body['frequency']) && in_array($body['frequency'], ['daily', 'weekly', 'monthly'])) {
            $updates['frequency'] = $body['frequency'];
        }
        if (isset($body['color']) && preg_match('/^#[0-9A-Fa-f]{6}$/', $body['color'])) {
            $updates['color'] = $body['color'];
        }
        if (isset($body['icon'])) $updates['icon'] = $body['icon'];
        if (isset($body['is_active'])) $updates['is_active'] = $body['is_active'] ? 1 : 0;

        $this->db->update('habits', $updates, ['id' => $habitId]);

        $updated = $this->db->fetchAssociative('SELECT * FROM habits WHERE id = ?', [$habitId]);
        $updated['is_active'] = (bool) $updated['is_active'];
        $updated['streak'] = $this->calculateStreak($habitId, $updated['frequency']);
        $updated['completed_today'] = $this->isCompletedOn($habitId, date('Y-m-d'));
        $updated['completions_this_week'] = $this->getCompletionsCount($habitId, date('Y-m-d', strtotime('monday this week')), date('Y-m-d'));

        return JsonResponse::success($updated);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $habitId = $args['id'];

        $habit = $this->db->fetchAssociative('SELECT id FROM habits WHERE id = ? AND user_id = ?', [$habitId, $userId]);
        if (!$habit) {
            return JsonResponse::notFound('Habit nicht gefunden');
        }

        $this->db->delete('habit_completions', ['habit_id' => $habitId]);
        $this->db->delete('habits', ['id' => $habitId]);

        return JsonResponse::noContent();
    }

    public function complete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $habitId = $args['id'];
        $body = (array) $request->getParsedBody();

        $habit = $this->db->fetchAssociative('SELECT id FROM habits WHERE id = ? AND user_id = ?', [$habitId, $userId]);
        if (!$habit) {
            return JsonResponse::notFound('Habit nicht gefunden');
        }

        $date = $body['date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        // Toggle: if already completed, remove completion
        $existing = $this->db->fetchAssociative(
            'SELECT id FROM habit_completions WHERE habit_id = ? AND completed_at = ?',
            [$habitId, $date]
        );

        if ($existing) {
            $this->db->delete('habit_completions', ['id' => $existing['id']]);
            return JsonResponse::success(['completed' => false, 'date' => $date]);
        }

        $this->db->insert('habit_completions', [
            'id' => Uuid::uuid4()->toString(),
            'habit_id' => $habitId,
            'user_id' => $userId,
            'completed_at' => $date,
            'notes' => $body['notes'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return JsonResponse::success(['completed' => true, 'date' => $date]);
    }

    public function getCompletions(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $habitId = $args['id'];
        $params = $request->getQueryParams();

        $habit = $this->db->fetchAssociative('SELECT id FROM habits WHERE id = ? AND user_id = ?', [$habitId, $userId]);
        if (!$habit) {
            return JsonResponse::notFound('Habit nicht gefunden');
        }

        $from = $params['from'] ?? date('Y-m-01');
        $to = $params['to'] ?? date('Y-m-d');

        $completions = $this->db->fetchAllAssociative(
            'SELECT completed_at, notes FROM habit_completions WHERE habit_id = ? AND completed_at BETWEEN ? AND ? ORDER BY completed_at DESC',
            [$habitId, $from, $to]
        );

        return JsonResponse::success($completions);
    }

    public function getStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $today = date('Y-m-d');
        $weekStart = date('Y-m-d', strtotime('monday this week'));

        $habits = $this->db->fetchAllAssociative(
            'SELECT id, name, frequency, color FROM habits WHERE user_id = ? AND is_active = 1',
            [$userId]
        );

        $stats = [
            'total_habits' => count($habits),
            'completed_today' => 0,
            'longest_streak' => 0,
            'habits' => [],
        ];

        foreach ($habits as $habit) {
            $completedToday = $this->isCompletedOn($habit['id'], $today);
            $streak = $this->calculateStreak($habit['id'], $habit['frequency']);

            if ($completedToday) $stats['completed_today']++;
            if ($streak > $stats['longest_streak']) $stats['longest_streak'] = $streak;

            $stats['habits'][] = [
                'id' => $habit['id'],
                'name' => $habit['name'],
                'color' => $habit['color'],
                'streak' => $streak,
                'completed_today' => $completedToday,
            ];
        }

        return JsonResponse::success($stats);
    }

    private function isCompletedOn(string $habitId, string $date): bool
    {
        $result = $this->db->fetchOne(
            'SELECT COUNT(*) FROM habit_completions WHERE habit_id = ? AND completed_at = ?',
            [$habitId, $date]
        );
        return (int) $result > 0;
    }

    private function getCompletionsCount(string $habitId, string $from, string $to): int
    {
        $result = $this->db->fetchOne(
            'SELECT COUNT(*) FROM habit_completions WHERE habit_id = ? AND completed_at BETWEEN ? AND ?',
            [$habitId, $from, $to]
        );
        return (int) $result;
    }

    private function calculateStreak(string $habitId, string $frequency): int
    {
        $completions = $this->db->fetchAllAssociative(
            'SELECT completed_at FROM habit_completions WHERE habit_id = ? ORDER BY completed_at DESC',
            [$habitId]
        );

        if (empty($completions)) return 0;

        $streak = 0;
        $today = new \DateTime('today');
        $checkDate = clone $today;
        $interval = $frequency === 'weekly' ? new \DateInterval('P7D') : new \DateInterval('P1D');

        $completionDates = array_column($completions, 'completed_at');

        // Allow yesterday or today as starting point
        $yesterday = (clone $today)->sub(new \DateInterval('P1D'))->format('Y-m-d');
        if (!in_array($today->format('Y-m-d'), $completionDates) && !in_array($yesterday, $completionDates)) {
            return 0;
        }

        while (true) {
            $dateStr = $checkDate->format('Y-m-d');
            if (in_array($dateStr, $completionDates)) {
                $streak++;
                $checkDate->sub($interval);
            } else {
                break;
            }

            if ($streak > 365) break; // Safety limit
        }

        return $streak;
    }
}
