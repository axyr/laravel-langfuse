<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/**
 * Shared dataset-item read-API fixtures, modelled on the payloads in
 * docs/openapi/langfuse.yml (GET/POST /api/public/dataset-items[/{id}]).
 */
class DatasetItemFixtures
{
    /**
     * A single dataset item (DatasetItem schema).
     *
     * @return array<string, mixed>
     */
    public static function datasetItem(): array
    {
        return [
            'id' => 'di-1',
            'status' => 'ACTIVE',
            'input' => ['question' => 'What is 2+2?'],
            'expectedOutput' => ['answer' => '4'],
            'metadata' => ['difficulty' => 'easy'],
            'sourceTraceId' => null,
            'sourceObservationId' => null,
            'datasetId' => 'ds-1',
            'datasetName' => 'qa-eval',
            'createdAt' => '2024-05-01T10:00:00.000Z',
            'updatedAt' => '2024-05-01T10:00:00.000Z',
        ];
    }

    /**
     * A paginated dataset-item list (PaginatedDatasetItems schema).
     *
     * @return array<string, mixed>
     */
    public static function datasetItemList(): array
    {
        return [
            'data' => [
                self::datasetItem(),
                [
                    'id' => 'di-2',
                    'status' => 'ARCHIVED',
                    'input' => ['question' => 'Capital of France?'],
                    'expectedOutput' => ['answer' => 'Paris'],
                    'metadata' => [],
                    'datasetId' => 'ds-1',
                    'datasetName' => 'qa-eval',
                    'createdAt' => '2024-05-02T10:00:00.000Z',
                    'updatedAt' => '2024-05-02T10:00:00.000Z',
                ],
            ],
            'meta' => [
                'totalItems' => 2,
                'totalPages' => 1,
                'page' => 1,
                'limit' => 50,
            ],
        ];
    }
}
