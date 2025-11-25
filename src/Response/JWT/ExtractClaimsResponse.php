<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Response\JWT;

/**
 * Response для извлечения claims
 */
class ExtractClaimsResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly array $claims = [],
        public readonly ?string $error = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            success: (bool)($data['success'] ?? false),
            claims: $data['claims'] ?? [],
            error: $data['error'] ?? null
        );
    }
}

