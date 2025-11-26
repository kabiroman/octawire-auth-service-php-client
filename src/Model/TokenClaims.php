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
        // Поддержка как camelCase (из protobuf), так и snake_case (из JSON)
        $userId = $data['user_id'] ?? $data['userId'] ?? $data['sub'] ?? '';
        $tokenType = $data['token_type'] ?? $data['tokenType'] ?? '';
        $issuedAt = (int)($data['issued_at'] ?? $data['issuedAt'] ?? $data['iat'] ?? 0);
        $expiresAt = (int)($data['expires_at'] ?? $data['expiresAt'] ?? $data['exp'] ?? 0);
        $issuer = $data['issuer'] ?? $data['iss'] ?? '';
        $audience = $data['audience'] ?? $data['aud'] ?? '';
        
        // Извлекаем кастомные claims
        // Если есть customClaims в данных - используем их, иначе извлекаем все нестандартные ключи
        $customClaims = [];
        if (isset($data['customClaims']) && is_array($data['customClaims'])) {
            $customClaims = $data['customClaims'];
        } elseif (isset($data['custom_claims']) && is_array($data['custom_claims'])) {
            $customClaims = $data['custom_claims'];
        } else {
            // Извлекаем кастомные claims (все кроме стандартных)
            $standardKeys = ['user_id', 'userId', 'sub', 'token_type', 'tokenType', 
                            'issued_at', 'issuedAt', 'iat', 'expires_at', 'expiresAt', 'exp',
                            'issuer', 'iss', 'audience', 'aud', 'custom_claims', 'customClaims', 'jwt_id', 'jti', 'jwtId'];
            foreach ($data as $key => $value) {
                if (!in_array($key, $standardKeys, true)) {
                    $customClaims[$key] = $value;
                }
            }
        }
        
        return new self(
            userId: $userId,
            tokenType: $tokenType,
            issuedAt: $issuedAt,
            expiresAt: $expiresAt,
            issuer: $issuer,
            audience: $audience,
            customClaims: $customClaims
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

