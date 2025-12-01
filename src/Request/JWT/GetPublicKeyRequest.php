<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\JWT;

/**
 * Request для получения публичного ключа
 */
class GetPublicKeyRequest
{
    public function __construct(
        public readonly string $projectId,
        public readonly ?string $keyId = null // Опционально - конкретный ключ
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'projectId' => $this->projectId,
        ];

        if ($this->keyId !== null) {
            $data['keyId'] = $this->keyId;
        }

        return $data;
    }
}
