<?php

declare(strict_types=1);

namespace App\Modules\AI\Providers;

use App\Modules\AI\Services\AIToolsService;

interface AIProviderInterface
{
    /**
     * Send a chat completion request to the AI provider.
     *
     * @param  string          $apiKey       Provider API key (empty string for Ollama)
     * @param  string          $model        Model identifier
     * @param  array           $messages     Conversation history [['role' => ..., 'content' => ...]]
     * @param  int             $maxTokens    Maximum tokens to generate
     * @param  float           $temperature  Sampling temperature (0.0–2.0)
     * @param  bool            $toolsEnabled Whether to enable tool/function calling
     * @param  AIToolsService  $toolsService Service providing tool definitions and execution
     * @param  string|null     $baseUrl      Base URL override (required for Ollama and Custom)
     * @return array{content: string, tokens: int}
     */
    public function call(
        string $apiKey,
        string $model,
        array $messages,
        int $maxTokens,
        float $temperature,
        bool $toolsEnabled,
        AIToolsService $toolsService,
        ?string $baseUrl = null
    ): array;

    /**
     * Unique provider identifier (e.g. 'openai', 'anthropic').
     */
    public function getName(): string;
}
