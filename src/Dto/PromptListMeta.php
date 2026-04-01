<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

readonly class PromptListMeta
{
    public function __construct(
        public int $totalItems,
        public int $totalPages,
        public int $page,
        public int $limit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            totalItems: (int) ($data['totalItems'] ?? 0),
            totalPages: (int) ($data['totalPages'] ?? 0),
            page: (int) ($data['page'] ?? 1),
            limit: (int) ($data['limit'] ?? 10),
        );
    }
}
