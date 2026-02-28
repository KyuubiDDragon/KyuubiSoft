<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Controllers;

use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class DeploymentController
{
    private string $encryptionKey;

    public function __construct(
        private readonly Connection $db
    ) {
        $this->encryptionKey = $_ENV['APP_KEY'] ?? 'default-key-change-me';
    }

    /**
     * List all pipelines for the authenticated user with last deployment info.
     */
    public function listPipelines(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $pipelines = $this->db->fetchAllAssociative(
            'SELECT dp.*,
                    (SELECT COUNT(*) FROM deployments d WHERE d.pipeline_id = dp.id) as total_deployments,
                    (SELECT d2.status FROM deployments d2 WHERE d2.pipeline_id = dp.id ORDER BY d2.created_at DESC LIMIT 1) as last_deployment_status,
                    (SELECT d3.created_at FROM deployments d3 WHERE d3.pipeline_id = dp.id ORDER BY d3.created_at DESC LIMIT 1) as last_deployment_at
             FROM deployment_pipelines dp
             WHERE dp.user_id = ?
             ORDER BY dp.updated_at DESC',
            [$userId]
        );

        $pipelines = array_map(function (array $pipeline): array {
            $pipeline['auto_deploy'] = (bool) $pipeline['auto_deploy'];
            $pipeline['notify_on_success'] = (bool) $pipeline['notify_on_success'];
            $pipeline['notify_on_failure'] = (bool) $pipeline['notify_on_failure'];
            $pipeline['steps'] = json_decode($pipeline['steps'], true) ?: [];
            return $pipeline;
        }, $pipelines);

        return JsonResponse::success($pipelines);
    }

    /**
     * Create a new deployment pipeline.
     */
    public function createPipeline(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        $name = trim((string) ($body['name'] ?? ''));
        $description = trim((string) ($body['description'] ?? ''));
        $repository = trim((string) ($body['repository'] ?? ''));
        $branch = trim((string) ($body['branch'] ?? 'main'));
        $steps = $body['steps'] ?? [];
        $environment = trim((string) ($body['environment'] ?? 'production'));
        $connectionId = !empty($body['connection_id']) ? (string) $body['connection_id'] : null;
        $autoDeploy = (bool) ($body['auto_deploy'] ?? false);
        $notifyOnSuccess = (bool) ($body['notify_on_success'] ?? true);
        $notifyOnFailure = (bool) ($body['notify_on_failure'] ?? true);

        // Validate required fields
        $errors = [];
        if ($name === '') {
            $errors['name'] = ['Pipeline-Name ist erforderlich'];
        }
        if (empty($steps) || !is_array($steps)) {
            $errors['steps'] = ['Mindestens ein Deployment-Schritt ist erforderlich'];
        }
        if (!in_array($environment, ['production', 'staging', 'development'], true)) {
            $errors['environment'] = ['Ungueltige Umgebung. Erlaubt: production, staging, development'];
        }

        if (!empty($errors)) {
            return JsonResponse::validationError($errors);
        }

        // Validate steps structure
        foreach ($steps as $i => $step) {
            if (empty($step['name']) || empty($step['command'])) {
                return JsonResponse::validationError([
                    'steps' => ["Schritt " . ($i + 1) . ": Name und Befehl sind erforderlich"],
                ]);
            }
        }

        $id = Uuid::uuid4()->toString();

        $this->db->insert('deployment_pipelines', [
            'id' => $id,
            'user_id' => $userId,
            'connection_id' => $connectionId,
            'name' => $name,
            'description' => $description ?: null,
            'repository' => $repository ?: null,
            'branch' => $branch,
            'steps' => json_encode($steps),
            'environment' => $environment,
            'auto_deploy' => $autoDeploy ? 1 : 0,
            'notify_on_success' => $notifyOnSuccess ? 1 : 0,
            'notify_on_failure' => $notifyOnFailure ? 1 : 0,
        ]);

        $pipeline = $this->db->fetchAssociative(
            'SELECT * FROM deployment_pipelines WHERE id = ?',
            [$id]
        );

        $pipeline['auto_deploy'] = (bool) $pipeline['auto_deploy'];
        $pipeline['notify_on_success'] = (bool) $pipeline['notify_on_success'];
        $pipeline['notify_on_failure'] = (bool) $pipeline['notify_on_failure'];
        $pipeline['steps'] = json_decode($pipeline['steps'], true) ?: [];

        return JsonResponse::created($pipeline);
    }

    /**
     * Get a single pipeline with recent deployments.
     */
    public function showPipeline(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $pipeline = $this->db->fetchAssociative(
            'SELECT * FROM deployment_pipelines WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$pipeline) {
            return JsonResponse::error('Pipeline nicht gefunden', 404);
        }

        $pipeline['auto_deploy'] = (bool) $pipeline['auto_deploy'];
        $pipeline['notify_on_success'] = (bool) $pipeline['notify_on_success'];
        $pipeline['notify_on_failure'] = (bool) $pipeline['notify_on_failure'];
        $pipeline['steps'] = json_decode($pipeline['steps'], true) ?: [];

        // Fetch recent deployments
        $deployments = $this->db->fetchAllAssociative(
            'SELECT * FROM deployments WHERE pipeline_id = ? ORDER BY created_at DESC LIMIT 10',
            [$id]
        );

        $deployments = array_map(function (array $deployment): array {
            $deployment['steps_log'] = json_decode($deployment['steps_log'] ?? '[]', true) ?: [];
            return $deployment;
        }, $deployments);

        $pipeline['recent_deployments'] = $deployments;

        return JsonResponse::success($pipeline);
    }

    /**
     * Update a deployment pipeline.
     */
    public function updatePipeline(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $existing = $this->db->fetchAssociative(
            'SELECT id FROM deployment_pipelines WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$existing) {
            return JsonResponse::error('Pipeline nicht gefunden', 404);
        }

        $body = (array) $request->getParsedBody();
        $updates = [];

        if (isset($body['name'])) {
            $name = trim((string) $body['name']);
            if ($name === '') {
                return JsonResponse::validationError([
                    'name' => ['Pipeline-Name darf nicht leer sein'],
                ]);
            }
            $updates['name'] = $name;
        }

        if (array_key_exists('description', $body)) {
            $updates['description'] = trim((string) $body['description']) ?: null;
        }

        if (array_key_exists('repository', $body)) {
            $updates['repository'] = trim((string) $body['repository']) ?: null;
        }

        if (isset($body['branch'])) {
            $updates['branch'] = trim((string) $body['branch']);
        }

        if (isset($body['steps'])) {
            $steps = $body['steps'];
            if (!is_array($steps) || empty($steps)) {
                return JsonResponse::validationError([
                    'steps' => ['Mindestens ein Deployment-Schritt ist erforderlich'],
                ]);
            }
            foreach ($steps as $i => $step) {
                if (empty($step['name']) || empty($step['command'])) {
                    return JsonResponse::validationError([
                        'steps' => ["Schritt " . ($i + 1) . ": Name und Befehl sind erforderlich"],
                    ]);
                }
            }
            $updates['steps'] = json_encode($steps);
        }

        if (isset($body['environment'])) {
            $env = trim((string) $body['environment']);
            if (!in_array($env, ['production', 'staging', 'development'], true)) {
                return JsonResponse::validationError([
                    'environment' => ['Ungueltige Umgebung'],
                ]);
            }
            $updates['environment'] = $env;
        }

        if (array_key_exists('connection_id', $body)) {
            $updates['connection_id'] = !empty($body['connection_id']) ? (string) $body['connection_id'] : null;
        }

        if (isset($body['auto_deploy'])) {
            $updates['auto_deploy'] = ((bool) $body['auto_deploy']) ? 1 : 0;
        }

        if (isset($body['notify_on_success'])) {
            $updates['notify_on_success'] = ((bool) $body['notify_on_success']) ? 1 : 0;
        }

        if (isset($body['notify_on_failure'])) {
            $updates['notify_on_failure'] = ((bool) $body['notify_on_failure']) ? 1 : 0;
        }

        if (!empty($updates)) {
            $this->db->update('deployment_pipelines', $updates, ['id' => $id]);
        }

        $pipeline = $this->db->fetchAssociative(
            'SELECT * FROM deployment_pipelines WHERE id = ?',
            [$id]
        );

        $pipeline['auto_deploy'] = (bool) $pipeline['auto_deploy'];
        $pipeline['notify_on_success'] = (bool) $pipeline['notify_on_success'];
        $pipeline['notify_on_failure'] = (bool) $pipeline['notify_on_failure'];
        $pipeline['steps'] = json_decode($pipeline['steps'], true) ?: [];

        return JsonResponse::success($pipeline);
    }

    /**
     * Delete a deployment pipeline.
     */
    public function deletePipeline(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $existing = $this->db->fetchAssociative(
            'SELECT id FROM deployment_pipelines WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$existing) {
            return JsonResponse::error('Pipeline nicht gefunden', 404);
        }

        $this->db->delete('deployment_pipelines', ['id' => $id]);

        return JsonResponse::success(null, 'Pipeline geloescht');
    }

    /**
     * Create a new deployment for a pipeline and execute steps via SSH or locally.
     */
    public function deploy(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $pipelineId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $pipeline = $this->db->fetchAssociative(
            'SELECT * FROM deployment_pipelines WHERE id = ? AND user_id = ?',
            [$pipelineId, $userId]
        );

        if (!$pipeline) {
            return JsonResponse::error('Pipeline nicht gefunden', 404);
        }

        $body = (array) $request->getParsedBody();
        $commitHash = trim((string) ($body['commit_hash'] ?? ''));
        $commitMessage = trim((string) ($body['commit_message'] ?? ''));

        $steps = json_decode($pipeline['steps'], true) ?: [];
        $deploymentId = Uuid::uuid4()->toString();
        $startedAt = date('Y-m-d H:i:s');

        // Load SSH connection if configured
        $connection = null;
        if (!empty($pipeline['connection_id'])) {
            $connection = $this->db->fetchAssociative(
                'SELECT * FROM connections WHERE id = ?',
                [$pipeline['connection_id']]
            );
            if (!$connection) {
                return JsonResponse::error('Die konfigurierte Server-Verbindung wurde nicht gefunden', 404);
            }
            if (!in_array($connection['type'], ['ssh', 'sftp'], true)) {
                return JsonResponse::error('Die Verbindung muss vom Typ SSH oder SFTP sein', 422);
            }
        }

        // Execute steps
        $stepsLog = [];
        $totalDuration = 0;
        $allSuccess = true;
        $errorMessage = null;

        foreach ($steps as $step) {
            $stepStarted = microtime(true);
            $stepStartedAt = date('Y-m-d H:i:s');

            $result = $this->executeStep($step['command'], $connection);

            $stepDuration = (int) ((microtime(true) - $stepStarted) * 1000);
            $totalDuration += $stepDuration;
            $stepFinishedAt = date('Y-m-d H:i:s');

            $stepStatus = $result['success'] ? 'success' : 'failed';

            $stepsLog[] = [
                'name' => $step['name'],
                'command' => $step['command'],
                'status' => $stepStatus,
                'duration_ms' => $stepDuration,
                'output' => $result['output'] ?: 'Keine Ausgabe',
                'exit_code' => $result['exit_code'],
                'started_at' => $stepStartedAt,
                'finished_at' => $stepFinishedAt,
            ];

            if (!$result['success']) {
                $allSuccess = false;
                $errorMessage = "Schritt '{$step['name']}' fehlgeschlagen (Exit-Code: {$result['exit_code']})";
                break;
            }
        }

        $status = $allSuccess ? 'success' : 'failed';
        $finishedAt = date('Y-m-d H:i:s');

        $this->db->insert('deployments', [
            'id' => $deploymentId,
            'pipeline_id' => $pipelineId,
            'user_id' => $userId,
            'status' => $status,
            'commit_hash' => $commitHash ?: null,
            'commit_message' => $commitMessage ?: null,
            'steps_log' => json_encode($stepsLog),
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'duration_ms' => $totalDuration,
            'error_message' => $errorMessage,
        ]);

        // Update pipeline last_deployed_at
        $this->db->update('deployment_pipelines', [
            'last_deployed_at' => $finishedAt,
        ], ['id' => $pipelineId]);

        $deployment = $this->db->fetchAssociative(
            'SELECT * FROM deployments WHERE id = ?',
            [$deploymentId]
        );

        $deployment['steps_log'] = json_decode($deployment['steps_log'] ?? '[]', true) ?: [];

        return JsonResponse::created($deployment);
    }

    /**
     * Get a single deployment with full logs.
     */
    public function getDeployment(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $deployment = $this->db->fetchAssociative(
            'SELECT d.*, dp.name as pipeline_name, dp.repository, dp.branch, dp.environment
             FROM deployments d
             JOIN deployment_pipelines dp ON d.pipeline_id = dp.id
             WHERE d.id = ? AND d.user_id = ?',
            [$id, $userId]
        );

        if (!$deployment) {
            return JsonResponse::error('Deployment nicht gefunden', 404);
        }

        $deployment['steps_log'] = json_decode($deployment['steps_log'] ?? '[]', true) ?: [];

        return JsonResponse::success($deployment);
    }

    /**
     * List deployments for a pipeline (paginated).
     */
    public function getDeployments(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $pipelineId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        // Verify pipeline ownership
        $pipeline = $this->db->fetchAssociative(
            'SELECT id FROM deployment_pipelines WHERE id = ? AND user_id = ?',
            [$pipelineId, $userId]
        );

        if (!$pipeline) {
            return JsonResponse::error('Pipeline nicht gefunden', 404);
        }

        $queryParams = $request->getQueryParams();
        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM deployments WHERE pipeline_id = ?',
            [$pipelineId]
        );

        $deployments = $this->db->fetchAllAssociative(
            'SELECT * FROM deployments
             WHERE pipeline_id = ?
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?',
            [$pipelineId, $perPage, $offset],
            [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT]
        );

        $deployments = array_map(function (array $deployment): array {
            $deployment['steps_log'] = json_decode($deployment['steps_log'] ?? '[]', true) ?: [];
            return $deployment;
        }, $deployments);

        return JsonResponse::paginated($deployments, $total, $page, $perPage);
    }

    /**
     * Cancel a pending or running deployment.
     */
    public function cancelDeployment(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $deployment = $this->db->fetchAssociative(
            'SELECT d.id, d.status FROM deployments d
             JOIN deployment_pipelines dp ON d.pipeline_id = dp.id
             WHERE d.id = ? AND d.user_id = ?',
            [$id, $userId]
        );

        if (!$deployment) {
            return JsonResponse::error('Deployment nicht gefunden', 404);
        }

        if (!in_array($deployment['status'], ['pending', 'running'], true)) {
            return JsonResponse::error('Nur ausstehende oder laufende Deployments koennen abgebrochen werden', 422);
        }

        $this->db->update('deployments', [
            'status' => 'cancelled',
            'finished_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        $updated = $this->db->fetchAssociative(
            'SELECT * FROM deployments WHERE id = ?',
            [$id]
        );

        $updated['steps_log'] = json_decode($updated['steps_log'] ?? '[]', true) ?: [];

        return JsonResponse::success($updated, 'Deployment abgebrochen');
    }

    /**
     * Create a rollback deployment referencing the original.
     */
    public function rollback(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $id = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $original = $this->db->fetchAssociative(
            'SELECT d.*, dp.steps, dp.user_id as pipeline_user_id
             FROM deployments d
             JOIN deployment_pipelines dp ON d.pipeline_id = dp.id
             WHERE d.id = ? AND d.user_id = ?',
            [$id, $userId]
        );

        if (!$original) {
            return JsonResponse::error('Deployment nicht gefunden', 404);
        }

        if ($original['status'] !== 'success') {
            return JsonResponse::error('Nur erfolgreiche Deployments koennen zurueckgerollt werden', 422);
        }

        $steps = json_decode($original['steps'], true) ?: [];
        $rollbackId = Uuid::uuid4()->toString();
        $startedAt = date('Y-m-d H:i:s');

        // Load SSH connection if configured
        $pipeline = $this->db->fetchAssociative(
            'SELECT connection_id FROM deployment_pipelines WHERE id = ?',
            [$original['pipeline_id']]
        );
        $connection = null;
        if (!empty($pipeline['connection_id'])) {
            $connection = $this->db->fetchAssociative(
                'SELECT * FROM connections WHERE id = ?',
                [$pipeline['connection_id']]
            );
        }

        // Execute rollback steps (in reverse order)
        $stepsLog = [];
        $totalDuration = 0;
        $allSuccess = true;
        $errorMessage = null;

        $reversedSteps = array_reverse($steps);
        foreach ($reversedSteps as $step) {
            $stepStarted = microtime(true);
            $stepStartedAt = date('Y-m-d H:i:s');

            $result = $this->executeStep($step['command'], $connection);

            $stepDuration = (int) ((microtime(true) - $stepStarted) * 1000);
            $totalDuration += $stepDuration;
            $stepFinishedAt = date('Y-m-d H:i:s');

            $stepStatus = $result['success'] ? 'success' : 'failed';

            $stepsLog[] = [
                'name' => 'Rollback: ' . $step['name'],
                'command' => $step['command'],
                'status' => $stepStatus,
                'duration_ms' => $stepDuration,
                'output' => $result['output'] ?: 'Keine Ausgabe',
                'exit_code' => $result['exit_code'],
                'started_at' => $stepStartedAt,
                'finished_at' => $stepFinishedAt,
            ];

            if (!$result['success']) {
                $allSuccess = false;
                $errorMessage = "Rollback-Schritt '{$step['name']}' fehlgeschlagen (Exit-Code: {$result['exit_code']})";
                break;
            }
        }

        $status = $allSuccess ? 'rolled_back' : 'failed';
        $finishedAt = date('Y-m-d H:i:s');

        $this->db->insert('deployments', [
            'id' => $rollbackId,
            'pipeline_id' => $original['pipeline_id'],
            'user_id' => $userId,
            'status' => $status,
            'commit_hash' => $original['commit_hash'],
            'commit_message' => 'Rollback von Deployment ' . substr($id, 0, 8),
            'error_message' => $errorMessage,
            'steps_log' => json_encode($stepsLog),
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'duration_ms' => $totalDuration,
            'rollback_of' => $id,
        ]);

        // Update pipeline last_deployed_at
        $this->db->update('deployment_pipelines', [
            'last_deployed_at' => $finishedAt,
        ], ['id' => $original['pipeline_id']]);

        $deployment = $this->db->fetchAssociative(
            'SELECT * FROM deployments WHERE id = ?',
            [$rollbackId]
        );

        $deployment['steps_log'] = json_decode($deployment['steps_log'] ?? '[]', true) ?: [];

        return JsonResponse::created($deployment);
    }

    /**
     * Deployment statistics: total, success rate, avg duration, recent failures.
     */
    public function getStats(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $total = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM deployments d
             JOIN deployment_pipelines dp ON d.pipeline_id = dp.id
             WHERE dp.user_id = ?',
            [$userId]
        );

        $successful = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM deployments d
             JOIN deployment_pipelines dp ON d.pipeline_id = dp.id
             WHERE dp.user_id = ? AND d.status = ?',
            [$userId, 'success']
        );

        $successRate = $total > 0 ? round(($successful / $total) * 100, 1) : 0;

        $avgDuration = (int) $this->db->fetchOne(
            'SELECT COALESCE(AVG(d.duration_ms), 0) FROM deployments d
             JOIN deployment_pipelines dp ON d.pipeline_id = dp.id
             WHERE dp.user_id = ? AND d.duration_ms IS NOT NULL',
            [$userId]
        );

        $recentFailures = $this->db->fetchAllAssociative(
            'SELECT d.id, d.status, d.error_message, d.created_at, dp.name as pipeline_name
             FROM deployments d
             JOIN deployment_pipelines dp ON d.pipeline_id = dp.id
             WHERE dp.user_id = ? AND d.status = ?
             ORDER BY d.created_at DESC
             LIMIT 5',
            [$userId, 'failed']
        );

        return JsonResponse::success([
            'total' => $total,
            'successful' => $successful,
            'success_rate' => $successRate,
            'avg_duration_ms' => $avgDuration,
            'recent_failures' => $recentFailures,
        ]);
    }

    // ========================================================================
    // Step Execution (SSH or Local)
    // ========================================================================

    /**
     * Execute a single deployment step via SSH (if connection provided) or locally.
     *
     * @return array{success: bool, output: string, exit_code: int}
     */
    private function executeStep(string $command, ?array $connection): array
    {
        if ($connection) {
            $password = $connection['password_encrypted'] ? $this->decrypt($connection['password_encrypted']) : null;
            $privateKey = $connection['private_key_encrypted'] ? $this->decrypt($connection['private_key_encrypted']) : null;

            return $this->executeSSHCommand(
                $connection['host'],
                (int) ($connection['port'] ?: 22),
                $connection['username'],
                $password,
                $privateKey,
                $command
            );
        }

        // Local execution
        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);

        return [
            'success' => $exitCode === 0,
            'output' => implode("\n", $output),
            'exit_code' => $exitCode,
        ];
    }

    /**
     * Execute a command via SSH using php-ssh2 extension or shell fallback.
     */
    private function executeSSHCommand(
        string $host,
        int $port,
        string $username,
        ?string $password,
        ?string $privateKey,
        string $command
    ): array {
        if (function_exists('ssh2_connect')) {
            return $this->executeSSHCommandViaSsh2($host, $port, $username, $password, $privateKey, $command);
        }

        return $this->executeSSHCommandViaShell($host, $port, $username, $password, $privateKey, $command);
    }

    private function executeSSHCommandViaSsh2(
        string $host,
        int $port,
        string $username,
        ?string $password,
        ?string $privateKey,
        string $command
    ): array {
        $connection = @ssh2_connect($host, $port);
        if (!$connection) {
            return ['success' => false, 'output' => "Verbindung zu {$host}:{$port} fehlgeschlagen", 'exit_code' => -1];
        }

        $authenticated = false;

        if ($privateKey) {
            $keyFile = tempnam(sys_get_temp_dir(), 'ssh_key_');
            file_put_contents($keyFile, $privateKey);
            chmod($keyFile, 0600);
            $authenticated = @ssh2_auth_pubkey_file($connection, $username, $keyFile . '.pub', $keyFile);
            unlink($keyFile);
        }

        if (!$authenticated && $password) {
            $authenticated = @ssh2_auth_password($connection, $username, $password);
        }

        if (!$authenticated) {
            return ['success' => false, 'output' => 'SSH-Authentifizierung fehlgeschlagen', 'exit_code' => -1];
        }

        $stream = ssh2_exec($connection, $command);
        if (!$stream) {
            return ['success' => false, 'output' => 'Befehlsausfuehrung fehlgeschlagen', 'exit_code' => -1];
        }

        stream_set_blocking($stream, true);
        $output = stream_get_contents($stream);

        $stderr = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
        stream_set_blocking($stderr, true);
        $errorOutput = stream_get_contents($stderr);

        fclose($stream);
        fclose($stderr);

        $fullOutput = $output;
        if ($errorOutput) {
            $fullOutput .= "\n" . $errorOutput;
        }

        return [
            'success' => true,
            'output' => $fullOutput,
            'exit_code' => 0,
        ];
    }

    private function executeSSHCommandViaShell(
        string $host,
        int $port,
        string $username,
        ?string $password,
        ?string $privateKey,
        string $command
    ): array {
        $sshArgs = [
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'UserKnownHostsFile=/dev/null',
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout=10',
            '-p', (string) $port,
        ];

        $keyFile = null;
        if ($privateKey) {
            $keyFile = tempnam(sys_get_temp_dir(), 'ssh_key_');
            file_put_contents($keyFile, $privateKey);
            chmod($keyFile, 0600);
            $sshArgs[] = '-i';
            $sshArgs[] = $keyFile;
        }

        $sshCommand = 'ssh ' . implode(' ', array_map('escapeshellarg', $sshArgs)) . ' '
            . escapeshellarg("{$username}@{$host}") . ' '
            . escapeshellarg($command);

        if ($password && !$privateKey) {
            $sshCommand = 'sshpass -p ' . escapeshellarg($password) . ' ' . $sshCommand;
        }

        $output = [];
        $exitCode = 0;
        exec($sshCommand . ' 2>&1', $output, $exitCode);

        if ($keyFile) {
            unlink($keyFile);
        }

        return [
            'success' => $exitCode === 0,
            'output' => implode("\n", $output),
            'exit_code' => $exitCode,
        ];
    }

    private function decrypt(string $data): string
    {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
    }
}
