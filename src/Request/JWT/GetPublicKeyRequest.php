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
            'project_id' => $this->projectId,
        ];

        if ($this->keyId !== null) {
            $data['key_id'] = $this->keyId;
        }

        return $data;
    }
}

