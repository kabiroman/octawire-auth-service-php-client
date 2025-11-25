<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Response\JWT;

use Kabiroman\Octawire\AuthService\Client\Model\PublicKeyInfo;

/**
 * Response для получения публичного ключа
 */
class GetPublicKeyResponse
{
    /**
     * @var PublicKeyInfo[]
     */
    public readonly array $activeKeys;

    public function __construct(
        public readonly string $publicKeyPem,
        public readonly string $algorithm,
        public readonly string $keyId,
        public readonly string $projectId,
        public readonly int $cacheUntil,
        array $activeKeys = []
    ) {
        $this->activeKeys = array_map(
            fn(array $key) => PublicKeyInfo::fromArray($key),
            $activeKeys
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            publicKeyPem: $data['public_key_pem'] ?? $data['publicKeyPem'] ?? '',
            algorithm: $data['algorithm'] ?? '',
            keyId: $data['key_id'] ?? $data['keyId'] ?? '',
            projectId: $data['project_id'] ?? $data['projectId'] ?? '',
            cacheUntil: (int)($data['cache_until'] ?? $data['cacheUntil'] ?? 0),
            activeKeys: $data['active_keys'] ?? $data['activeKeys'] ?? []
        );
    }
}

