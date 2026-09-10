<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

readonly class ExperimentListResponse
{
    /**
     * @param  array<ExperimentResponse>  $data
     */
    public function __construct(
        public array $data,
        public CursorMeta $meta,
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
                fn(array $item): ExperimentResponse => ExperimentResponse::fromArray($item),
                $items,
            ),
            meta: CursorMeta::fromArray($meta),
        );
    }
}
