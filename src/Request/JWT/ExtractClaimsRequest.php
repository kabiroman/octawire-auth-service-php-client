<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\JWT;

/**
 * Request для извлечения claims из токена
 * НЕ принимает project_id - определяется автоматически из токена
 */
class ExtractClaimsRequest
{
    public function __construct(
        public readonly string $token,
        public readonly ?array $claimKeys = null // Список ключей для извлечения (null = все)
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'token' => $this->token,
        ];

        if ($this->claimKeys !== null) {
            $data['claim_keys'] = $this->claimKeys;
        }

        return $data;
    }
}

