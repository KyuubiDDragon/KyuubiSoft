<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use Exception;

class ForbiddenException extends Exception
{
    public function __construct(
        string $message = 'Access forbidden',
        int $code = 403
    ) {
        parent::__construct($message, $code);
    }
}
