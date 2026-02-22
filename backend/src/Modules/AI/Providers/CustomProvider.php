<?php

declare(strict_types=1);

namespace App\Modules\AI\Providers;

class CustomProvider extends AbstractOpenAICompatibleProvider
{
    public function getName(): string
    {
        return 'custom';
    }

    protected function getEndpointUrl(?string $baseUrl): string
    {
        if (empty($baseUrl)) {
            throw new \InvalidArgumentException('Custom provider requires a base URL.');
        }

        return rtrim($baseUrl, '/') . '/v1/chat/completions';
    }

    protected function getHeaders(string $apiKey): array
    {
        return ['Authorization: Bearer ' . $apiKey];
    }
}
