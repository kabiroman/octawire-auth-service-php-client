<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\APIKey;

/**
 * Request для валидации API ключа
 * НЕ принимает project_id - определяется автоматически из ключа
 */
class ValidateAPIKeyRequest
{
    public function __construct(
        public readonly string $apiKey,
        public readonly ?array $requiredScopes = null
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'api_key' => $this->apiKey,
        ];

        if ($this->requiredScopes !== null) {
            $data['required_scopes'] = $this->requiredScopes;
        }

        return $data;
    }
}

