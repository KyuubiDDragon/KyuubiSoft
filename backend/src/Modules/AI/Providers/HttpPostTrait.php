<?php

declare(strict_types=1);

namespace App\Modules\AI\Providers;

/**
 * Provides a simple cURL-based HTTP POST helper for AI providers.
 *
 * NOTE: Phase 3 will replace this with Guzzle HTTP client. All providers
 * use this single trait so the migration can be done in one place.
 */
trait HttpPostTrait
{
    private function httpPost(string $url, array $data, array $headers, int $timeout = 120): array
    {
        $headers[] = 'Content-Type: application/json';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('HTTP request failed: ' . $error);
        }

        return json_decode($response, true) ?? ['error' => 'Invalid JSON response'];
    }
}
