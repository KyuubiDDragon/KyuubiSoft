<?php

declare(strict_types=1);

namespace App\Modules\AI\Providers;

use App\Modules\AI\Services\AIToolsService;

class OllamaProvider implements AIProviderInterface
{
    use HttpPostTrait;

    public function getName(): string
    {
        return 'ollama';
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
        $url = rtrim($baseUrl ?? 'http://localhost:11434', '/') . '/api/chat';

        $response = $this->httpPost($url, [
            'model'    => $model,
            'messages' => $messages,
            'options'  => [
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
            'tokens'  => $response['eval_count'] ?? 0,
        ];
    }
}
