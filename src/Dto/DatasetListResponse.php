<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

readonly class DatasetListResponse
{
    /**
     * @param  array<DatasetResponse>  $data
     */
    public function __construct(
        public array $data,
        public PromptListMeta $meta,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $data['data'] ?? [];

        /** @var array<string, mixed> $meta */
        $meta = $data['meta'] ?? [];

        return new self(
            data: array_map(
                fn(array $item): DatasetResponse => DatasetResponse::fromArray($item),
                $items,
            ),
            meta: PromptListMeta::fromArray($meta),
        );
    }
}
