<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use Exception;

class AuthException extends Exception
{
    public function __construct(
        string $message = 'Authentication failed',
        int $code = 401
    ) {
        parent::__construct($message, $code);
    }
}
