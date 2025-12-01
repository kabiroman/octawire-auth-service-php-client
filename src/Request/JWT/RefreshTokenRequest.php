<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\JWT;

/**
 * Request для обновления токена
 * projectId обязателен (v0.9.3+)
 */
class RefreshTokenRequest
{
    public function __construct(
        public readonly string $refreshToken,
        public readonly string $projectId, // Обязательное поле (v0.9.3+)
        public readonly ?string $deviceId = null
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'refreshToken' => $this->refreshToken,
            'projectId' => $this->projectId,
        ];

        if ($this->deviceId !== null) {
            $data['deviceId'] = $this->deviceId;
        }

        return $data;
    }
}
