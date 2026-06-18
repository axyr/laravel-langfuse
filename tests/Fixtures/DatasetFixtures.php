<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/**
 * Shared dataset read-API fixtures, modelled on the payloads in
 * docs/openapi/langfuse.yml (GET/POST /api/public/v2/datasets[/{name}]).
 */
class DatasetFixtures
{
    /**
     * A single dataset (Dataset schema).
     *
     * @return array<string, mixed>
     */
    public static function dataset(): array
    {
        return [
            'id' => 'ds-1',
            'name' => 'qa-eval',
            'description' => 'QA evaluation set',
            'metadata' => ['owner' => 'team-a'],
            'inputSchema' => null,
            'expectedOutputSchema' => null,
            'projectId' => 'proj-1',
            'createdAt' => '2024-05-01T10:00:00.000Z',
            'updatedAt' => '2024-05-01T10:00:00.000Z',
        ];
    }

    /**
     * A paginated dataset list (PaginatedDatasets schema).
     *
     * @return array<string, mixed>
     */
    public static function datasetList(): array
    {
        return [
            'data' => [
                self::dataset(),
                [
                    'id' => 'ds-2',
                    'name' => 'regression',
                    'projectId' => 'proj-1',
                    'metadata' => [],
                    'createdAt' => '2024-05-03T10:00:00.000Z',
                    'updatedAt' => '2024-05-03T10:00:00.000Z',
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
