<?php

declare(strict_types=1);

namespace App\Modules\NotificationRules\Controllers;

use App\Core\Http\JsonResponse;
use App\Modules\NotificationRules\Services\NotificationRuleEngine;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class NotificationRuleController
{
    public function __construct(
        private readonly Connection $db,
        private readonly NotificationRuleEngine $ruleEngine
    ) {}

    /**
     * List user's notification rules
     */
    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $rules = $this->db->fetchAllAssociative(
            'SELECT * FROM notification_rules WHERE user_id = ? ORDER BY created_at DESC',
            [$userId]
        );

        // Decode JSON fields
        foreach ($rules as &$rule) {
            $rule['conditions'] = json_decode($rule['conditions'] ?? '[]', true);
            $rule['actions'] = json_decode($rule['actions'] ?? '[]', true);
            $rule['is_active'] = (bool) $rule['is_active'];
        }

        return JsonResponse::success($rules);
    }

    /**
     * Create a new notification rule
     */
    public function create(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            return JsonResponse::error('Name is required', 400);
        }

        if (empty($data['trigger_event'])) {
            return JsonResponse::error('Trigger event is required', 400);
        }

        if (empty($data['actions'])) {
            return JsonResponse::error('Actions are required', 400);
        }

        $id = Uuid::uuid4()->toString();

        $this->db->insert('notification_rules', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'trigger_event' => $data['trigger_event'],
            'conditions' => isset($data['conditions']) ? json_encode($data['conditions']) : null,
            'actions' => json_encode($data['actions']),
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $rule = $this->db->fetchAssociative(
            'SELECT * FROM notification_rules WHERE id = ?',
            [$id]
        );

        $rule['conditions'] = json_decode($rule['conditions'] ?? '[]', true);
        $rule['actions'] = json_decode($rule['actions'] ?? '[]', true);
        $rule['is_active'] = (bool) $rule['is_active'];

        return JsonResponse::created($rule);
    }

    /**
     * Get a single notification rule
     */
    public function show(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $rule = $this->db->fetchAssociative(
            'SELECT * FROM notification_rules WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$rule) {
            return JsonResponse::notFound('Rule not found');
        }

        $rule['conditions'] = json_decode($rule['conditions'] ?? '[]', true);
        $rule['actions'] = json_decode($rule['actions'] ?? '[]', true);
        $rule['is_active'] = (bool) $rule['is_active'];

        return JsonResponse::success($rule);
    }

    /**
     * Update a notification rule
     */
    public function update(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        $rule = $this->db->fetchAssociative(
            'SELECT * FROM notification_rules WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$rule) {
            return JsonResponse::notFound('Rule not found');
        }

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['trigger_event'])) {
            $updateData['trigger_event'] = $data['trigger_event'];
        }
        if (array_key_exists('conditions', $data)) {
            $updateData['conditions'] = $data['conditions'] !== null ? json_encode($data['conditions']) : null;
        }
        if (isset($data['actions'])) {
            $updateData['actions'] = json_encode($data['actions']);
        }
        if (isset($data['is_active'])) {
            $updateData['is_active'] = (bool) $data['is_active'];
        }

        if (!empty($updateData)) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $this->db->update('notification_rules', $updateData, ['id' => $id]);
        }

        $rule = $this->db->fetchAssociative(
            'SELECT * FROM notification_rules WHERE id = ?',
            [$id]
        );

        $rule['conditions'] = json_decode($rule['conditions'] ?? '[]', true);
        $rule['actions'] = json_decode($rule['actions'] ?? '[]', true);
        $rule['is_active'] = (bool) $rule['is_active'];

        return JsonResponse::success($rule, 'Updated');
    }

    /**
     * Delete a notification rule
     */
    public function delete(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $rule = $this->db->fetchAssociative(
            'SELECT * FROM notification_rules WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$rule) {
            return JsonResponse::notFound('Rule not found');
        }

        $this->db->delete('notification_rules', ['id' => $id]);

        return JsonResponse::success(null, 'Deleted');
    }

    /**
     * Toggle a rule's is_active status
     */
    public function toggle(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $rule = $this->db->fetchAssociative(
            'SELECT * FROM notification_rules WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$rule) {
            return JsonResponse::notFound('Rule not found');
        }

        $newStatus = !((bool) $rule['is_active']);

        $this->db->update('notification_rules', [
            'is_active' => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        $rule['is_active'] = $newStatus;
        $rule['conditions'] = json_decode($rule['conditions'] ?? '[]', true);
        $rule['actions'] = json_decode($rule['actions'] ?? '[]', true);

        return JsonResponse::success($rule, $newStatus ? 'Rule activated' : 'Rule deactivated');
    }

    /**
     * Fire a test notification through the rule
     */
    public function test(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $rule = $this->db->fetchAssociative(
            'SELECT * FROM notification_rules WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$rule) {
            return JsonResponse::notFound('Rule not found');
        }

        $actions = json_decode($rule['actions'], true);
        $testEventData = [
            'test' => true,
            'triggered_by' => 'manual_test',
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        try {
            foreach ($actions as $action) {
                $type = $action['type'] ?? 'push';

                if ($type === 'push') {
                    $this->ruleEngine->getNotificationService()->create(
                        $userId,
                        $rule['trigger_event'],
                        $action['title'] ?? 'Test: ' . $rule['name'],
                        $action['message'] ?? 'Testregel ausgelöst: ' . $rule['name'],
                        $testEventData
                    );
                }
            }

            return JsonResponse::success([
                'rule_id' => $id,
                'actions_executed' => count($actions),
            ], 'Test notification sent');
        } catch (\Exception $e) {
            return JsonResponse::error('Test failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get execution history for a specific rule (paginated)
     */
    public function history(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        // Verify the rule belongs to the user
        $rule = $this->db->fetchAssociative(
            'SELECT * FROM notification_rules WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$rule) {
            return JsonResponse::notFound('Rule not found');
        }

        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM notification_rule_history WHERE rule_id = ?',
            [$id]
        );

        $history = $this->db->fetchAllAssociative(
            'SELECT * FROM notification_rule_history WHERE rule_id = ? ORDER BY triggered_at DESC LIMIT ? OFFSET ?',
            [$id, $perPage, $offset],
            [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT]
        );

        foreach ($history as &$entry) {
            $entry['event_data'] = json_decode($entry['event_data'] ?? '[]', true);
        }

        return JsonResponse::paginated($history, $total, $page, $perPage);
    }

    /**
     * Get available trigger events for the UI
     */
    public function availableEvents(Request $request, Response $response): Response
    {
        $events = $this->ruleEngine->getAvailableEvents();

        return JsonResponse::success($events);
    }
}
