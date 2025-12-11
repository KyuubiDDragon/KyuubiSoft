<?php

declare(strict_types=1);

namespace App\Modules\AI\Services;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

class AIService
{
    private string $encryptionKey;

    public function __construct(
        private readonly Connection $db
    ) {
        $this->encryptionKey = $_ENV['APP_KEY'] ?? 'default-key-change-me';
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
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Only update API key if provided
        if (!empty($data['api_key'])) {
            $updateData['api_key_encrypted'] = $this->encryptApiKey($data['api_key']);
            $updateData['is_enabled'] = true;
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
            'is_enabled' => false,
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

        // Create or get conversation
        if (!$conversationId) {
            $conversationId = $this->createConversation($userId, $context);
        }

        // Save user message
        $this->saveMessage($conversationId, 'user', $message);

        // Get conversation history
        $history = $this->getConversationHistory($conversationId);

        // Call AI provider
        $response = $this->callProvider(
            $settings['provider'],
            $apiKey,
            $settings['model'],
            $settings['api_base_url'],
            $history,
            (int) $settings['max_tokens'],
            (float) $settings['temperature']
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
     * Call AI provider API
     */
    private function callProvider(
        string $provider,
        string $apiKey,
        string $model,
        ?string $baseUrl,
        array $messages,
        int $maxTokens,
        float $temperature
    ): array {
        $formattedMessages = array_map(fn($m) => [
            'role' => $m['role'],
            'content' => $m['content']
        ], $messages);

        switch ($provider) {
            case 'openai':
                return $this->callOpenAI($apiKey, $model, $formattedMessages, $maxTokens, $temperature);
            case 'anthropic':
                return $this->callAnthropic($apiKey, $model, $formattedMessages, $maxTokens, $temperature);
            case 'openrouter':
                return $this->callOpenRouter($apiKey, $model, $formattedMessages, $maxTokens, $temperature);
            case 'ollama':
                return $this->callOllama($baseUrl ?? 'http://localhost:11434', $model, $formattedMessages, $maxTokens, $temperature);
            case 'custom':
                return $this->callCustom($baseUrl, $apiKey, $model, $formattedMessages, $maxTokens, $temperature);
            default:
                throw new \InvalidArgumentException('Unsupported AI provider: ' . $provider);
        }
    }

    /**
     * Call OpenAI API
     */
    private function callOpenAI(string $apiKey, string $model, array $messages, int $maxTokens, float $temperature): array
    {
        $response = $this->httpPost('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
        ], ['Authorization: Bearer ' . $apiKey]);

        if (isset($response['error'])) {
            throw new \RuntimeException($response['error']['message'] ?? 'OpenAI API error');
        }

        return [
            'content' => $response['choices'][0]['message']['content'] ?? '',
            'tokens' => $response['usage']['total_tokens'] ?? 0,
        ];
    }

    /**
     * Call Anthropic API
     */
    private function callAnthropic(string $apiKey, string $model, array $messages, int $maxTokens, float $temperature): array
    {
        // Extract system message if present
        $system = null;
        $filteredMessages = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system = $msg['content'];
            } else {
                $filteredMessages[] = $msg;
            }
        }

        $payload = [
            'model' => $model,
            'messages' => $filteredMessages,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
        ];

        if ($system) {
            $payload['system'] = $system;
        }

        $response = $this->httpPost('https://api.anthropic.com/v1/messages', $payload, [
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ]);

        if (isset($response['error'])) {
            throw new \RuntimeException($response['error']['message'] ?? 'Anthropic API error');
        }

        return [
            'content' => $response['content'][0]['text'] ?? '',
            'tokens' => ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0),
        ];
    }

    /**
     * Call OpenRouter API (OpenAI compatible)
     */
    private function callOpenRouter(string $apiKey, string $model, array $messages, int $maxTokens, float $temperature): array
    {
        $response = $this->httpPost('https://openrouter.ai/api/v1/chat/completions', [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
        ], [
            'Authorization: Bearer ' . $apiKey,
            'HTTP-Referer: ' . ($_ENV['APP_URL'] ?? 'http://localhost'),
        ]);

        if (isset($response['error'])) {
            throw new \RuntimeException($response['error']['message'] ?? 'OpenRouter API error');
        }

        return [
            'content' => $response['choices'][0]['message']['content'] ?? '',
            'tokens' => $response['usage']['total_tokens'] ?? 0,
        ];
    }

    /**
     * Call Ollama (local)
     */
    private function callOllama(string $baseUrl, string $model, array $messages, int $maxTokens, float $temperature): array
    {
        $response = $this->httpPost($baseUrl . '/api/chat', [
            'model' => $model,
            'messages' => $messages,
            'options' => [
                'num_predict' => $maxTokens,
                'temperature' => $temperature,
            ],
            'stream' => false,
        ], []);

        if (isset($response['error'])) {
            throw new \RuntimeException($response['error'] ?? 'Ollama API error');
        }

        return [
            'content' => $response['message']['content'] ?? '',
            'tokens' => $response['eval_count'] ?? 0,
        ];
    }

    /**
     * Call custom OpenAI-compatible API
     */
    private function callCustom(string $baseUrl, string $apiKey, string $model, array $messages, int $maxTokens, float $temperature): array
    {
        $response = $this->httpPost($baseUrl . '/v1/chat/completions', [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
        ], ['Authorization: Bearer ' . $apiKey]);

        if (isset($response['error'])) {
            throw new \RuntimeException($response['error']['message'] ?? 'Custom API error');
        }

        return [
            'content' => $response['choices'][0]['message']['content'] ?? '',
            'tokens' => $response['usage']['total_tokens'] ?? 0,
        ];
    }

    /**
     * HTTP POST helper
     */
    private function httpPost(string $url, array $data, array $headers): array
    {
        $headers[] = 'Content-Type: application/json';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('HTTP request failed: ' . $error);
        }

        return json_decode($response, true) ?? ['error' => 'Invalid response'];
    }

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
     * Encrypt API key
     */
    private function encryptApiKey(string $apiKey): string
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($apiKey, 'aes-256-cbc', $this->encryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt API key
     */
    private function decryptApiKey(string $encrypted): string
    {
        $data = base64_decode($encrypted);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'aes-256-cbc', $this->encryptionKey, 0, $iv);
    }
}
