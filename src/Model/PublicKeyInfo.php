<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Model;

/**
 * Информация о публичном ключе
 */
class PublicKeyInfo
{
    public function __construct(
        public readonly string $keyId,
        public readonly string $publicKeyPem,
        public readonly bool $isPrimary,
        public readonly int $expiresAt
    ) {
    }

    /**
     * Проверка, истек ли ключ
     */
    public function isExpired(): bool
    {
        return time() >= $this->expiresAt;
    }

    /**
     * Создание из массива данных
     */
    public static function fromArray(array $data): self
    {
        return new self(
            keyId: $data['key_id'] ?? '',
            publicKeyPem: $data['public_key_pem'] ?? '',
            isPrimary: $data['is_primary'] ?? false,
            expiresAt: $data['expires_at'] ?? 0
        );
    }

    /**
     * Преобразование в массив
     */
    public function toArray(): array
    {
        return [
            'key_id' => $this->keyId,
            'public_key_pem' => $this->publicKeyPem,
            'is_primary' => $this->isPrimary,
            'expires_at' => $this->expiresAt,
        ];
    }
}

