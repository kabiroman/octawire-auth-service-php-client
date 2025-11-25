<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Response\JWT;

/**
 * Response для проверки здоровья сервиса
 */
class HealthCheckResponse
{
    public function __construct(
        public readonly bool $healthy,
        public readonly ?string $version = null,
        public readonly ?int $uptime = null,
        public readonly ?array $details = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            healthy: (bool)($data['healthy'] ?? false),
            version: $data['version'] ?? null,
            uptime: isset($data['uptime']) ? (int)$data['uptime'] : null,
            details: $data['details'] ?? null
        );
    }
}

