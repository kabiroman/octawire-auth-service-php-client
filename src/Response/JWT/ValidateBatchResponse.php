<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Response\JWT;

/**
 * Response для пакетной валидации токенов
 */
class ValidateBatchResponse
{
    /**
     * @var ValidateTokenResponse[]
     */
    public readonly array $results;

    public function __construct(array $results)
    {
        $this->results = array_map(
            fn(array $result) => ValidateTokenResponse::fromArray($result),
            $results
        );
    }

    public static function fromArray(array $data): self
    {
        return new self($data['results'] ?? []);
    }
}

