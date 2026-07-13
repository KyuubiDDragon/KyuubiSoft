<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Stream;

/**
 * Preserves the exact raw request body for routes that need to verify a
 * signature over the original bytes (the Alexa webhook).
 *
 * The global JSON body parser reads the PSR-7 stream to `getContents()`. When
 * that stream is not seekable (php://input under some SAPIs), a later
 * `rewind()` is a no-op and the body reads back empty — which makes Amazon's
 * RSA-SHA256 signature check fail on every request even though the signature,
 * skill id and timestamp are all correct.
 *
 * This middleware runs BEFORE the body parser, captures the raw body once for
 * the configured path(s), stores it under the `rawBody` attribute, and replaces
 * the body with a fresh seekable stream so downstream middleware still works.
 * It is a no-op for every other path, so large uploads are never buffered here.
 */
class RawBodyPreserveMiddleware implements MiddlewareInterface
{
    private const CAPTURE_PATHS = [
        '/api/v1/alexa/webhook',
    ];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (!in_array($path, self::CAPTURE_PATHS, true)) {
            return $handler->handle($request);
        }

        $body = $request->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }
        $raw = $body->getContents();

        // Hand a fresh, seekable stream to downstream middleware/handlers so the
        // body is still readable after we consumed it here.
        $resource = fopen('php://temp', 'r+');
        if ($resource !== false) {
            fwrite($resource, $raw);
            rewind($resource);
            $request = $request->withBody(new Stream($resource));
        }

        $request = $request->withAttribute('rawBody', $raw);

        return $handler->handle($request);
    }
}
