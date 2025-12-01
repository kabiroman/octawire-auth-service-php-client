<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\APIKey;

/**
 * Request для отзыва API ключа
 * Требует projectId и либо keyId, либо apiKey
 */
class RevokeAPIKeyRequest
{
    public function __construct(
        public readonly string $projectId,
        public readonly ?string $keyId = null,
        public readonly ?string $apiKey = null
    ) {
        if ($keyId === null && $apiKey === null) {
            throw new \InvalidArgumentException('Either keyId or apiKey must be provided');
        }
    }

    public function toArray(): array
    {
        $data = [
            'projectId' => $this->projectId,
        ];

        if ($this->keyId !== null) {
            $data['keyId'] = $this->keyId;
        }
        if ($this->apiKey !== null) {
            $data['apiKey'] = $this->apiKey;
        }

        return $data;
    }
}
