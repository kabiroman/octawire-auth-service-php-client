<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\JWT;

/**
 * Request для парсинга токена без валидации
 * НЕ принимает project_id - определяется автоматически из токена
 */
class ParseTokenRequest
{
    public function __construct(
        public readonly string $token
    ) {
    }

    public function toArray(): array
    {
        return [
            'token' => $this->token,
        ];
    }
}

