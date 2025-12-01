<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Response\JWT;

/**
 * Response для выдачи нового JWT токена
 */
class IssueTokenResponse
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly int $expiresIn,
        public readonly int $accessTokenExpiresAt = 0,
        public readonly int $refreshTokenExpiresAt = 0,
        public readonly int $refreshExpiresIn = 0,
        public readonly string $keyId = ''
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: $data['accessToken'] ?? $data['access_token'] ?? '',
            refreshToken: $data['refreshToken'] ?? $data['refresh_token'] ?? '',
            expiresIn: (int)($data['expiresIn'] ?? $data['expires_in'] ?? 0),
            accessTokenExpiresAt: (int)($data['accessTokenExpiresAt'] ?? $data['access_token_expires_at'] ?? 0),
            refreshTokenExpiresAt: (int)($data['refreshTokenExpiresAt'] ?? $data['refresh_token_expires_at'] ?? 0),
            refreshExpiresIn: (int)($data['refreshExpiresIn'] ?? $data['refresh_expires_in'] ?? 0),
            keyId: $data['keyId'] ?? $data['key_id'] ?? ''
        );
    }
}
