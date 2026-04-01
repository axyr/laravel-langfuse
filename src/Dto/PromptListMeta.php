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
        /** @var int $totalItems */
        $totalItems = $data['totalItems'] ?? 0;

        /** @var int $totalPages */
        $totalPages = $data['totalPages'] ?? 0;

        /** @var int $page */
        $page = $data['page'] ?? 1;

        /** @var int $limit */
        $limit = $data['limit'] ?? 10;

        return new self(
            totalItems: $totalItems,
            totalPages: $totalPages,
            page: $page,
            limit: $limit,
        );
    }
}
