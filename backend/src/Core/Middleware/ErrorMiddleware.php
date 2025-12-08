<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Exceptions\ValidationException;
use App\Core\Exceptions\AuthException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ForbiddenException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use Throwable;

class ErrorMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (ValidationException $e) {
            return $this->jsonResponse(422, [
                'error' => 'Validation failed',
                'message' => $e->getMessage(),
                'errors' => $e->getErrors(),
            ]);
        } catch (AuthException $e) {
            return $this->jsonResponse(401, [
                'error' => 'Authentication failed',
                'message' => $e->getMessage(),
            ]);
        } catch (ForbiddenException $e) {
            return $this->jsonResponse(403, [
                'error' => 'Forbidden',
                'message' => $e->getMessage(),
            ]);
        } catch (NotFoundException $e) {
            return $this->jsonResponse(404, [
                'error' => 'Not found',
                'message' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            $this->logger?->error('Unhandled exception', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $isDebug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

            return $this->jsonResponse(500, [
                'error' => 'Internal server error',
                'message' => $isDebug ? $e->getMessage() : 'An unexpected error occurred',
                ...($isDebug ? [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : []),
            ]);
        }
    }

    private function jsonResponse(int $status, array $data): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
