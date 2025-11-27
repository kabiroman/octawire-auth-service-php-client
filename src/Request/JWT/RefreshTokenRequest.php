<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\JWT;

/**
 * Request для обновления токена
 * project_id обязателен (v0.9.3+)
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
            'refresh_token' => $this->refreshToken,
            'project_id' => $this->projectId,
        ];

        if ($this->deviceId !== null) {
            $data['device_id'] = $this->deviceId;
        }

        return $data;
    }
}

