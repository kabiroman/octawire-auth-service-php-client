<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\JWT;

/**
 * Request для парсинга токена без валидации
 * project_id обязателен (v0.9.3+)
 */
class ParseTokenRequest
{
    public function __construct(
        public readonly string $token,
        public readonly string $projectId // Обязательное поле (v0.9.3+)
    ) {
    }

    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'project_id' => $this->projectId,
        ];
    }
}

