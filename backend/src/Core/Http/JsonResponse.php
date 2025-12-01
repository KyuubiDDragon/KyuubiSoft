<?php

declare(strict_types=1);

namespace App\Core\Http;

use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;

class JsonResponse
{
    /**
     * Create a JSON response
     */
    public static function create(
        mixed $data = null,
        int $status = 200,
        array $headers = []
    ): ResponseInterface {
        $response = new Response();

        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->getBody()->write($payload);

        $response = $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    /**
     * Success response
     */
    public static function success(mixed $data = null, string $message = 'Success', int $status = 200): ResponseInterface
    {
        return self::create([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Created response
     */
    public static function created(mixed $data = null, string $message = 'Created'): ResponseInterface
    {
        return self::success($data, $message, 201);
    }

    /**
     * No content response
     */
    public static function noContent(): ResponseInterface
    {
        return (new Response())->withStatus(204);
    }

    /**
     * Error response
     */
    public static function error(string $message, int $status = 400, array $errors = []): ResponseInterface
    {
        $data = [
            'success' => false,
            'error' => $message,
        ];

        if (!empty($errors)) {
            $data['errors'] = $errors;
        }

        return self::create($data, $status);
    }

    /**
     * Unauthorized response
     */
    public static function unauthorized(string $message = 'Unauthorized'): ResponseInterface
    {
        return self::error($message, 401);
    }

    /**
     * Forbidden response
     */
    public static function forbidden(string $message = 'Forbidden'): ResponseInterface
    {
        return self::error($message, 403);
    }

    /**
     * Not found response
     */
    public static function notFound(string $message = 'Resource not found'): ResponseInterface
    {
        return self::error($message, 404);
    }

    /**
     * Validation error response
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): ResponseInterface
    {
        return self::error($message, 422, $errors);
    }

    /**
     * Server error response
     */
    public static function serverError(string $message = 'Internal server error'): ResponseInterface
    {
        return self::error($message, 500);
    }

    /**
     * Paginated response
     */
    public static function paginated(
        array $items,
        int $total,
        int $page,
        int $perPage
    ): ResponseInterface {
        return self::success([
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
                'has_more' => ($page * $perPage) < $total,
            ],
        ]);
    }
}
