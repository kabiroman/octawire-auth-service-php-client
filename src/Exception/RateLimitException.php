<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Exception;

/**
 * Исключение при превышении лимита запросов
 */
class RateLimitException extends AuthException
{
    protected ?int $limit = null;
    protected ?int $remaining = null;
    protected ?int $window = null;

    public function __construct(
        string $message = "Rate limit exceeded",
        int $code = 0,
        ?\Throwable $previous = null,
        ?int $limit = null,
        ?int $remaining = null,
        ?int $window = null
    ) {
        parent::__construct($message, $code, $previous, 'RATE_LIMIT_EXCEEDED', [
            'limit' => $limit,
            'remaining' => $remaining,
            'window' => $window,
        ]);
        $this->limit = $limit;
        $this->remaining = $remaining;
        $this->window = $window;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getRemaining(): ?int
    {
        return $this->remaining;
    }

    public function getWindow(): ?int
    {
        return $this->window;
    }
}

