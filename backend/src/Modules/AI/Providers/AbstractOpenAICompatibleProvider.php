<?php

declare(strict_types=1);

namespace App\Modules\AI\Providers;

use App\Modules\AI\Services\AIToolsService;

/**
 * Base implementation for OpenAI-compatible chat completion APIs.
 *
 * OpenAI, OpenRouter, and Custom endpoints share identical request/response
 * schemas. Subclasses override getEndpointUrl() and getHeaders() only.
 */
abstract class AbstractOpenAICompatibleProvider implements AIProviderInterface
{
    use HttpPostTrait;

    abstract protected function getEndpointUrl(?string $baseUrl): string;

    abstract protected function getHeaders(string $apiKey): array;

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
        $url           = $this->getEndpointUrl($baseUrl);
        $headers       = $this->getHeaders($apiKey);

        for ($i = 0; $i < $maxIterations; $i++) {
            $payload = [
                'model'       => $model,
                'messages'    => $messages,
                'max_tokens'  => $maxTokens,
                'temperature' => $temperature,
            ];

            if ($toolsEnabled) {
                $payload['tools']       = $toolsService->getToolDefinitions();
                $payload['tool_choice'] = 'auto';
            }

            $response = $this->httpPost($url, $payload, $headers);

            if (isset($response['error'])) {
                throw new \RuntimeException($response['error']['message'] ?? $this->getName() . ' API error');
            }

            $totalTokens += $response['usage']['total_tokens'] ?? 0;
            $choice       = $response['choices'][0] ?? [];
            $message      = $choice['message'] ?? [];
            $finishReason = $choice['finish_reason'] ?? '';

            // No tool calls – return the final answer
            if ($finishReason !== 'tool_calls' || empty($message['tool_calls'])) {
                return [
                    'content' => $message['content'] ?? '',
                    'tokens'  => $totalTokens,
                ];
            }

            // Append assistant message and execute each tool call
            $messages[] = $message;

            foreach ($message['tool_calls'] as $toolCall) {
                $toolName = $toolCall['function']['name'] ?? '';
                $toolArgs = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?: [];

                try {
                    $toolResult = $toolsService->executeTool($toolName, $toolArgs);
                } catch (\Throwable $e) {
                    $toolResult = ['error' => 'Tool execution failed: ' . $e->getMessage()];
                }

                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $toolCall['id'],
                    'content'      => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        return [
            'content' => 'Maximale Tool-Iterationen erreicht.',
            'tokens'  => $totalTokens,
        ];
    }
}
