<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

readonly class DatasetRunWithItemsResponse
{
    /**
     * @param  array<DatasetRunItemResponse>  $datasetRunItems
     */
    public function __construct(
        public DatasetRunResponse $run,
        public array $datasetRunItems,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $data['datasetRunItems'] ?? [];

        return new self(
            run: DatasetRunResponse::fromArray($data),
            datasetRunItems: array_map(
                fn(array $item): DatasetRunItemResponse => DatasetRunItemResponse::fromArray($item),
                $items,
            ),
        );
    }
}
