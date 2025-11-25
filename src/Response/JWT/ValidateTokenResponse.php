<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Response\JWT;

use Kabiroman\Octawire\AuthService\Client\Model\TokenClaims;

/**
 * Response для валидации токена
 */
class ValidateTokenResponse
{
    public function __construct(
        public readonly bool $valid,
        public readonly ?TokenClaims $claims = null,
        public readonly ?string $error = null,
        public readonly ?string $errorCode = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        $claims = null;
        if (isset($data['claims']) && is_array($data['claims'])) {
            $claims = TokenClaims::fromArray($data['claims']);
        }

        return new self(
            valid: (bool)($data['valid'] ?? false),
            claims: $claims,
            error: $data['error'] ?? null,
            errorCode: $data['error_code'] ?? $data['errorCode'] ?? null
        );
    }
}

