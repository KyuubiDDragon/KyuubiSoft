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

        // NB: reading the cache is a LOCAL file read — unaffected by
        // allow_url_fopen. Only the remote fetch below needs cURL. A cached file
        // that is empty or not a PEM (e.g. a truncated write) is ignored and
        // re-fetched, so a corrupt cache entry can never wedge verification.
        if (is_file($cacheFile)) {
            $cached = file_get_contents($cacheFile);
            if (is_string($cached) && str_contains($cached, 'BEGIN CERTIFICATE')) {
                return $cached;
            }
        }

        $pem = $this->fetchOverHttps($url);
        if ($pem === null || $pem === '' || !str_contains($pem, 'BEGIN CERTIFICATE')) {
            return null;
        }

        @file_put_contents($cacheFile, $pem);
        return $pem;
    }

    /**
     * Fetch a URL body over HTTPS using cURL.
     *
     * We deliberately do NOT use file_get_contents(): production PHP hardening
     * disables `allow_url_fopen`, so the http(s) stream wrapper is unavailable
     * and file_get_contents() on a URL fails with "no suitable wrapper could be
     * found". cURL uses libcurl and works regardless. TLS verification stays
     * strict — the cert chain URL has already been validated by the caller.
     */
    private function fetchOverHttps(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if (!is_string($body) || $body === '' || $status !== 200) {
            return null;
        }

        return $body;
    }

    /**
     * @return array{ok:bool,error:string}
     */
    private function fail(string $reason): array
    {
        return ['ok' => false, 'error' => $reason];
    }
}
