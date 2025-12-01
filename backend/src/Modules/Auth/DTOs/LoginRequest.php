<?php

declare(strict_types=1);

namespace App\Modules\Auth\DTOs;

class LoginRequest
{
    public readonly bool $isEmail;

    public function __construct(
        public readonly string $login, // Email oder Benutzername
        public readonly string $password,
        public readonly ?string $twoFactorCode = null
    ) {
        $this->isEmail = filter_var($this->login, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function validate(): array
    {
        $errors = [];

        if (empty($this->login)) {
            $errors['login'] = 'E-Mail oder Benutzername ist erforderlich';
        }

        if (empty($this->password)) {
            $errors['password'] = 'Passwort ist erforderlich';
        }

        return $errors;
    }
}
