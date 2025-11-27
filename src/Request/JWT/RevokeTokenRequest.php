<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\JWT;

/**
 * Request для отзыва токена
 * project_id обязателен (v0.9.3+)
 */
class RevokeTokenRequest
{
    public function __construct(
        public readonly string $token,
        public readonly string $projectId, // Обязательное поле (v0.9.3+)
        public readonly ?int $ttl = null // Время жизни в blacklist (по умолчанию = TTL токена)
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'token' => $this->token,
            'project_id' => $this->projectId,
        ];

        if ($this->ttl !== null) {
            $data['ttl'] = $this->ttl;
        }

        return $data;
    }
}

