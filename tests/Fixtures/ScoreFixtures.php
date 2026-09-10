<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/**
 * Shared score read-API fixtures, modelled on the v3 payloads in
 * docs/openapi/langfuse.yml (GET /api/public/v3/scores). `value` is polymorphic
 * and there is no `stringValue`; what a score is attached to lives in `subject`.
 */
class ScoreFixtures
{
    /**
     * A NUMERIC score on an observation, with the details and subject field groups.
     *
     * @return array<string, mixed>
     */
    public static function numericScore(): array
    {
        return [
            'id' => 'score-abc',
            'projectId' => 'project-1',
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
            'subject' => [
                'kind' => 'observation',
                'id' => '0192f1b42c7e7a1b',
                'traceId' => '0192f1b42c7e7a1b8d3e9f0a1b2c3d4e',
            ],
        ];
    }

    /**
     * A CATEGORICAL score on an experiment: the string lands in `value`.
     *
     * @return array<string, mixed>
     */
    public static function categoricalScore(): array
    {
        return [
            'id' => 'score-cat',
            'projectId' => 'project-1',
            'name' => 'sentiment',
            'source' => 'EVAL',
            'timestamp' => '2024-05-02T08:30:00.000Z',
            'createdAt' => '2024-05-02T08:30:01.000Z',
            'updatedAt' => '2024-05-02T08:30:01.000Z',
            'metadata' => [],
            'environment' => 'production',
            'dataType' => 'CATEGORICAL',
            'value' => 'positive',
            'subject' => [
                'kind' => 'experiment',
                'id' => 'experiment-789',
            ],
        ];
    }

    /**
     * A BOOLEAN score on a trace: `value` is a real boolean in v3.
     *
     * @return array<string, mixed>
     */
    public static function booleanScore(): array
    {
        return [
            'id' => 'score-bool',
            'projectId' => 'project-1',
            'name' => 'is_correct',
            'source' => 'ANNOTATION',
            'timestamp' => '2024-05-03T09:00:00.000Z',
            'createdAt' => '2024-05-03T09:00:01.000Z',
            'updatedAt' => '2024-05-03T09:00:01.000Z',
            'environment' => 'production',
            'dataType' => 'BOOLEAN',
            'value' => true,
            'authorUserId' => 'user-9',
            'queueId' => 'queue-1',
            'subject' => [
                'kind' => 'trace',
                'id' => '0192f1b42c7e7a1b8d3e9f0a1b2c3d4e',
            ],
        ];
    }

    /**
     * A cursor-paginated GetScoresResponse.
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
                'limit' => 50,
                'cursor' => 'eyJpZCI6InNjb3JlLWNhdCJ9',
            ],
        ];
    }

    /**
     * The last page: no cursor.
     *
     * @return array<string, mixed>
     */
    public static function lastScorePage(): array
    {
        return [
            'data' => [self::booleanScore()],
            'meta' => ['limit' => 50],
        ];
    }
}
