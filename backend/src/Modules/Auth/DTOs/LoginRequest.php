<?php

declare(strict_types=1);

namespace App\Modules\Auth\DTOs;

class LoginRequest
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly ?string $twoFactorCode = null
    ) {}

    public function validate(): array
    {
        $errors = [];

        if (empty($this->email)) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        if (empty($this->password)) {
            $errors['password'] = 'Password is required';
        }

        return $errors;
    }
}
