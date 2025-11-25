<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Response\APIKey;

/**
 * Response для создания API ключа
 */
class CreateAPIKeyResponse
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $keyId,
        public readonly int $expiresAt, // 0 = без ограничения
        public readonly int $createdAt
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            apiKey: $data['api_key'] ?? $data['apiKey'] ?? '',
            keyId: $data['key_id'] ?? $data['keyId'] ?? '',
            expiresAt: (int)($data['expires_at'] ?? $data['expiresAt'] ?? 0),
            createdAt: (int)($data['created_at'] ?? $data['createdAt'] ?? 0)
        );
    }
}

