<?php

declare(strict_types=1);

namespace App\Modules\Auth\DTOs;

class RegisterRequest
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $passwordConfirmation,
        public readonly ?string $username = null
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
        } elseif (strlen($this->password) < 12) {
            $errors['password'] = 'Password must be at least 12 characters';
        }

        if ($this->password !== $this->passwordConfirmation) {
            $errors['password_confirmation'] = 'Passwords do not match';
        }

        if ($this->username !== null && strlen($this->username) < 3) {
            $errors['username'] = 'Username must be at least 3 characters';
        }

        return $errors;
    }
}
