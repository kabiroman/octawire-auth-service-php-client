<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Response\APIKey;

/**
 * Response для валидации API ключа
 */
class ValidateAPIKeyResponse
{
    public function __construct(
        public readonly bool $valid,
        public readonly ?string $error = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $projectId = null,
        public readonly ?string $userId = null,
        public readonly array $scopes = [],
        public readonly array $metadata = []
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            valid: (bool)($data['valid'] ?? false),
            error: $data['error'] ?? null,
            errorCode: $data['error_code'] ?? $data['errorCode'] ?? null,
            projectId: $data['project_id'] ?? $data['projectId'] ?? null,
            userId: $data['user_id'] ?? $data['userId'] ?? null,
            scopes: $data['scopes'] ?? [],
            metadata: $data['metadata'] ?? []
        );
    }
}

