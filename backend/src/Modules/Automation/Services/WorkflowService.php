<?php

declare(strict_types=1);

namespace App\Modules\Automation\Services;

use App\Core\Services\MailService;
use App\Modules\Notifications\Services\PushNotificationService;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

class WorkflowService
{
    // Available trigger types
    public const TRIGGER_TYPES = [
        'schedule' => 'Zeitplan (Cron)',
        'event' => 'Ereignis',
        'webhook' => 'Webhook',
        'manual' => 'Manuell',
    ];

    // Available events for event triggers
    public const EVENTS = [
        'task.created' => 'Aufgabe erstellt',
        'task.completed' => 'Aufgabe erledigt',
        'task.due' => 'Aufgabe fällig',
        'document.created' => 'Dokument erstellt',
        'document.updated' => 'Dokument aktualisiert',
        'calendar.event_starting' => 'Termin beginnt bald',
        'uptime.down' => 'Monitor offline',
        'uptime.up' => 'Monitor wieder online',
        'backup.completed' => 'Backup abgeschlossen',
        'backup.failed' => 'Backup fehlgeschlagen',
        'ticket.created' => 'Ticket erstellt',
        'ticket.updated' => 'Ticket aktualisiert',
        'recurring.due' => 'Wiederkehrende Aufgabe fällig',
    ];

    // Available action types
    public const ACTION_TYPES = [
        'send_notification' => ['name' => 'Push-Benachrichtigung senden', 'config' => ['title', 'body', 'url']],
        'send_email' => ['name' => 'E-Mail senden', 'config' => ['to', 'subject', 'body']],
        'create_task' => ['name' => 'Aufgabe erstellen', 'config' => ['list_id', 'content', 'due_date']],
        'create_document' => ['name' => 'Dokument erstellen', 'config' => ['title', 'content', 'folder_id']],
        'create_calendar_event' => ['name' => 'Kalendereintrag erstellen', 'config' => ['title', 'start', 'end']],
        'http_request' => ['name' => 'HTTP-Request', 'config' => ['method', 'url', 'headers', 'body']],
        'trigger_n8n' => ['name' => 'n8n Webhook auslösen', 'config' => ['webhook_url', 'payload']],
        'delay' => ['name' => 'Verzögerung', 'config' => ['seconds']],
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly ?PushNotificationService $pushService = null,
        private readonly ?MailService $mailService = null
    ) {}

    // ==================== Workflow CRUD ====================

    /**
     * Get all workflows for user
     */
    public function getWorkflows(string $userId): array
    {
        $workflows = $this->db->fetchAllAssociative(
            'SELECT * FROM workflows WHERE user_id = ? ORDER BY created_at DESC',
            [$userId]
        );

        foreach ($workflows as &$workflow) {
            $workflow['trigger_config'] = json_decode($workflow['trigger_config'], true);
            $workflow['actions'] = $this->getWorkflowActions($workflow['id']);
            $workflow['conditions'] = $this->getWorkflowConditions($workflow['id']);
        }

        return $workflows;
    }

    /**
     * Get a single workflow
     */
    public function getWorkflow(string $id, string $userId): ?array
    {
        $workflow = $this->db->fetchAssociative(
            'SELECT * FROM workflows WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$workflow) {
            return null;
        }

        $workflow['trigger_config'] = json_decode($workflow['trigger_config'], true);
        $workflow['actions'] = $this->getWorkflowActions($id);
        $workflow['conditions'] = $this->getWorkflowConditions($id);

        return $workflow;
    }

    /**
     * Create a new workflow
     */
    public function createWorkflow(string $userId, array $data): array
    {
        $id = Uuid::uuid4()->toString();

        $this->db->insert('workflows', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_enabled' => $data['is_enabled'] ?? true,
            'trigger_type' => $data['trigger_type'],
            'trigger_config' => json_encode($data['trigger_config'] ?? []),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Add actions
        if (!empty($data['actions'])) {
            foreach ($data['actions'] as $index => $action) {
                $this->addAction($id, $action, $index);
            }
        }

        // Add conditions
        if (!empty($data['conditions'])) {
            foreach ($data['conditions'] as $condition) {
                $this->addCondition($id, $condition);
            }
        }

        return $this->getWorkflow($id, $userId);
    }

    /**
     * Update a workflow
     */
    public function updateWorkflow(string $id, string $userId, array $data): bool
    {
        $workflow = $this->db->fetchAssociative(
            'SELECT id FROM workflows WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$workflow) {
            return false;
        }

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];
        $allowedFields = ['name', 'description', 'is_enabled', 'trigger_type'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        if (isset($data['trigger_config'])) {
            $updateData['trigger_config'] = json_encode($data['trigger_config']);
        }

        $this->db->update('workflows', $updateData, ['id' => $id]);

        // Update actions if provided
        if (isset($data['actions'])) {
            $this->db->delete('workflow_actions', ['workflow_id' => $id]);
            foreach ($data['actions'] as $index => $action) {
                $this->addAction($id, $action, $index);
            }
        }

        // Update conditions if provided
        if (isset($data['conditions'])) {
            $this->db->delete('workflow_conditions', ['workflow_id' => $id]);
            foreach ($data['conditions'] as $condition) {
                $this->addCondition($id, $condition);
            }
        }

        return true;
    }

    /**
     * Delete a workflow
     */
    public function deleteWorkflow(string $id, string $userId): bool
    {
        $result = $this->db->delete('workflows', [
            'id' => $id,
            'user_id' => $userId,
        ]);

        return $result > 0;
    }

    /**
     * Toggle workflow enabled status
     */
    public function toggleWorkflow(string $id, string $userId): ?bool
    {
        $workflow = $this->db->fetchAssociative(
            'SELECT is_enabled FROM workflows WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$workflow) {
            return null;
        }

        $newStatus = !$workflow['is_enabled'];
        $this->db->update('workflows', [
            'is_enabled' => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        return $newStatus;
    }

    // ==================== Workflow Actions ====================

    /**
     * Get actions for a workflow
     */
    private function getWorkflowActions(string $workflowId): array
    {
        $actions = $this->db->fetchAllAssociative(
            'SELECT * FROM workflow_actions WHERE workflow_id = ? ORDER BY position ASC',
            [$workflowId]
        );

        foreach ($actions as &$action) {
            $action['action_config'] = json_decode($action['action_config'], true);
        }

        return $actions;
    }

    /**
     * Add an action to a workflow
     */
    private function addAction(string $workflowId, array $action, int $position): string
    {
        $id = Uuid::uuid4()->toString();

        $this->db->insert('workflow_actions', [
            'id' => $id,
            'workflow_id' => $workflowId,
            'position' => $position,
            'action_type' => $action['action_type'],
            'action_config' => json_encode($action['config'] ?? []),
            'continue_on_error' => $action['continue_on_error'] ?? false,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    // ==================== Workflow Conditions ====================

    /**
     * Get conditions for a workflow
     */
    private function getWorkflowConditions(string $workflowId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT * FROM workflow_conditions WHERE workflow_id = ?',
            [$workflowId]
        );
    }

    /**
     * Add a condition to a workflow
     */
    private function addCondition(string $workflowId, array $condition): string
    {
        $id = Uuid::uuid4()->toString();

        $this->db->insert('workflow_conditions', [
            'id' => $id,
            'workflow_id' => $workflowId,
            'field' => $condition['field'],
            'operator' => $condition['operator'],
            'value' => $condition['value'] ?? null,
            'logical_operator' => $condition['logical_operator'] ?? 'and',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    // ==================== Workflow Execution ====================

    /**
     * Execute a workflow manually
     */
    public function executeWorkflow(string $id, string $userId, array $triggerData = []): array
    {
        $workflow = $this->getWorkflow($id, $userId);

        if (!$workflow) {
            throw new \InvalidArgumentException('Workflow not found');
        }

        return $this->runWorkflow($workflow, $triggerData);
    }

    /**
     * Run a workflow with given trigger data
     */
    public function runWorkflow(array $workflow, array $triggerData = []): array
    {
        $runId = Uuid::uuid4()->toString();
        $startTime = microtime(true);

        // Create run record
        $this->db->insert('workflow_runs', [
            'id' => $runId,
            'workflow_id' => $workflow['id'],
            'trigger_data' => json_encode($triggerData),
            'status' => 'running',
            'started_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $results = [];
        $hasErrors = false;
        $allSuccess = true;

        // Check conditions
        if (!$this->checkConditions($workflow['conditions'] ?? [], $triggerData)) {
            $this->db->update('workflow_runs', [
                'status' => 'success',
                'completed_at' => date('Y-m-d H:i:s'),
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
            ], ['id' => $runId]);

            return ['run_id' => $runId, 'status' => 'skipped', 'reason' => 'conditions_not_met'];
        }

        // Execute each action
        foreach ($workflow['actions'] as $action) {
            $actionResult = $this->executeAction($runId, $action, $triggerData);
            $results[] = $actionResult;

            if ($actionResult['status'] === 'failed') {
                $hasErrors = true;
                $allSuccess = false;

                if (!$action['continue_on_error']) {
                    break;
                }
            }
        }

        $status = $allSuccess ? 'success' : ($hasErrors ? 'partial' : 'failed');
        $duration = (int) ((microtime(true) - $startTime) * 1000);

        // Update run record
        $this->db->update('workflow_runs', [
            'status' => $status,
            'completed_at' => date('Y-m-d H:i:s'),
            'duration_ms' => $duration,
        ], ['id' => $runId]);

        // Update workflow stats
        $this->db->executeStatement(
            'UPDATE workflows SET run_count = run_count + 1, last_run_at = NOW(), last_run_status = ? WHERE id = ?',
            [$status, $workflow['id']]
        );

        return [
            'run_id' => $runId,
            'status' => $status,
            'duration_ms' => $duration,
            'results' => $results,
        ];
    }

    /**
     * Execute a single action
     */
    private function executeAction(string $runId, array $action, array $context): array
    {
        $logId = Uuid::uuid4()->toString();
        $startTime = microtime(true);

        // Create action log
        $this->db->insert('workflow_action_logs', [
            'id' => $logId,
            'run_id' => $runId,
            'action_id' => $action['id'],
            'status' => 'running',
            'input_data' => json_encode($action['action_config']),
            'started_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        try {
            $config = $this->replaceVariables($action['action_config'], $context);
            $output = $this->performAction($action['action_type'], $config, $context);

            $duration = (int) ((microtime(true) - $startTime) * 1000);

            $this->db->update('workflow_action_logs', [
                'status' => 'success',
                'output_data' => json_encode($output),
                'completed_at' => date('Y-m-d H:i:s'),
                'duration_ms' => $duration,
            ], ['id' => $logId]);

            return ['status' => 'success', 'action_id' => $action['id'], 'output' => $output];

        } catch (\Exception $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            $this->db->update('workflow_action_logs', [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => date('Y-m-d H:i:s'),
                'duration_ms' => $duration,
            ], ['id' => $logId]);

            return ['status' => 'failed', 'action_id' => $action['id'], 'error' => $e->getMessage()];
        }
    }

    /**
     * Perform a specific action
     */
    private function performAction(string $type, array $config, array $context): array
    {
        switch ($type) {
            case 'send_notification':
                return $this->actionSendNotification($config, $context);

            case 'send_email':
                return $this->actionSendEmail($config, $context);

            case 'create_task':
                return $this->actionCreateTask($config, $context);

            case 'create_document':
                return $this->actionCreateDocument($config, $context);

            case 'create_calendar_event':
                return $this->actionCreateCalendarEvent($config, $context);

            case 'http_request':
                return $this->actionHttpRequest($config);

            case 'trigger_n8n':
                return $this->actionTriggerN8n($config);

            case 'delay':
                return $this->actionDelay($config);

            default:
                throw new \InvalidArgumentException("Unknown action type: {$type}");
        }
    }

    /**
     * Send notification action
     */
    private function actionSendNotification(array $config, array $context): array
    {
        $userId = $context['user_id'] ?? null;

        if (!$userId || !$this->pushService) {
            return ['skipped' => true, 'reason' => 'no_user_or_push_service'];
        }

        $result = $this->pushService->sendToUser(
            $userId,
            $config['title'] ?? 'Automation',
            $config['body'] ?? '',
            'system',
            null,
            $config['url'] ?? null
        );

        return ['sent' => $result['sent'], 'failed' => $result['failed']];
    }

    /**
     * Create task action
     */
    private function actionCreateTask(array $config, array $context): array
    {
        $userId = $context['user_id'] ?? null;
        $listId = $config['list_id'] ?? null;

        if (!$userId || !$listId) {
            return ['skipped' => true, 'reason' => 'missing_user_or_list'];
        }

        $id = Uuid::uuid4()->toString();

        $this->db->insert('tasks', [
            'id' => $id,
            'list_id' => $listId,
            'content' => $config['content'] ?? 'New Task',
            'is_completed' => false,
            'due_date' => $config['due_date'] ?? null,
            'position' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['task_id' => $id];
    }

    /**
     * Send email action
     */
    private function actionSendEmail(array $config, array $context): array
    {
        if (!$this->mailService) {
            return ['skipped' => true, 'reason' => 'mail_service_unavailable'];
        }

        $to = $config['to'] ?? ($context['user_email'] ?? null);
        if (!$to) {
            return ['skipped' => true, 'reason' => 'no_recipient'];
        }

        $this->mailService->sendSystemMail(
            $to,
            $config['subject'] ?? 'Automation',
            $config['body'] ?? ''
        );

        return ['sent' => true, 'to' => $to];
    }

    /**
     * Create document action
     */
    private function actionCreateDocument(array $config, array $context): array
    {
        $userId = $context['user_id'] ?? null;
        if (!$userId) {
            return ['skipped' => true, 'reason' => 'missing_user'];
        }

        $id = Uuid::uuid4()->toString();
        $this->db->insert('documents', [
            'id' => $id,
            'user_id' => $userId,
            'folder_id' => $config['folder_id'] ?? null,
            'title' => $config['title'] ?? 'Neues Dokument',
            'content' => $config['content'] ?? '',
            'format' => 'markdown',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['document_id' => $id];
    }

    /**
     * Create calendar event action
     */
    private function actionCreateCalendarEvent(array $config, array $context): array
    {
        $userId = $context['user_id'] ?? null;
        if (!$userId) {
            return ['skipped' => true, 'reason' => 'missing_user'];
        }

        $id = Uuid::uuid4()->toString();
        $this->db->insert('calendar_events', [
            'id' => $id,
            'user_id' => $userId,
            'title' => $config['title'] ?? 'Neuer Termin',
            'description' => $config['description'] ?? null,
            'start_date' => $config['start'] ?? date('Y-m-d H:i:s'),
            'end_date' => $config['end'] ?? null,
            'all_day' => 0,
            'color' => 'primary',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['event_id' => $id];
    }

    /**
     * HTTP request action
     */
    private function actionHttpRequest(array $config): array
    {
        $method = strtoupper($config['method'] ?? 'GET');
        $url = $config['url'] ?? '';
        $headers = $config['headers'] ?? [];
        $body = $config['body'] ?? null;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if (!empty($headers)) {
            $headerLines = [];
            foreach ($headers as $key => $value) {
                $headerLines[] = "{$key}: {$value}";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
        }

        if ($body && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body) : $body);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("HTTP request failed: {$error}");
        }

        return [
            'status_code' => $httpCode,
            'response' => $response,
        ];
    }

    /**
     * Trigger n8n webhook action
     */
    private function actionTriggerN8n(array $config): array
    {
        $webhookUrl = $config['webhook_url'] ?? '';

        if (empty($webhookUrl) || !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid n8n webhook URL');
        }

        $payload = $config['payload'] ?? '{}';
        if (is_string($payload)) {
            $payloadData = json_decode($payload, true) ?? [];
        } else {
            $payloadData = $payload;
        }

        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payloadData),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error        = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("n8n webhook request failed: {$error}");
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException("n8n webhook returned HTTP {$httpCode}");
        }

        return [
            'status_code' => $httpCode,
            'response'    => $responseBody,
        ];
    }

    /**
     * Delay action
     */
    private function actionDelay(array $config): array
    {
        $seconds = (int) ($config['seconds'] ?? 1);
        $seconds = min(60, max(1, $seconds)); // Limit to 1-60 seconds

        sleep($seconds);

        return ['delayed' => $seconds];
    }

    // ==================== Helpers ====================

    /**
     * Check workflow conditions
     */
    private function checkConditions(array $conditions, array $context): bool
    {
        if (empty($conditions)) {
            return true;
        }

        $result = true;
        $previousOp = 'and';

        foreach ($conditions as $condition) {
            $fieldValue = $this->getNestedValue($context, $condition['field']);
            $conditionResult = $this->evaluateCondition($fieldValue, $condition['operator'], $condition['value']);

            if ($previousOp === 'and') {
                $result = $result && $conditionResult;
            } else {
                $result = $result || $conditionResult;
            }

            $previousOp = $condition['logical_operator'] ?? 'and';
        }

        return $result;
    }

    /**
     * Evaluate a single condition
     */
    private function evaluateCondition($value, string $operator, $compareValue): bool
    {
        switch ($operator) {
            case 'equals':
                return $value == $compareValue;
            case 'not_equals':
                return $value != $compareValue;
            case 'contains':
                return str_contains((string) $value, (string) $compareValue);
            case 'not_contains':
                return !str_contains((string) $value, (string) $compareValue);
            case 'gt':
                return $value > $compareValue;
            case 'lt':
                return $value < $compareValue;
            case 'gte':
                return $value >= $compareValue;
            case 'lte':
                return $value <= $compareValue;
            case 'is_empty':
                return empty($value);
            case 'not_empty':
                return !empty($value);
            default:
                return false;
        }
    }

    /**
     * Replace {{variables}} in config
     */
    private function replaceVariables(array $config, array $context): array
    {
        array_walk_recursive($config, function (&$value) use ($context) {
            if (is_string($value)) {
                $value = preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) use ($context) {
                    return $this->getNestedValue($context, trim($matches[1])) ?? $matches[0];
                }, $value);
            }
        });

        return $config;
    }

    /**
     * Get nested array value
     */
    private function getNestedValue(array $array, string $path)
    {
        $keys = explode('.', $path);
        $value = $array;

        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    // ==================== Templates ====================

    /**
     * Get workflow templates
     */
    public function getTemplates(?string $category = null): array
    {
        $sql = 'SELECT * FROM workflow_templates';
        $params = [];

        if ($category) {
            $sql .= ' WHERE category = ?';
            $params[] = $category;
        }

        $sql .= ' ORDER BY is_featured DESC, use_count DESC';

        $templates = $this->db->fetchAllAssociative($sql, $params);

        foreach ($templates as &$template) {
            $template['trigger_config'] = json_decode($template['trigger_config'], true);
            $template['actions'] = json_decode($template['actions'], true);
        }

        return $templates;
    }

    /**
     * Create workflow from template
     */
    public function createFromTemplate(string $templateId, string $userId): array
    {
        $template = $this->db->fetchAssociative(
            'SELECT * FROM workflow_templates WHERE id = ?',
            [$templateId]
        );

        if (!$template) {
            throw new \InvalidArgumentException('Template not found');
        }

        // Increment use count
        $this->db->executeStatement(
            'UPDATE workflow_templates SET use_count = use_count + 1 WHERE id = ?',
            [$templateId]
        );

        $data = [
            'name' => $template['name'],
            'description' => $template['description'],
            'trigger_type' => $template['trigger_type'],
            'trigger_config' => json_decode($template['trigger_config'], true),
            'actions' => json_decode($template['actions'], true),
        ];

        return $this->createWorkflow($userId, $data);
    }

    // ==================== Run History ====================

    /**
     * Get workflow run history
     */
    public function getRunHistory(string $workflowId, string $userId, int $limit = 20): array
    {
        // Verify ownership
        $workflow = $this->db->fetchAssociative(
            'SELECT id FROM workflows WHERE id = ? AND user_id = ?',
            [$workflowId, $userId]
        );

        if (!$workflow) {
            return [];
        }

        $runs = $this->db->fetchAllAssociative(
            'SELECT * FROM workflow_runs WHERE workflow_id = ? ORDER BY started_at DESC LIMIT ?',
            [$workflowId, $limit],
            [\PDO::PARAM_STR, \PDO::PARAM_INT]
        );

        foreach ($runs as &$run) {
            $run['trigger_data'] = json_decode($run['trigger_data'], true);
        }

        return $runs;
    }

    /**
     * Get run details with action logs
     */
    public function getRunDetails(string $runId, string $userId): ?array
    {
        $run = $this->db->fetchAssociative(
            'SELECT r.*, w.user_id FROM workflow_runs r
             JOIN workflows w ON r.workflow_id = w.id
             WHERE r.id = ? AND w.user_id = ?',
            [$runId, $userId]
        );

        if (!$run) {
            return null;
        }

        $run['trigger_data'] = json_decode($run['trigger_data'], true);
        $run['action_logs'] = $this->db->fetchAllAssociative(
            'SELECT * FROM workflow_action_logs WHERE run_id = ? ORDER BY started_at ASC',
            [$runId]
        );

        foreach ($run['action_logs'] as &$log) {
            $log['input_data'] = json_decode($log['input_data'], true);
            $log['output_data'] = json_decode($log['output_data'], true);
        }

        return $run;
    }

    /**
     * Get available triggers and actions for UI
     */
    public function getAvailableOptions(): array
    {
        return [
            'trigger_types' => self::TRIGGER_TYPES,
            'events' => self::EVENTS,
            'action_types' => self::ACTION_TYPES,
        ];
    }
}
