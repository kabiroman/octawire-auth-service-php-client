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
        public readonly ?array $claims = null,
        public readonly ?int $accessTokenTtl = null,
        public readonly ?int $refreshTokenTtl = null,
        public readonly ?string $deviceId = null,
        public readonly ?string $projectId = null,
        public readonly ?string $tokenType = null // 'access' | 'refresh'
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'user_id' => $this->userId,
        ];

        if ($this->claims !== null) {
            $data['claims'] = $this->claims;
        }
        if ($this->accessTokenTtl !== null) {
            $data['access_token_ttl'] = $this->accessTokenTtl;
        }
        if ($this->refreshTokenTtl !== null) {
            $data['refresh_token_ttl'] = $this->refreshTokenTtl;
        }
        if ($this->deviceId !== null) {
            $data['device_id'] = $this->deviceId;
        }
        if ($this->projectId !== null) {
            $data['project_id'] = $this->projectId;
        }
        if ($this->tokenType !== null) {
            $data['token_type'] = $this->tokenType;
        }

        return $data;
    }
}

