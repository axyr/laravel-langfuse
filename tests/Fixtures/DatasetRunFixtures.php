<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/**
 * Shared dataset-run / run-item read-API fixtures, modelled on the payloads in
 * docs/openapi/langfuse.yml (datasets/{name}/runs[/{runName}] and
 * /api/public/dataset-run-items).
 */
class DatasetRunFixtures
{
    /**
     * A single dataset run (DatasetRun schema).
     *
     * @return array<string, mixed>
     */
    public static function datasetRun(): array
    {
        return [
            'id' => 'dr-1',
            'name' => 'run-2024-05',
            'description' => 'first eval run',
            'metadata' => ['model' => 'gpt-4'],
            'datasetId' => 'ds-1',
            'datasetName' => 'qa-eval',
            'createdAt' => '2024-05-05T10:00:00.000Z',
            'updatedAt' => '2024-05-05T10:00:00.000Z',
        ];
    }

    /**
     * A single dataset run item (DatasetRunItem schema).
     *
     * @return array<string, mixed>
     */
    public static function datasetRunItem(): array
    {
        return [
            'id' => 'dri-1',
            'datasetRunId' => 'dr-1',
            'datasetRunName' => 'run-2024-05',
            'datasetItemId' => 'di-1',
            'traceId' => 'trace-abc',
            'observationId' => null,
            'createdAt' => '2024-05-05T10:01:00.000Z',
            'updatedAt' => '2024-05-05T10:01:00.000Z',
        ];
    }

    /**
     * A dataset run together with its run items (DatasetRunWithItems schema).
     *
     * @return array<string, mixed>
     */
    public static function datasetRunWithItems(): array
    {
        return self::datasetRun() + [
            'datasetRunItems' => [
                self::datasetRunItem(),
            ],
        ];
    }

    /**
     * A paginated dataset-run list (PaginatedDatasetRuns schema).
     *
     * @return array<string, mixed>
     */
    public static function datasetRunList(): array
    {
        return [
            'data' => [self::datasetRun()],
            'meta' => ['totalItems' => 1, 'totalPages' => 1, 'page' => 1, 'limit' => 50],
        ];
    }

    /**
     * A paginated dataset-run-item list (PaginatedDatasetRunItems schema).
     *
     * @return array<string, mixed>
     */
    public static function datasetRunItemList(): array
    {
        return [
            'data' => [self::datasetRunItem()],
            'meta' => ['totalItems' => 1, 'totalPages' => 1, 'page' => 1, 'limit' => 50],
        ];
    }
}
