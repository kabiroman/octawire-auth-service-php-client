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
        public readonly string $name,
        public readonly array $scopes,
        public readonly int $createdAt,
        public readonly ?int $expiresAt = null,
        public readonly bool $revoked = false
    ) {
    }

    /**
     * Проверка, истек ли API ключ
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }
        return time() >= $this->expiresAt;
    }

    /**
     * Проверка, активен ли API ключ
     */
    public function isActive(): bool
    {
        return !$this->revoked && !$this->isExpired();
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
            keyId: $data['key_id'] ?? '',
            name: $data['name'] ?? '',
            scopes: $data['scopes'] ?? [],
            createdAt: $data['created_at'] ?? 0,
            expiresAt: $data['expires_at'] ?? null,
            revoked: $data['revoked'] ?? false
        );
    }

    /**
     * Преобразование в массив
     */
    public function toArray(): array
    {
        return [
            'key_id' => $this->keyId,
            'name' => $this->name,
            'scopes' => $this->scopes,
            'created_at' => $this->createdAt,
            'expires_at' => $this->expiresAt,
            'revoked' => $this->revoked,
        ];
    }
}

