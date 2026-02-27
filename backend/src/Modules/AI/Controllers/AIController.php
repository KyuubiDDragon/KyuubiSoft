<?php

declare(strict_types=1);

namespace App\Modules\AI\Controllers;

use App\Core\Http\JsonResponse;
use App\Modules\AI\Services\AIService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AIController
{
    public function __construct(
        private readonly AIService $aiService
    ) {}

    /**
     * Get AI settings
     */
    public function getSettings(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $settings = $this->aiService->getSettings($userId);

        return JsonResponse::create($settings ?? [
            'is_configured' => false,
            'has_api_key' => false,
        ]);
    }

    /**
     * Save AI settings
     */
    public function saveSettings(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        try {
            $settings = $this->aiService->saveSettings($userId, $data);

            return JsonResponse::create($settings);
        } catch (\Exception $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Remove API key
     */
    public function removeApiKey(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $this->aiService->removeApiKey($userId);

        return JsonResponse::success(null, 'API key removed');
    }

    /**
     * Check if AI is configured
     */
    public function checkStatus(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $isConfigured = $this->aiService->isConfigured($userId);

        return JsonResponse::create([
            'is_configured' => $isConfigured,
            'message' => $isConfigured
                ? 'AI assistant is ready'
                : 'Please configure your API key in settings to use the AI assistant'
        ]);
    }

    /**
     * Send chat message
     */
    public function chat(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['message'])) {
            return JsonResponse::error('Message is required', 400);
        }

        try {
            $result = $this->aiService->chat(
                $userId,
                $data['message'],
                $data['conversation_id'] ?? null,
                $data['context'] ?? []
            );

            return JsonResponse::create($result);
        } catch (\RuntimeException $e) {
            return JsonResponse::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            // Log the error for debugging
            error_log('AI Chat Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return JsonResponse::serverError('Ein Fehler ist aufgetreten: ' . $e->getMessage());
        }
    }

    /**
     * Get conversations
     */
    public function getConversations(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $conversations = $this->aiService->getConversations($userId);

        return JsonResponse::create($conversations);
    }

    /**
     * Get conversation
     */
    public function getConversation(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $conversationId = $args['id'];

        $conversation = $this->aiService->getConversation($userId, $conversationId);

        if (!$conversation) {
            return JsonResponse::notFound('Conversation not found');
        }

        return JsonResponse::create($conversation);
    }

    /**
     * Delete conversation
     */
    public function deleteConversation(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $conversationId = $args['id'];

        $deleted = $this->aiService->deleteConversation($userId, $conversationId);

        if (!$deleted) {
            return JsonResponse::notFound('Conversation not found');
        }

        return JsonResponse::success(null, 'Conversation deleted');
    }

    /**
     * Get prompts
     */
    public function getPrompts(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $prompts = $this->aiService->getPrompts($userId);

        return JsonResponse::create($prompts);
    }

    /**
     * Save prompt
     */
    public function savePrompt(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['name']) || empty($data['prompt_template'])) {
            return JsonResponse::error('Name and prompt template are required', 400);
        }

        $prompt = $this->aiService->savePrompt($userId, $data);

        return JsonResponse::create($prompt);
    }

    /**
     * Delete prompt
     */
    public function deletePrompt(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $promptId = $args['id'];

        $deleted = $this->aiService->deletePrompt($userId, $promptId);

        if (!$deleted) {
            return JsonResponse::notFound('Prompt not found');
        }

        return JsonResponse::success(null, 'Prompt deleted');
    }
}
