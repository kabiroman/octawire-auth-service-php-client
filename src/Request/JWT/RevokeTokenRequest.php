<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\JWT;

/**
 * Request для отзыва токена
 * НЕ принимает project_id - определяется автоматически из токена
 */
class RevokeTokenRequest
{
    public function __construct(
        public readonly string $token,
        public readonly ?int $ttl = null // Время жизни в blacklist (по умолчанию = TTL токена)
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'token' => $this->token,
        ];

        if ($this->ttl !== null) {
            $data['ttl'] = $this->ttl;
        }

        return $data;
    }
}

