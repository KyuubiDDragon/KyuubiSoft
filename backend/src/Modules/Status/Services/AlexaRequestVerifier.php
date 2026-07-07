<?php

declare(strict_types=1);

namespace App\Modules\Status\Services;

/**
 * Verifies that an incoming HTTP request genuinely originates from Amazon Alexa
 * for the configured skill, following Amazon's security requirements for
 * self-hosted (HTTPS) skill endpoints:
 *
 *   1. The SignatureCertChainUrl points at Amazon's cert host over HTTPS.
 *   2. The signing certificate is valid (dates) and lists echo-api.amazon.com
 *      in its Subject Alternative Names.
 *   3. The request body's RSA-SHA256 signature matches that certificate.
 *   4. The request timestamp is within 150 seconds (replay protection).
 *   5. The applicationId matches the configured skill id (checked by caller).
 *
 * Without a valid Amazon signature, a request is rejected — the endpoint is
 * public but only Amazon can produce a passing request for this skill.
 */
class AlexaRequestVerifier
{
    private const TOLERANCE_SECONDS = 150;
    private const ECHO_SAN = 'echo-api.amazon.com';

    public function __construct(
        private readonly string $certCacheDir
    ) {}

    /**
     * @return array{ok:bool,error:?string}
     */
    public function verify(string $rawBody, string $certChainUrl, string $signature): array
    {
        if ($certChainUrl === '' || $signature === '') {
            return $this->fail('Missing signature headers');
        }

        if (!$this->isValidCertChainUrl($certChainUrl)) {
            return $this->fail('Invalid SignatureCertChainUrl');
        }

        $pem = $this->loadCertificate($certChainUrl);
        if ($pem === null) {
            return $this->fail('Unable to load signing certificate');
        }

        $cert = openssl_x509_parse($pem);
        if ($cert === false) {
            return $this->fail('Unparseable signing certificate');
        }

        $now = time();
        if (($cert['validFrom_time_t'] ?? 0) > $now || ($cert['validTo_time_t'] ?? 0) < $now) {
            return $this->fail('Signing certificate expired or not yet valid');
        }

        if (!$this->certHasEchoSan($cert)) {
            return $this->fail('Certificate missing echo-api.amazon.com SAN');
        }

        $publicKey = openssl_pkey_get_public($pem);
        if ($publicKey === false) {
            return $this->fail('Unable to extract public key');
        }

        $decodedSignature = base64_decode($signature, true);
        if ($decodedSignature === false) {
            return $this->fail('Signature is not valid base64');
        }

        $verified = openssl_verify($rawBody, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            return $this->fail('Request body signature mismatch');
        }

        return ['ok' => true, 'error' => null];
    }

    /**
     * Validate the request timestamp against the allowed clock skew.
     */
    public function isTimestampFresh(?string $isoTimestamp): bool
    {
        if (!$isoTimestamp) {
            return false;
        }
        $ts = strtotime($isoTimestamp);
        if ($ts === false) {
            return false;
        }
        return abs(time() - $ts) <= self::TOLERANCE_SECONDS;
    }

    // ------------------------------------------------------------------

    private function isValidCertChainUrl(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'], $parts['path'])) {
            return false;
        }
        if (strtolower($parts['scheme']) !== 'https') {
            return false;
        }
        if (strtolower($parts['host']) !== 's3.amazonaws.com') {
            return false;
        }
        if (isset($parts['port']) && (int) $parts['port'] !== 443) {
            return false;
        }
        // Normalise "/./" and reject traversal, then require the echo.api prefix.
        $path = $parts['path'];
        if (str_contains($path, '..')) {
            return false;
        }
        return str_starts_with($path, '/echo.api/');
    }

    private function certHasEchoSan(array $cert): bool
    {
        $san = $cert['extensions']['subjectAltName'] ?? '';
        return str_contains($san, self::ECHO_SAN);
    }

    /**
     * Download (and cache) the PEM certificate chain for a validated URL.
     */
    private function loadCertificate(string $url): ?string
    {
        if (!is_dir($this->certCacheDir)) {
            @mkdir($this->certCacheDir, 0700, true);
        }
        $cacheFile = $this->certCacheDir . '/' . sha1($url) . '.pem';

        if (is_file($cacheFile)) {
            $cached = file_get_contents($cacheFile);
            if ($cached !== false && $cached !== '') {
                return $cached;
            }
        }

        $context = stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => 5],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $pem = @file_get_contents($url, false, $context);
        if ($pem === false || $pem === '') {
            return null;
        }

        @file_put_contents($cacheFile, $pem);
        return $pem;
    }

    /**
     * @return array{ok:bool,error:string}
     */
    private function fail(string $reason): array
    {
        return ['ok' => false, 'error' => $reason];
    }
}
