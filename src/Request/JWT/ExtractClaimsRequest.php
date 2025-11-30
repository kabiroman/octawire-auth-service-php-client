<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\JWT;

/**
 * Request для извлечения claims из токена
 * project_id обязателен (v0.9.3+)
 */
class ExtractClaimsRequest
{
    public function __construct(
        public readonly string $token,
        public readonly string $projectId, // Обязательное поле (v0.9.3+)
        public readonly ?array $claimKeys = null // Список ключей для извлечения (null = все)
    ) {
    }

    public function toArray(): array
    {
        // project_id передается через metadata, не через payload (v1.0+)
        $data = [
            'token' => $this->token,
        ];

        if ($this->claimKeys !== null) {
            $data['claim_keys'] = $this->claimKeys;
        }

        return $data;
    }
}

