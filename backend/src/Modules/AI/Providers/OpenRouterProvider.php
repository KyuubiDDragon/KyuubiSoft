<?php

declare(strict_types=1);

namespace App\Modules\AI\Providers;

class OpenRouterProvider extends AbstractOpenAICompatibleProvider
{
    public function getName(): string
    {
        return 'openrouter';
    }

    protected function getEndpointUrl(?string $baseUrl): string
    {
        return 'https://openrouter.ai/api/v1/chat/completions';
    }

    protected function getHeaders(string $apiKey): array
    {
        return [
            'Authorization: Bearer ' . $apiKey,
            'HTTP-Referer: ' . ($_ENV['APP_URL'] ?? 'http://localhost'),
        ];
    }
}
