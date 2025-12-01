<?php

declare(strict_types=1);

namespace App\Core\Security;

class PasswordHasher
{
    private const ALGORITHM = PASSWORD_ARGON2ID;
    private const OPTIONS = [
        'memory_cost' => 65536,  // 64 MB
        'time_cost' => 4,        // 4 iterations
        'threads' => 3,          // 3 parallel threads
    ];

    /**
     * Hash a password using Argon2id
     */
    public function hash(string $password): string
    {
        return password_hash($password, self::ALGORITHM, self::OPTIONS);
    }

    /**
     * Verify a password against a hash
     */
    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if a hash needs to be rehashed (e.g., if options changed)
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::ALGORITHM, self::OPTIONS);
    }

    /**
     * Validate password strength
     *
     * Requirements:
     * - Minimum 12 characters
     * - At least 1 uppercase letter
     * - At least 1 lowercase letter
     * - At least 1 number
     * - At least 1 special character
     */
    public function validateStrength(string $password): array
    {
        $errors = [];

        if (strlen($password) < 12) {
            $errors[] = 'Password must be at least 12 characters long';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }

        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }

        return $errors;
    }

    /**
     * Check if password meets minimum requirements
     */
    public function isStrong(string $password): bool
    {
        return empty($this->validateStrength($password));
    }
}
