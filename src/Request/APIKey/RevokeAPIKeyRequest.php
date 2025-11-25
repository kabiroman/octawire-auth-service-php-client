<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\APIKey;

/**
 * Request для отзыва API ключа
 * Требует project_id и либо key_id, либо api_key
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
            'project_id' => $this->projectId,
        ];

        if ($this->keyId !== null) {
            $data['key_id'] = $this->keyId;
        }
        if ($this->apiKey !== null) {
            $data['api_key'] = $this->apiKey;
        }

        return $data;
    }
}

