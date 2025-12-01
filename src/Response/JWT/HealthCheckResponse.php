<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Response\JWT;

/**
 * Response для проверки здоровья сервиса
 * 
 * BREAKING CHANGE (v0.9.4): Поле `healthy` (bool) заменено на `status` (string)
 * Миграция: $response->healthy → $response->status === 'healthy' или $response->isHealthy()
 */
class HealthCheckResponse
{
    public const STATUS_HEALTHY = 'healthy';
    public const STATUS_DEGRADED = 'degraded';
    public const STATUS_UNHEALTHY = 'unhealthy';

    public function __construct(
        public readonly string $status,
        public readonly ?int $timestamp = null,
        public readonly ?string $version = null,
        public readonly ?int $uptime = null,
        public readonly ?array $details = null
    ) {
    }

    /**
     * Проверка, что сервис полностью здоров
     */
    public function isHealthy(): bool
    {
        return $this->status === self::STATUS_HEALTHY;
    }

    /**
     * Проверка, что сервис работает (healthy или degraded)
     */
    public function isOperational(): bool
    {
        return $this->status === self::STATUS_HEALTHY || $this->status === self::STATUS_DEGRADED;
    }

    public static function fromArray(array $data): self
    {
        // Поддержка как нового формата (status), так и legacy (healthy) для обратной совместимости при парсинге
        $status = $data['status'] ?? null;
        if ($status === null && isset($data['healthy'])) {
            // Legacy fallback: конвертируем bool healthy в string status
            $status = $data['healthy'] ? self::STATUS_HEALTHY : self::STATUS_UNHEALTHY;
        }
        $status = $status ?? self::STATUS_UNHEALTHY;

        return new self(
            status: $status,
            timestamp: isset($data['timestamp']) ? (int)$data['timestamp'] : null,
            version: $data['version'] ?? null,
            uptime: isset($data['uptime']) ? (int)$data['uptime'] : null,
            details: $data['details'] ?? null
        );
    }
}
