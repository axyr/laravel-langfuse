<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

readonly class DatasetRunItemListResponse
{
    /**
     * @param  array<DatasetRunItemResponse>  $data
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
                fn(array $item): DatasetRunItemResponse => DatasetRunItemResponse::fromArray($item),
                $items,
            ),
            meta: PromptListMeta::fromArray($meta),
        );
    }
}
