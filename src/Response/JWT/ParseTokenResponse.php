<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Response\JWT;

use Kabiroman\Octawire\AuthService\Client\Model\TokenClaims;

/**
 * Response для парсинга токена
 */
class ParseTokenResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ?TokenClaims $claims = null,
        public readonly ?string $error = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        $claims = null;
        if (isset($data['claims']) && is_array($data['claims'])) {
            $claims = TokenClaims::fromArray($data['claims']);
        }

        return new self(
            success: (bool)($data['success'] ?? false),
            claims: $claims,
            error: $data['error'] ?? null
        );
    }
}

