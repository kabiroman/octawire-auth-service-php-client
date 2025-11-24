<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Exception;

/**
 * Исключение при истекшем токене
 */
class TokenExpiredException extends AuthException
{
    public function __construct(
        string $message = "Token has expired",
        int $code = 0,
        ?\Throwable $previous = null,
        array $details = []
    ) {
        parent::__construct($message, $code, $previous, 'TOKEN_EXPIRED', $details);
    }
}

