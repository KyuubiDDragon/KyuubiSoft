<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * Retrieves application secrets from the environment with hard fail-fast.
 *
 * Historically several modules silently fell back to a literal
 * `default-key-change-me` when `APP_KEY` was missing — which meant secrets
 * encrypted with that constant were trivially recoverable from any DB dump.
 *
 * `require()` instead throws so the app refuses to boot/handle requests when
 * the key isn't configured. Use it everywhere AES/HMAC keys are loaded.
 */
final class AppKey
{
    /** Known historical defaults that must NEVER be accepted. */
    private const FORBIDDEN_VALUES = [
        'default-key-change-me',
        'default-secret-key-change-me',
        'default-key',
        'changeme',
        'change-me',
    ];

    /**
     * Return the configured secret for `$envName`, or throw if it's missing,
     * empty, or set to one of the known insecure defaults.
     *
     * @throws \RuntimeException
     */
    public static function require(string $envName): string
    {
        $value = $_ENV[$envName] ?? getenv($envName) ?: null;
        if (!is_string($value) || $value === '') {
            throw new \RuntimeException(
                "Required secret '{$envName}' is not configured. Set it in the environment before starting the application."
            );
        }
        if (in_array($value, self::FORBIDDEN_VALUES, true)) {
            throw new \RuntimeException(
                "Required secret '{$envName}' is set to an insecure default value. Generate a fresh random secret (e.g. `openssl rand -hex 32`) and update your environment."
            );
        }
        return $value;
    }
}
