<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Response\APIKey;

/**
 * Response для отзыва API ключа
 */
class RevokeAPIKeyResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $error = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            success: (bool)($data['success'] ?? false),
            error: $data['error'] ?? null
        );
    }
}

