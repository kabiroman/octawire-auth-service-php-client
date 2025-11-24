<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Exception;

use Exception;

/**
 * Базовое исключение для Auth Service клиента
 */
class AuthException extends Exception
{
    protected ?string $errorCode = null;
    protected array $details = [];

    public function __construct(
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $errorCode = null,
        array $details = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
        $this->details = $details;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}

