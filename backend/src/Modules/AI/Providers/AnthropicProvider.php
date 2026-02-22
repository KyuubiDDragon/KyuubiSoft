<?php

declare(strict_types=1);

namespace App\Modules\AI\Providers;

use App\Modules\AI\Services\AIToolsService;

class AnthropicProvider implements AIProviderInterface
{
    use HttpPostTrait;

    public function getName(): string
    {
        return 'anthropic';
    }

    public function call(
        string $apiKey,
        string $model,
        array $messages,
        int $maxTokens,
        float $temperature,
        bool $toolsEnabled,
        AIToolsService $toolsService,
        ?string $baseUrl = null
    ): array {
        $totalTokens   = 0;
        $maxIterations = $toolsEnabled ? 5 : 1;

        // Anthropic separates the system message from the conversation
        $system           = null;
        $filteredMessages = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system = $msg['content'];
            } else {
                $filteredMessages[] = $msg;
            }
        }

        $headers = [
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ];

        for ($i = 0; $i < $maxIterations; $i++) {
            $payload = [
                'model'       => $model,
                'messages'    => $filteredMessages,
                'max_tokens'  => $maxTokens,
                'temperature' => $temperature,
            ];

            if ($toolsEnabled) {
                $payload['tools'] = $toolsService->getAnthropicToolDefinitions();
            }

            if ($system !== null) {
                $payload['system'] = $system;
            }

            $response = $this->httpPost('https://api.anthropic.com/v1/messages', $payload, $headers);

            if (isset($response['error'])) {
                throw new \RuntimeException($response['error']['message'] ?? 'Anthropic API error');
            }

            $totalTokens += ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0);
            $stopReason   = $response['stop_reason'] ?? '';
            $content      = $response['content'] ?? [];

            $toolUseBlocks = array_filter($content, fn($block) => ($block['type'] ?? '') === 'tool_use');

            if (empty($toolUseBlocks) || $stopReason !== 'tool_use') {
                $textBlocks  = array_filter($content, fn($block) => ($block['type'] ?? '') === 'text');
                $textContent = implode("\n", array_map(fn($block) => $block['text'] ?? '', $textBlocks));

                return [
                    'content' => $textContent,
                    'tokens'  => $totalTokens,
                ];
            }

            // Append assistant turn and collect tool results
            $filteredMessages[] = ['role' => 'assistant', 'content' => $content];

            $toolResults = [];
            foreach ($toolUseBlocks as $toolUse) {
                $toolName = $toolUse['name'] ?? '';
                $toolArgs = $toolUse['input'] ?? [];
                $toolId   = $toolUse['id'] ?? '';

                try {
                    $result = $toolsService->executeTool($toolName, $toolArgs);
                } catch (\Throwable $e) {
                    $result = ['error' => 'Tool execution failed: ' . $e->getMessage()];
                }

                $toolResults[] = [
                    'type'        => 'tool_result',
                    'tool_use_id' => $toolId,
                    'content'     => json_encode($result, JSON_UNESCAPED_UNICODE),
                ];
            }

            $filteredMessages[] = ['role' => 'user', 'content' => $toolResults];
        }

        return [
            'content' => 'Maximale Tool-Iterationen erreicht.',
            'tokens'  => $totalTokens,
        ];
    }
}
