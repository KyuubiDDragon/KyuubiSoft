<?php

declare(strict_types=1);

namespace App\Modules\AI\Providers;

class OpenAIProvider extends AbstractOpenAICompatibleProvider
{
    public function getName(): string
    {
        return 'openai';
    }

    protected function getEndpointUrl(?string $baseUrl): string
    {
        return 'https://api.openai.com/v1/chat/completions';
    }

    protected function getHeaders(string $apiKey): array
    {
        return ['Authorization: Bearer ' . $apiKey];
    }
}
