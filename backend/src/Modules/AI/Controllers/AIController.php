<?php

declare(strict_types=1);

namespace App\Modules\AI\Controllers;

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

        $response->getBody()->write(json_encode($settings ?? [
            'is_configured' => false,
            'has_api_key' => false,
        ]));
        return $response->withHeader('Content-Type', 'application/json');
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

            $response->getBody()->write(json_encode($settings));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'error' => $e->getMessage()
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Remove API key
     */
    public function removeApiKey(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $this->aiService->removeApiKey($userId);

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Check if AI is configured
     */
    public function checkStatus(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $isConfigured = $this->aiService->isConfigured($userId);

        $response->getBody()->write(json_encode([
            'is_configured' => $isConfigured,
            'message' => $isConfigured
                ? 'AI assistant is ready'
                : 'Please configure your API key in settings to use the AI assistant'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Send chat message
     */
    public function chat(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['message'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Message is required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $result = $this->aiService->chat(
                $userId,
                $data['message'],
                $data['conversation_id'] ?? null,
                $data['context'] ?? []
            );

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'error' => $e->getMessage()
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Get conversations
     */
    public function getConversations(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $conversations = $this->aiService->getConversations($userId);

        $response->getBody()->write(json_encode($conversations));
        return $response->withHeader('Content-Type', 'application/json');
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
            $response->getBody()->write(json_encode([
                'error' => 'Conversation not found'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode($conversation));
        return $response->withHeader('Content-Type', 'application/json');
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
            $response->getBody()->write(json_encode([
                'error' => 'Conversation not found'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get prompts
     */
    public function getPrompts(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $prompts = $this->aiService->getPrompts($userId);

        $response->getBody()->write(json_encode($prompts));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Save prompt
     */
    public function savePrompt(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['name']) || empty($data['prompt_template'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Name and prompt template are required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $prompt = $this->aiService->savePrompt($userId, $data);

        $response->getBody()->write(json_encode($prompt));
        return $response->withHeader('Content-Type', 'application/json');
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
            $response->getBody()->write(json_encode([
                'error' => 'Prompt not found'
            ]));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
