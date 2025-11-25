<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\APIKey;

/**
 * Request для создания нового API ключа
 */
class CreateAPIKeyRequest
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $name,
        public readonly ?string $userId = null,
        public readonly ?array $scopes = null,
        public readonly ?int $ttl = null, // 0 = без ограничения
        public readonly ?array $allowedIps = null // IPv4 адреса
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'project_id' => $this->projectId,
            'name' => $this->name,
        ];

        if ($this->userId !== null) {
            $data['user_id'] = $this->userId;
        }
        if ($this->scopes !== null) {
            $data['scopes'] = $this->scopes;
        }
        if ($this->ttl !== null) {
            $data['ttl'] = $this->ttl;
        }
        if ($this->allowedIps !== null) {
            $data['allowed_ips'] = $this->allowedIps;
        }

        return $data;
    }
}

