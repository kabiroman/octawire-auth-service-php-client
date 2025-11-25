<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\JWT;

/**
 * Request для пакетной валидации токенов
 * НЕ принимает project_id - определяется автоматически из токенов
 */
class ValidateBatchRequest
{
    public function __construct(
        public readonly array $tokens, // Массив токенов (максимум 50)
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
            'check_blacklist' => $this->checkBlacklist,
        ];
    }
}

