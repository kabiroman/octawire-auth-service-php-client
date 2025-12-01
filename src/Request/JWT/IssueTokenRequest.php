<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\JWT;

/**
 * Request для выдачи нового JWT токена (access + refresh)
 * @see https://github.com/octawire/auth-service/blob/main/docs/protocol/JATP_METHODS_1.0.json
 */
class IssueTokenRequest
{
    public function __construct(
        public readonly string $userId,
        public readonly string $projectId, // Обязательное поле (v0.9.3+)
        public readonly ?array $claims = null,
        public readonly ?int $accessTokenTtl = null,
        public readonly ?int $refreshTokenTtl = null,
        public readonly ?string $deviceId = null,
        public readonly ?string $tokenType = null // 'access' | 'refresh'
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'userId' => $this->userId,
            'projectId' => $this->projectId,
        ];

        if ($this->claims !== null) {
            $data['claims'] = $this->claims;
        }
        if ($this->accessTokenTtl !== null) {
            $data['accessTokenTtl'] = $this->accessTokenTtl;
        }
        if ($this->refreshTokenTtl !== null) {
            $data['refreshTokenTtl'] = $this->refreshTokenTtl;
        }
        if ($this->deviceId !== null) {
            $data['deviceId'] = $this->deviceId;
        }
        if ($this->tokenType !== null) {
            $data['tokenType'] = $this->tokenType;
        }

        return $data;
    }
}
