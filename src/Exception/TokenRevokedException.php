<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Exception;

/**
 * Исключение при отозванном токене
 */
class TokenRevokedException extends AuthException
{
    public function __construct(
        string $message = "Token has been revoked",
        int $code = 0,
        ?\Throwable $previous = null,
        array $details = []
    ) {
        parent::__construct($message, $code, $previous, 'TOKEN_REVOKED', $details);
    }
}

