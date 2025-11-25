<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\JWT;

/**
 * Request для выдачи межсервисного JWT токена
 */
class IssueServiceTokenRequest
{
    public function __construct(
        public readonly string $sourceService,
        public readonly ?string $targetService = null,
        public readonly ?string $userId = null,
        public readonly ?array $claims = null,
        public readonly ?int $ttl = null,
        public readonly ?string $projectId = null
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'source_service' => $this->sourceService,
        ];

        if ($this->targetService !== null) {
            $data['target_service'] = $this->targetService;
        }
        if ($this->userId !== null) {
            $data['user_id'] = $this->userId;
        }
        if ($this->claims !== null) {
            $data['claims'] = $this->claims;
        }
        if ($this->ttl !== null) {
            $data['ttl'] = $this->ttl;
        }
        if ($this->projectId !== null) {
            $data['project_id'] = $this->projectId;
        }

        return $data;
    }
}

