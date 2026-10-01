<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/**
 * Shared experiment read-API fixtures, modelled on the payloads in
 * docs/openapi/langfuse.yml (GET /api/public/experiments and
 * GET /api/public/experiment-items). Experiments replace dataset runs in v4.
 */
class ExperimentFixtures
{
    /**
     * @return array<string, mixed>
     */
    public static function experiment(): array
    {
        return [
            'id' => 'experiment-1',
            'name' => 'nightly-eval',
            'description' => 'nightly regression run',
            'startTime' => '2024-05-01T00:00:00.000Z',
            'endTime' => '2024-05-01T00:10:00.000Z',
            'itemCount' => 2,
            'datasetId' => 'dataset-1',
            'metadata' => ['model' => 'gpt-4'],
            'scores' => [ScoreFixtures::numericScore()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function experimentList(): array
    {
        return [
            'data' => [self::experiment()],
            'meta' => ['cursor' => 'eyJpZCI6ImV4cGVyaW1lbnQtMSJ9'],
        ];
    }

    /**
     * `id` is the root observation id of the item's trace; `experimentItemId` is
     * the dataset item id.
     *
     * @return array<string, mixed>
     */
    public static function experimentItem(): array
    {
        return [
            'id' => 'aaaaaaaaaaaaaaaa',
            'traceId' => '0192f1b42c7e7a1b8d3e9f0a1b2c3d4e',
            'startTime' => '2024-05-01T00:00:00.000Z',
            'endTime' => '2024-05-01T00:00:05.000Z',
            'level' => 'DEFAULT',
            'environment' => 'experiment',
            'experimentId' => 'experiment-1',
            'experimentName' => 'nightly-eval',
            'experimentItemId' => 'item-1',
            'experimentDatasetId' => 'dataset-1',
            'experimentItemVersion' => '2024-04-01T00:00:00.000Z',
            'input' => 'What is Langfuse?',
            'output' => 'An LLM observability platform.',
            'expectedOutput' => 'An LLM observability platform.',
            'metadata' => ['run' => 1],
            'experimentItemMetadata' => ['difficulty' => 'easy'],
            'experimentMetadata' => ['model' => 'gpt-4'],
            'experimentDescription' => 'nightly regression run',
            'scores' => [ScoreFixtures::numericScore()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function experimentItemList(): array
    {
        return [
            'data' => [self::experimentItem()],
            'meta' => ['cursor' => null],
        ];
    }
}
