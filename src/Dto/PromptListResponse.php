<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

readonly class PromptListResponse
{
    /**
     * @param  array<PromptListItem>  $data
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

        return new self(
            data: array_map(
                fn(array $item): PromptListItem => PromptListItem::fromArray($item),
                $items,
            ),
            meta: PromptListMeta::fromArray($data['meta'] ?? []),
        );
    }
}
