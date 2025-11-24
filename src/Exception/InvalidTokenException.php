<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Exception;

/**
 * Исключение при невалидном токене
 */
class InvalidTokenException extends AuthException
{
    public function __construct(
        string $message = "Invalid token",
        int $code = 0,
        ?\Throwable $previous = null,
        array $details = []
    ) {
        parent::__construct($message, $code, $previous, 'INVALID_TOKEN', $details);
    }
}

