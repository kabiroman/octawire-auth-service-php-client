<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\JWT;

/**
 * Request для пакетной валидации токенов
 * projectId обязателен (v0.9.4+)
 */
class ValidateBatchRequest
{
    public function __construct(
        public readonly array $tokens, // Массив токенов (максимум 50)
        public readonly string $projectId, // Обязательное поле (v0.9.4+)
        public readonly bool $checkBlacklist = true
    ) {
        if (count($tokens) > 50) {
            throw new \InvalidArgumentException('Maximum 50 tokens allowed per batch');
        }
        if (empty($tokens)) {
            throw new \InvalidArgumentException('At least one token is required');
        }
    }

    public function toArray(): array
    {
        return [
            'tokens' => $this->tokens,
            'projectId' => $this->projectId,
            'checkBlacklist' => $this->checkBlacklist,
        ];
    }
}
