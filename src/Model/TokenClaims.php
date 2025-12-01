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
        public readonly string $jwtId = '',
        public readonly string $keyId = '',
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
        $userId = $data['userId'] ?? $data['user_id'] ?? $data['sub'] ?? '';
        $tokenType = $data['tokenType'] ?? $data['token_type'] ?? '';
        $issuedAt = (int)($data['issuedAt'] ?? $data['issued_at'] ?? $data['iat'] ?? 0);
        $expiresAt = (int)($data['expiresAt'] ?? $data['expires_at'] ?? $data['exp'] ?? 0);
        $issuer = $data['issuer'] ?? $data['iss'] ?? '';
        $audience = $data['audience'] ?? $data['aud'] ?? '';
        $jwtId = $data['jwtId'] ?? $data['jwt_id'] ?? $data['jti'] ?? '';
        $keyId = $data['keyId'] ?? $data['key_id'] ?? '';
        
        // Извлекаем кастомные claims
        // Если есть customClaims в данных - используем их, иначе извлекаем все нестандартные ключи
        $customClaims = [];
        if (isset($data['customClaims']) && is_array($data['customClaims'])) {
            $customClaims = $data['customClaims'];
        } elseif (isset($data['custom_claims']) && is_array($data['custom_claims'])) {
            $customClaims = $data['custom_claims'];
        } else {
            // Извлекаем кастомные claims (все кроме стандартных)
            $standardKeys = [
                'userId', 'user_id', 'sub',
                'tokenType', 'token_type',
                'issuedAt', 'issued_at', 'iat',
                'expiresAt', 'expires_at', 'exp',
                'issuer', 'iss',
                'audience', 'aud',
                'jwtId', 'jwt_id', 'jti',
                'keyId', 'key_id',
                'customClaims', 'custom_claims'
            ];
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
            jwtId: $jwtId,
            keyId: $keyId,
            customClaims: $customClaims
        );
    }

    /**
     * Преобразование в массив
     */
    public function toArray(): array
    {
        return [
            'userId' => $this->userId,
            'tokenType' => $this->tokenType,
            'issuedAt' => $this->issuedAt,
            'expiresAt' => $this->expiresAt,
            'issuer' => $this->issuer,
            'audience' => $this->audience,
            'jwtId' => $this->jwtId,
            'keyId' => $this->keyId,
            'customClaims' => $this->customClaims,
        ];
    }
}
