<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Response\APIKey;

use Kabiroman\Octawire\AuthService\Client\Model\APIKeyInfo;

/**
 * Response для списка API ключей
 */
class ListAPIKeysResponse
{
    /**
     * @var APIKeyInfo[]
     */
    public readonly array $keys;

    public function __construct(
        array $keys,
        public readonly int $total,
        public readonly int $page,
        public readonly int $pageSize
    ) {
        $this->keys = array_map(
            fn(array $key) => APIKeyInfo::fromArray($key),
            $keys
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            keys: $data['keys'] ?? [],
            total: (int)($data['total'] ?? 0),
            page: (int)($data['page'] ?? 1),
            pageSize: (int)($data['page_size'] ?? $data['pageSize'] ?? 20)
        );
    }
}

