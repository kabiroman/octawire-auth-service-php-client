<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Model;

/**
 * Claims токена
 */
class TokenClaims
{
    public function __construct(
        public readonly string $userId,
        public readonly string $tokenType,
        public readonly int $issuedAt,
        public readonly int $expiresAt,
        public readonly string $issuer,
        public readonly string $audience,
        public readonly array $customClaims = []
    ) {
    }

    /**
     * Проверка, истек ли токен
     */
    public function isExpired(): bool
    {
        return time() >= $this->expiresAt;
    }

    /**
     * Получение конкретного claim
     */
    public function getClaim(string $key, mixed $default = null): mixed
    {
        return $this->customClaims[$key] ?? $default;
    }

    /**
     * Создание из массива данных
     */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['user_id'] ?? '',
            tokenType: $data['token_type'] ?? '',
            issuedAt: $data['issued_at'] ?? 0,
            expiresAt: $data['expires_at'] ?? 0,
            issuer: $data['issuer'] ?? '',
            audience: $data['audience'] ?? '',
            customClaims: $data['custom_claims'] ?? []
        );
    }

    /**
     * Преобразование в массив
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'token_type' => $this->tokenType,
            'issued_at' => $this->issuedAt,
            'expires_at' => $this->expiresAt,
            'issuer' => $this->issuer,
            'audience' => $this->audience,
            'custom_claims' => $this->customClaims,
        ];
    }
}

