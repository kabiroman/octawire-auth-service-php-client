<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\JWT;

/**
 * Request для валидации токена
 * НЕ принимает project_id - определяется автоматически из токена
 */
class ValidateTokenRequest
{
    public function __construct(
        public readonly string $token,
        public readonly bool $checkBlacklist = true
    ) {
    }

    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'check_blacklist' => $this->checkBlacklist,
        ];
    }
}

