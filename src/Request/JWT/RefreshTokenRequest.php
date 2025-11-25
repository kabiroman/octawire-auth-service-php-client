<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\JWT;

/**
 * Request для обновления токена
 */
class RefreshTokenRequest
{
    public function __construct(
        public readonly string $refreshToken,
        public readonly ?string $deviceId = null
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'refresh_token' => $this->refreshToken,
        ];

        if ($this->deviceId !== null) {
            $data['device_id'] = $this->deviceId;
        }

        return $data;
    }
}

