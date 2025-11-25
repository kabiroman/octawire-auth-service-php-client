<?php

declare(strict_types=1);

namespace Kabiroman\Octawire\AuthService\Client\Request\APIKey;

/**
 * Request для списка API ключей
 */
class ListAPIKeysRequest
{
    public function __construct(
        public readonly string $projectId,
        public readonly ?string $userId = null,
        public readonly int $page = 1,
        public readonly int $pageSize = 20
    ) {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be >= 1');
        }
        if ($pageSize < 1 || $pageSize > 100) {
            throw new \InvalidArgumentException('Page size must be between 1 and 100');
        }
    }

    public function toArray(): array
    {
        $data = [
            'project_id' => $this->projectId,
            'page' => $this->page,
            'page_size' => $this->pageSize,
        ];

        if ($this->userId !== null) {
            $data['user_id'] = $this->userId;
        }

        return $data;
    }
}

