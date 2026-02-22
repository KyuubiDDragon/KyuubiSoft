<?php

declare(strict_types=1);

namespace App\Modules\AI\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Provides a Guzzle-based HTTP POST helper for AI providers.
 */
trait HttpPostTrait
{
    private function httpPost(string $url, array $data, array $headers, int $timeout = 120): array
    {
        $client = new Client(['timeout' => $timeout]);

        // Convert flat "Name: Value" header strings to associative array for Guzzle
        $parsedHeaders = ['Content-Type' => 'application/json'];
        foreach ($headers as $header) {
            [$name, $value] = explode(': ', $header, 2);
            $parsedHeaders[$name] = $value;
        }

        try {
            $response = $client->post($url, [
                'headers' => $parsedHeaders,
                'json'    => $data,
            ]);

            return json_decode($response->getBody()->getContents(), true)
                ?? ['error' => 'Invalid JSON response'];
        } catch (GuzzleException $e) {
            throw new \RuntimeException('HTTP request failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
