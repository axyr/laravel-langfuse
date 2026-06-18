<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/**
 * Shared score read-API fixtures, modelled on the payloads in
 * docs/openapi/langfuse.yml (GET /api/public/v2/scores[/{scoreId}]).
 */
class ScoreFixtures
{
    /**
     * A single NUMERIC score as returned by GET /api/public/v2/scores/{scoreId}.
     *
     * @return array<string, mixed>
     */
    public static function numericScore(): array
    {
        return [
            'id' => 'score-abc',
            'traceId' => 'trace-123',
            'observationId' => 'obs-1',
            'name' => 'accuracy',
            'source' => 'API',
            'timestamp' => '2024-05-01T12:00:00.000Z',
            'createdAt' => '2024-05-01T12:00:01.000Z',
            'updatedAt' => '2024-05-01T12:00:01.000Z',
            'comment' => 'looks good',
            'metadata' => ['reviewer' => 'qa'],
            'environment' => 'production',
            'dataType' => 'NUMERIC',
            'value' => 0.95,
        ];
    }

    /**
     * A CATEGORICAL score including trace field-group data, as returned in a list.
     *
     * @return array<string, mixed>
     */
    public static function categoricalScore(): array
    {
        return [
            'id' => 'score-cat',
            'traceId' => 'trace-456',
            'datasetRunId' => 'run-789',
            'name' => 'sentiment',
            'source' => 'EVAL',
            'timestamp' => '2024-05-02T08:30:00.000Z',
            'createdAt' => '2024-05-02T08:30:01.000Z',
            'updatedAt' => '2024-05-02T08:30:01.000Z',
            'metadata' => [],
            'environment' => 'production',
            'dataType' => 'CATEGORICAL',
            'value' => 1,
            'stringValue' => 'positive',
            'trace' => [
                'userId' => 'user-1',
                'tags' => ['prod', 'v2'],
                'environment' => 'production',
                'sessionId' => 'sess-1',
            ],
        ];
    }

    /**
     * A paginated GetScoresResponse with two scores.
     *
     * @return array<string, mixed>
     */
    public static function scoreList(): array
    {
        return [
            'data' => [
                self::numericScore(),
                self::categoricalScore(),
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
