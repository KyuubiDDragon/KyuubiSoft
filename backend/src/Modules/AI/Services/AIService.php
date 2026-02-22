<?php

declare(strict_types=1);

namespace App\Modules\AI\Services;

use App\Modules\AI\Providers\AIProviderInterface;
use App\Modules\AI\Providers\OpenAIProvider;
use App\Modules\AI\Providers\OpenRouterProvider;
use App\Modules\AI\Providers\AnthropicProvider;
use App\Modules\AI\Providers\OllamaProvider;
use App\Modules\AI\Providers\CustomProvider;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

class AIService
{
    private string $encryptionKey;
    private AIToolsService $toolsService;

    /** @var array<string, AIProviderInterface> */
    private array $providers;

    public function __construct(
        private readonly Connection $db
    ) {
        // Derive a proper 32-byte key from APP_KEY using SHA-256 (raw binary output).
        // Using the raw APP_KEY string directly was unsafe: AES-256-CBC requires exactly
        // 32 bytes, and OpenSSL would silently truncate/pad arbitrary-length strings.
        $rawKey = $_ENV['APP_KEY'] ?? 'default-key-change-me';
        $this->encryptionKey = hash('sha256', $rawKey, true);
        $this->toolsService  = new AIToolsService();

        $this->providers = $this->buildProviderMap();
    }

    /**
     * Build the provider map. Adding a new AI provider only requires creating a class
     * implementing AIProviderInterface and registering it here.
     *
     * @return array<string, AIProviderInterface>
     */
    private function buildProviderMap(): array
    {
        $list = [
            new OpenAIProvider(),
            new OpenRouterProvider(),
            new AnthropicProvider(),
            new OllamaProvider(),
            new CustomProvider(),
        ];

        $map = [];
        foreach ($list as $provider) {
            $map[$provider->getName()] = $provider;
        }
        return $map;
    }

    /**
     * Get AI settings for user
     */
    public function getSettings(string $userId): ?array
    {
        $settings = $this->db->fetchAssociative(
            'SELECT * FROM ai_settings WHERE user_id = ?',
            [$userId]
        );

        if ($settings) {
            // Don't expose the actual key, just whether it's set
            $settings['has_api_key'] = !empty($settings['api_key_encrypted']);
            unset($settings['api_key_encrypted']);
        }

        return $settings ?: null;
    }

    /**
     * Save AI settings
     */
    public function saveSettings(string $userId, array $data): array
    {
        $existing = $this->db->fetchAssociative(
            'SELECT id FROM ai_settings WHERE user_id = ?',
            [$userId]
        );

        $updateData = [
            'provider' => $data['provider'] ?? 'openai',
            'model' => $data['model'] ?? 'gpt-4o-mini',
            'api_base_url' => $data['api_base_url'] ?? null,
            'max_tokens' => $data['max_tokens'] ?? 2000,
            'temperature' => $data['temperature'] ?? 0.7,
            'context_enabled' => isset($data['context_enabled']) ? ($data['context_enabled'] ? 1 : 0) : 1,
            'tools_enabled' => isset($data['tools_enabled']) ? ($data['tools_enabled'] ? 1 : 0) : 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Only update API key if provided
        if (!empty($data['api_key'])) {
            $updateData['api_key_encrypted'] = $this->encryptApiKey($data['api_key']);
            $updateData['is_enabled'] = 1;
        }

        if ($existing) {
            $this->db->update('ai_settings', $updateData, ['user_id' => $userId]);
        } else {
            $updateData['id'] = Uuid::uuid4()->toString();
            $updateData['user_id'] = $userId;
            $updateData['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert('ai_settings', $updateData);
        }

        return $this->getSettings($userId);
    }

    /**
     * Remove API key
     */
    public function removeApiKey(string $userId): bool
    {
        return $this->db->update('ai_settings', [
            'api_key_encrypted' => null,
            'is_enabled' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['user_id' => $userId]) > 0;
    }

    /**
     * Check if user has configured AI
     */
    public function isConfigured(string $userId): bool
    {
        $result = $this->db->fetchOne(
            'SELECT api_key_encrypted FROM ai_settings WHERE user_id = ? AND is_enabled = 1',
            [$userId]
        );
        return !empty($result);
    }

    /**
     * Send message to AI
     */
    public function chat(string $userId, string $message, ?string $conversationId = null, array $context = []): array
    {
        $settings = $this->db->fetchAssociative(
            'SELECT * FROM ai_settings WHERE user_id = ? AND is_enabled = 1',
            [$userId]
        );

        if (!$settings || empty($settings['api_key_encrypted'])) {
            throw new \RuntimeException('AI not configured. Please add your API key in settings.');
        }

        $apiKey = $this->decryptApiKey($settings['api_key_encrypted']);

        // Get feature flags (default to true for backwards compatibility)
        $contextEnabled = ($settings['context_enabled'] ?? 1) == 1;
        $toolsEnabled = ($settings['tools_enabled'] ?? 1) == 1;

        // Create or get conversation
        if (!$conversationId) {
            $conversationId = $this->createConversation($userId, $context);
        }

        // Save user message
        $this->saveMessage($conversationId, 'user', $message);

        // Get conversation history
        $history = $this->getConversationHistory($conversationId);

        // Build system prompt with user context (only for new conversations or first message)
        if (count($history) <= 1) {
            $projectId = $context['project_id'] ?? null;
            $systemPrompt = $this->buildSystemPrompt($userId, $contextEnabled, $toolsEnabled, $projectId);
            array_unshift($history, ['role' => 'system', 'content' => $systemPrompt]);
        }

        // Call AI provider
        $response = $this->callProvider(
            $settings['provider'],
            $apiKey,
            $settings['model'],
            $settings['api_base_url'],
            $history,
            (int) $settings['max_tokens'],
            (float) $settings['temperature'],
            $toolsEnabled
        );

        // Save assistant response
        $this->saveMessage($conversationId, 'assistant', $response['content'], $response['tokens'] ?? 0);

        // Update usage stats
        $this->updateUsageStats($userId, $response['tokens'] ?? 0);

        return [
            'conversation_id' => $conversationId,
            'message' => $response['content'],
            'tokens_used' => $response['tokens'] ?? 0,
        ];
    }

    /**
     * Build system prompt with user context
     */
    private function buildSystemPrompt(string $userId, bool $contextEnabled = true, bool $toolsEnabled = true, ?string $projectId = null): string
    {
        $userName = $this->getUserName($userId);

        $systemPrompt = "Du bist ein hilfreicher AI-Assistent in KyuubiSoft, einer Produktivitäts- und Projektmanagement-Anwendung.
Du hilfst dem Benutzer '{$userName}' bei der Verwaltung seiner Projekte, Aufgaben, Wiki-Seiten und mehr.\n\n";

        // Only include user context if enabled
        if ($contextEnabled) {
            $userContext = $this->getUserContext($userId, $projectId);

            $systemPrompt .= "WICHTIG: Du hast Zugriff auf die aktuellen Daten des Benutzers. Nutze diese Informationen, um präzise und hilfreiche Antworten zu geben.

=== AKTUELLE BENUTZERDATEN ===

";

            // Check if we have project context
            if (!empty($userContext['active_project'])) {
                $project = $userContext['active_project'];
                $systemPrompt .= "*** AKTIVES PROJEKT: {$project['name']} ***\n";
                if (!empty($project['description'])) {
                    $systemPrompt .= "Beschreibung: {$project['description']}\n";
                }
                $systemPrompt .= "Status: {$project['status']}\n\n";

                // Show project-specific lists
                if (!empty($userContext['lists'])) {
                    $systemPrompt .= "LISTEN IN DIESEM PROJEKT:\n";
                    foreach ($userContext['lists'] as $list) {
                        $systemPrompt .= "  - {$list['title']} ({$list['open_count']} offen, {$list['completed_count']} erledigt)\n";
                    }
                    $systemPrompt .= "\n";
                }

                // Show docker hosts for this project
                if (!empty($userContext['docker_hosts'])) {
                    $systemPrompt .= "DOCKER-HOSTS IN DIESEM PROJEKT:\n";
                    foreach ($userContext['docker_hosts'] as $host) {
                        $status = $host['is_active'] ? '[Aktiv]' : '[Inaktiv]';
                        $connection = $host['type'] === 'tcp' ? $host['tcp_host'] : $host['socket_path'];
                        $systemPrompt .= "  {$status} {$host['name']} ({$connection})\n";
                    }
                    $systemPrompt .= "\n";
                }

                // Show time tracked today
                if ($userContext['time_entries_today'] > 0) {
                    $hours = floor($userContext['time_entries_today'] / 3600);
                    $minutes = floor(($userContext['time_entries_today'] % 3600) / 60);
                    $systemPrompt .= "ZEIT HEUTE GETRACKT: {$hours}h {$minutes}min\n\n";
                }
            } else {
                // No project context - show all projects
                $systemPrompt .= "PROJEKTE ({$userContext['project_count']}):\n";
                if (!empty($userContext['projects'])) {
                    foreach ($userContext['projects'] as $project) {
                        $status = $project['status'] === 'active' ? '[Aktiv]' : '[Inaktiv]';
                        $systemPrompt .= "  {$status} {$project['name']}";
                        if (!empty($project['description'])) {
                            $systemPrompt .= " - " . substr($project['description'], 0, 50);
                        }
                        $systemPrompt .= "\n";
                    }
                } else {
                    $systemPrompt .= "  Keine Projekte vorhanden\n";
                }
            }

            // Add tasks summary
            $systemPrompt .= "\nAUFGABEN:\n";
            $systemPrompt .= "  Offen: {$userContext['tasks']['open']}\n";
            $systemPrompt .= "  Erledigt: {$userContext['tasks']['completed']}\n";
            $systemPrompt .= "  Ueberfaellig: {$userContext['tasks']['overdue']}\n";

            // Add upcoming tasks
            if (!empty($userContext['upcoming_tasks'])) {
                $systemPrompt .= "\nANSTEHENDE AUFGABEN (naechste 7 Tage):\n";
                foreach ($userContext['upcoming_tasks'] as $task) {
                    $dueDate = $task['due_date'] ? date('d.m.Y', strtotime($task['due_date'])) : '';
                    $listInfo = !empty($task['list_name']) ? " [{$task['list_name']}]" : '';
                    $systemPrompt .= "  - {$task['title']}{$listInfo} (Faellig: {$dueDate})\n";
                }
            }

            // Add inbox items (global)
            $systemPrompt .= "\nINBOX: {$userContext['inbox_count']} Eintraege\n";

            // Add wiki pages (global)
            $systemPrompt .= "\nWIKI-SEITEN: {$userContext['wiki_count']} Seiten\n";
            if (!empty($userContext['recent_wiki_pages'])) {
                $systemPrompt .= "  Zuletzt bearbeitet:\n";
                foreach ($userContext['recent_wiki_pages'] as $page) {
                    $systemPrompt .= "    - {$page['title']}\n";
                }
            }

            // Add kanban boards with names
            if ($userContext['kanban_count'] > 0) {
                $systemPrompt .= "\nKANBAN-BOARDS ({$userContext['kanban_count']}):\n";
                foreach ($userContext['kanban_boards'] as $board) {
                    $systemPrompt .= "  - {$board['title']} ({$board['card_count']} Karten)";
                    if (!empty($board['description'])) {
                        $systemPrompt .= " - " . substr($board['description'], 0, 50);
                    }
                    $systemPrompt .= "\n";
                }
            }

            $systemPrompt .= "\n=== ENDE BENUTZERDATEN ===\n\n";
        }

        // Only include tools info if enabled
        if ($toolsEnabled) {
            $systemPrompt .= "=== VERFUEGBARE TOOLS ===
Du hast Zugriff auf System-Tools, die du nutzen kannst:
- get_docker_containers: Docker-Container auflisten
- get_docker_container_logs: Container-Logs anzeigen
- get_system_info: Systeminformationen (CPU, RAM, etc.)
- get_running_processes: Laufende Prozesse anzeigen
- get_disk_usage: Festplattennutzung pruefen
- get_service_status: Dienst-Status pruefen
- get_network_info: Netzwerkinformationen anzeigen

Wenn der Benutzer nach Docker, Server-Status, Prozessen oder Systeminformationen fragt, nutze die entsprechenden Tools!
=== ENDE TOOLS ===\n\n";
        }

        $systemPrompt .= "Beantworte Fragen freundlich auf Deutsch.";
        if ($contextEnabled) {
            $systemPrompt .= " Wenn der Benutzer nach seinen Daten fragt, nutze die obigen Informationen.";
        }
        $systemPrompt .= " Du kannst auch bei allgemeinen Fragen helfen, Texte schreiben, Code erklaeren und vieles mehr.";

        return $systemPrompt;
    }

    /**
     * Get user name
     */
    private function getUserName(string $userId): string
    {
        $user = $this->db->fetchAssociative(
            'SELECT username, email FROM users WHERE id = ?',
            [$userId]
        );
        return $user['username'] ?? $user['email'] ?? 'Benutzer';
    }

    /**
     * Get user context data, optionally filtered by project
     */
    private function getUserContext(string $userId, ?string $projectId = null): array
    {
        $result = [
            'active_project' => null,
            'projects' => [],
            'project_count' => 0,
            'tasks' => ['open' => 0, 'completed' => 0, 'overdue' => 0],
            'upcoming_tasks' => [],
            'inbox_count' => 0,
            'wiki_count' => 0,
            'recent_wiki_pages' => [],
            'kanban_boards' => [],
            'kanban_count' => 0,
            'lists' => [],
            'docker_hosts' => [],
            'time_entries_today' => 0,
        ];

        // If project context is given, get project info and filter by it
        if ($projectId) {
            $project = $this->db->fetchAssociative(
                "SELECT id, name, description, status, color FROM projects WHERE id = ? AND user_id = ?",
                [$projectId, $userId]
            );
            if ($project) {
                $result['active_project'] = $project;

                // Get lists linked to this project
                $lists = $this->db->fetchAllAssociative(
                    "SELECT l.id, l.title, l.type,
                            (SELECT COUNT(*) FROM list_items li WHERE li.list_id = l.id AND li.is_completed = 0) as open_count,
                            (SELECT COUNT(*) FROM list_items li WHERE li.list_id = l.id AND li.is_completed = 1) as completed_count
                     FROM lists l
                     INNER JOIN project_links pl ON pl.linkable_id = l.id AND pl.linkable_type = 'list'
                     WHERE pl.project_id = ? AND l.is_archived = 0
                     ORDER BY l.updated_at DESC",
                    [$projectId]
                );
                $result['lists'] = $lists;

                // Calculate task counts from project-linked lists
                $taskCounts = $this->db->fetchAssociative(
                    "SELECT
                        SUM(CASE WHEN li.is_completed = 0 THEN 1 ELSE 0 END) as open_count,
                        SUM(CASE WHEN li.is_completed = 1 THEN 1 ELSE 0 END) as completed_count,
                        SUM(CASE WHEN li.is_completed = 0 AND li.due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_count
                     FROM list_items li
                     INNER JOIN lists l ON li.list_id = l.id
                     INNER JOIN project_links pl ON pl.linkable_id = l.id AND pl.linkable_type = 'list'
                     WHERE pl.project_id = ?",
                    [$projectId]
                );
                $result['tasks'] = [
                    'open' => (int) ($taskCounts['open_count'] ?? 0),
                    'completed' => (int) ($taskCounts['completed_count'] ?? 0),
                    'overdue' => (int) ($taskCounts['overdue_count'] ?? 0),
                ];

                // Get upcoming tasks from project-linked lists
                $result['upcoming_tasks'] = $this->db->fetchAllAssociative(
                    "SELECT li.content as title, li.due_date, li.priority, l.title as list_name
                     FROM list_items li
                     INNER JOIN lists l ON li.list_id = l.id
                     INNER JOIN project_links pl ON pl.linkable_id = l.id AND pl.linkable_type = 'list'
                     WHERE pl.project_id = ? AND li.is_completed = 0 AND li.due_date IS NOT NULL
                     AND li.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                     ORDER BY li.due_date ASC LIMIT 10",
                    [$projectId]
                );

                // Get kanban boards linked to this project
                $result['kanban_boards'] = $this->db->fetchAllAssociative(
                    "SELECT kb.id, kb.title, kb.description,
                            (SELECT COUNT(*) FROM kanban_cards kc
                             INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
                             WHERE kcol.board_id = kb.id) as card_count
                     FROM kanban_boards kb
                     INNER JOIN project_links pl ON pl.linkable_id = kb.id AND pl.linkable_type = 'kanban_board'
                     WHERE pl.project_id = ? AND kb.is_archived = 0
                     ORDER BY kb.updated_at DESC",
                    [$projectId]
                );
                $result['kanban_count'] = count($result['kanban_boards']);

                // Get docker hosts linked to this project
                $result['docker_hosts'] = $this->db->fetchAllAssociative(
                    "SELECT name, tcp_host, socket_path, type, is_active FROM docker_hosts WHERE project_id = ? AND user_id = ?",
                    [$projectId, $userId]
                );

                // Get time entries for today in this project
                $result['time_entries_today'] = (int) $this->db->fetchOne(
                    "SELECT COALESCE(SUM(duration_seconds), 0) FROM time_entries
                     WHERE project_id = ? AND user_id = ? AND DATE(started_at) = CURDATE()",
                    [$projectId, $userId]
                );
            }
        } else {
            // No project context - show all user data
            $result['projects'] = $this->db->fetchAllAssociative(
                "SELECT id, name, description, status, color FROM projects WHERE user_id = ? AND status != 'archived' ORDER BY updated_at DESC LIMIT 10",
                [$userId]
            );
            $result['project_count'] = count($result['projects']);

            // Get all task counts
            $result['tasks'] = [
                'open' => (int) $this->db->fetchOne(
                    "SELECT COUNT(*) FROM list_items li INNER JOIN lists l ON li.list_id = l.id WHERE l.user_id = ? AND li.is_completed = 0",
                    [$userId]
                ),
                'completed' => (int) $this->db->fetchOne(
                    "SELECT COUNT(*) FROM list_items li INNER JOIN lists l ON li.list_id = l.id WHERE l.user_id = ? AND li.is_completed = 1",
                    [$userId]
                ),
                'overdue' => (int) $this->db->fetchOne(
                    "SELECT COUNT(*) FROM list_items li INNER JOIN lists l ON li.list_id = l.id WHERE l.user_id = ? AND li.is_completed = 0 AND li.due_date < CURDATE()",
                    [$userId]
                ),
            ];

            // Get upcoming tasks
            $result['upcoming_tasks'] = $this->db->fetchAllAssociative(
                "SELECT li.content as title, li.due_date, li.priority FROM list_items li
                 INNER JOIN lists l ON li.list_id = l.id
                 WHERE l.user_id = ? AND li.is_completed = 0 AND li.due_date IS NOT NULL
                 AND li.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                 ORDER BY li.due_date ASC LIMIT 5",
                [$userId]
            );

            // Get all kanban boards
            $result['kanban_boards'] = $this->db->fetchAllAssociative(
                "SELECT kb.id, kb.title, kb.description,
                        (SELECT COUNT(*) FROM kanban_cards kc
                         INNER JOIN kanban_columns kcol ON kc.column_id = kcol.id
                         WHERE kcol.board_id = kb.id) as card_count
                 FROM kanban_boards kb
                 WHERE kb.user_id = ? AND kb.is_archived = 0
                 ORDER BY kb.updated_at DESC LIMIT 10",
                [$userId]
            );
            $result['kanban_count'] = count($result['kanban_boards']);
        }

        // Inbox is always user-global (not project-specific)
        $result['inbox_count'] = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM inbox_items WHERE user_id = ? AND status = 'inbox'",
            [$userId]
        );

        // Wiki is always user-global
        $result['wiki_count'] = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM wiki_pages WHERE user_id = ?',
            [$userId]
        );
        $result['recent_wiki_pages'] = $this->db->fetchAllAssociative(
            'SELECT title FROM wiki_pages WHERE user_id = ? ORDER BY updated_at DESC LIMIT 5',
            [$userId]
        );

        return $result;
    }

    /**
     * Dispatch a chat completion request to the appropriate AI provider.
     */
    private function callProvider(
        string $provider,
        string $apiKey,
        string $model,
        ?string $baseUrl,
        array $messages,
        int $maxTokens,
        float $temperature,
        bool $toolsEnabled = true
    ): array {
        if (!isset($this->providers[$provider])) {
            throw new \InvalidArgumentException('Unsupported AI provider: ' . $provider);
        }

        $formattedMessages = array_map(fn($m) => [
            'role'    => $m['role'],
            'content' => $m['content'],
        ], $messages);

        return $this->providers[$provider]->call(
            $apiKey,
            $model,
            $formattedMessages,
            $maxTokens,
            $temperature,
            $toolsEnabled,
            $this->toolsService,
            $baseUrl
        );
    }

    // Provider-specific call methods have been moved to dedicated classes under
    // App\Modules\AI\Providers\. See callProvider() above and the Providers/ directory.

    /**
     * Create conversation
     */
    private function createConversation(string $userId, array $context = []): string
    {
        $id = Uuid::uuid4()->toString();
        $this->db->insert('ai_conversations', [
            'id' => $id,
            'user_id' => $userId,
            'title' => $context['title'] ?? 'New Conversation',
            'context_type' => $context['type'] ?? null,
            'context_id' => $context['id'] ?? null,
            'model_used' => $context['model'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $id;
    }

    /**
     * Save message
     */
    private function saveMessage(string $conversationId, string $role, string $content, int $tokens = 0): void
    {
        $this->db->insert('ai_messages', [
            'id' => Uuid::uuid4()->toString(),
            'conversation_id' => $conversationId,
            'role' => $role,
            'content' => $content,
            'tokens_used' => $tokens,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->update('ai_conversations', [
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $conversationId]);
    }

    /**
     * Get conversation history
     */
    private function getConversationHistory(string $conversationId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT role, content FROM ai_messages WHERE conversation_id = ? ORDER BY created_at ASC',
            [$conversationId]
        );
    }

    /**
     * Update usage stats
     */
    private function updateUsageStats(string $userId, int $tokens): void
    {
        $this->db->executeStatement(
            'UPDATE ai_settings SET total_requests = total_requests + 1, total_tokens_used = total_tokens_used + ?, last_used_at = ? WHERE user_id = ?',
            [$tokens, date('Y-m-d H:i:s'), $userId]
        );
    }

    /**
     * Get conversations
     */
    public function getConversations(string $userId, int $limit = 50): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT * FROM ai_conversations WHERE user_id = ? AND is_archived = 0 ORDER BY updated_at DESC LIMIT ?',
            [$userId, $limit]
        );
    }

    /**
     * Get conversation with messages
     */
    public function getConversation(string $userId, string $conversationId): ?array
    {
        $conversation = $this->db->fetchAssociative(
            'SELECT * FROM ai_conversations WHERE id = ? AND user_id = ?',
            [$conversationId, $userId]
        );

        if (!$conversation) {
            return null;
        }

        $conversation['messages'] = $this->db->fetchAllAssociative(
            'SELECT * FROM ai_messages WHERE conversation_id = ? ORDER BY created_at ASC',
            [$conversationId]
        );

        return $conversation;
    }

    /**
     * Delete conversation
     */
    public function deleteConversation(string $userId, string $conversationId): bool
    {
        return $this->db->delete('ai_conversations', [
            'id' => $conversationId,
            'user_id' => $userId,
        ]) > 0;
    }

    /**
     * Get prompts
     */
    public function getPrompts(string $userId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT * FROM ai_prompts WHERE user_id = ? ORDER BY use_count DESC',
            [$userId]
        );
    }

    /**
     * Save prompt
     */
    public function savePrompt(string $userId, array $data): array
    {
        $id = $data['id'] ?? Uuid::uuid4()->toString();

        if (isset($data['id'])) {
            $this->db->update('ai_prompts', [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'prompt_template' => $data['prompt_template'],
                'category' => $data['category'] ?? 'general',
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $id, 'user_id' => $userId]);
        } else {
            $this->db->insert('ai_prompts', [
                'id' => $id,
                'user_id' => $userId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'prompt_template' => $data['prompt_template'],
                'category' => $data['category'] ?? 'general',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $this->db->fetchAssociative('SELECT * FROM ai_prompts WHERE id = ?', [$id]);
    }

    /**
     * Delete prompt
     */
    public function deletePrompt(string $userId, string $promptId): bool
    {
        return $this->db->delete('ai_prompts', [
            'id' => $promptId,
            'user_id' => $userId,
        ]) > 0;
    }

    /**
     * Encrypt API key using the derived 32-byte key.
     */
    private function encryptApiKey(string $apiKey): string
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($apiKey, 'aes-256-cbc', $this->encryptionKey, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt API key. Tries the derived key first; falls back to the legacy raw
     * APP_KEY for data encrypted before the key-derivation fix was applied.
     * Run scripts/migrate_encryption.php to re-encrypt legacy values so the fallback
     * can eventually be removed.
     */
    private function decryptApiKey(string $encrypted): string
    {
        $data = base64_decode($encrypted);
        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);

        // Attempt decryption with the new derived key (OPENSSL_RAW_DATA).
        $result = openssl_decrypt($ciphertext, 'aes-256-cbc', $this->encryptionKey, OPENSSL_RAW_DATA, $iv);
        if ($result !== false) {
            return $result;
        }

        // Legacy fallback: data was encrypted with the raw APP_KEY string and flag=0
        // (base64-wrapped output). Try that combination before giving up.
        $legacyKey = $_ENV['APP_KEY'] ?? 'default-key-change-me';
        $legacyResult = openssl_decrypt($ciphertext, 'aes-256-cbc', $legacyKey, 0, $iv);
        if ($legacyResult !== false) {
            return $legacyResult;
        }

        throw new \RuntimeException('Unable to decrypt API key: invalid key or corrupted data.');
    }
}
