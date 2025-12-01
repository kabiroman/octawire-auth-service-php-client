<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\JWT;

/**
 * Request для извлечения claims из токена
 * projectId обязателен (v0.9.3+)
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
        $data = [
            'token' => $this->token,
            'projectId' => $this->projectId,
        ];

        if ($this->claimKeys !== null) {
            $data['claimKeys'] = $this->claimKeys;
        }

        return $data;
    }
}
