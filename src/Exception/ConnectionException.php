<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Exception;

/**
 * Исключение при ошибке подключения к сервису
 */
class ConnectionException extends AuthException
{
    public function __construct(
        string $message = "Failed to connect to auth service",
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous, 'CONNECTION_FAILED');
    }
}

