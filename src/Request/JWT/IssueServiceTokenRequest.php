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
        public readonly string $projectId, // Обязательное поле (v0.9.3+)
        public readonly ?string $targetService = null,
        public readonly ?string $userId = null,
        public readonly ?array $claims = null,
        public readonly ?int $ttl = null
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'source_service' => $this->sourceService,
            'project_id' => $this->projectId, // Всегда включаем project_id
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

        return $data;
    }
}

