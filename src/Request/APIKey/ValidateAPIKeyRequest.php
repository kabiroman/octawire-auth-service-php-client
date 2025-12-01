<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\APIKey;

/**
 * Request для валидации API ключа
 * projectId обязателен (v0.9.4+)
 */
class ValidateAPIKeyRequest
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $projectId, // Обязательное поле (v0.9.4+)
        public readonly ?array $requiredScopes = null
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'apiKey' => $this->apiKey,
            'projectId' => $this->projectId,
        ];

        if ($this->requiredScopes !== null) {
            $data['requiredScopes'] = $this->requiredScopes;
        }

        return $data;
    }
}
