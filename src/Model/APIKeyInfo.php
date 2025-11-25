<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Model;

/**
 * Информация об API ключе
 */
class APIKeyInfo
{
    public function __construct(
        public readonly string $keyId,
        public readonly string $projectId,
        public readonly ?string $name = null,
        public readonly ?string $userId = null,
        public readonly array $scopes = [],
        public readonly int $createdAt,
        public readonly int $expiresAt, // 0 = без ограничения
        public readonly bool $active = true,
        public readonly ?int $lastUsedAt = null
    ) {
    }

    /**
     * Проверка, истек ли API ключ
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === 0) {
            return false; // 0 = без ограничения
        }
        return time() >= $this->expiresAt;
    }

    /**
     * Проверка, активен ли API ключ
     */
    public function isActive(): bool
    {
        return $this->active && !$this->isExpired();
    }

    /**
     * Проверка наличия scope
     */
    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    /**
     * Создание из массива данных
     */
    public static function fromArray(array $data): self
    {
        return new self(
            keyId: $data['key_id'] ?? $data['keyId'] ?? '',
            projectId: $data['project_id'] ?? $data['projectId'] ?? '',
            name: $data['name'] ?? null,
            userId: $data['user_id'] ?? $data['userId'] ?? null,
            scopes: $data['scopes'] ?? [],
            createdAt: (int)($data['created_at'] ?? $data['createdAt'] ?? 0),
            expiresAt: (int)($data['expires_at'] ?? $data['expiresAt'] ?? 0),
            active: (bool)($data['active'] ?? true),
            lastUsedAt: isset($data['last_used_at']) || isset($data['lastUsedAt']) 
                ? (int)($data['last_used_at'] ?? $data['lastUsedAt']) 
                : null
        );
    }

    /**
     * Преобразование в массив
     */
    public function toArray(): array
    {
        return [
            'key_id' => $this->keyId,
            'project_id' => $this->projectId,
            'name' => $this->name,
            'user_id' => $this->userId,
            'scopes' => $this->scopes,
            'created_at' => $this->createdAt,
            'expires_at' => $this->expiresAt,
            'active' => $this->active,
            'last_used_at' => $this->lastUsedAt,
        ];
    }
}

