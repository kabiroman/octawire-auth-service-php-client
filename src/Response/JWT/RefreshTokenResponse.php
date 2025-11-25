<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Response\JWT;

/**
 * Response для обновления токена
 */
class RefreshTokenResponse
{
    public function __construct(
        public readonly string $accessToken,
        public readonly ?string $refreshToken = null, // Опционально, если включена ротация
        public readonly int $accessTokenExpiresAt = 0,
        public readonly int $refreshTokenExpiresAt = 0,
        public readonly string $keyId = ''
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: $data['access_token'] ?? $data['accessToken'] ?? '',
            refreshToken: $data['refresh_token'] ?? $data['refreshToken'] ?? null,
            accessTokenExpiresAt: (int)($data['access_token_expires_at'] ?? $data['accessTokenExpiresAt'] ?? 0),
            refreshTokenExpiresAt: (int)($data['refresh_token_expires_at'] ?? $data['refreshTokenExpiresAt'] ?? 0),
            keyId: $data['key_id'] ?? $data['keyId'] ?? ''
        );
    }
}

