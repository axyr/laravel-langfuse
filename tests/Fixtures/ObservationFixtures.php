<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/**
 * Shared observation read-API fixtures, modelled on the ObservationV2 payloads
 * in docs/openapi/langfuse.yml (GET /api/public/v2/observations). The v1
 * get-by-id endpoint is gone in v4; a single observation is an `id` filter on
 * the same list.
 */
class ObservationFixtures
{
    /**
     * A GENERATION observation with the model, usage, prompt and trace_context
     * field groups.
     *
     * @return array<string, mixed>
     */
    public static function generation(): array
    {
        return [
            'id' => '0192f1b42c7e7a1b',
            'traceId' => '0192f1b42c7e7a1b8d3e9f0a1b2c3d4e',
            'projectId' => 'project-1',
            'type' => 'GENERATION',
            'name' => 'llm-call',
            'isRootObservation' => false,
            'startTime' => '2024-05-01T12:00:00.000Z',
            'endTime' => '2024-05-01T12:00:02.000Z',
            'completionStartTime' => '2024-05-01T12:00:00.500Z',
            'createdAt' => '2024-05-01T12:00:02.100Z',
            'updatedAt' => '2024-05-01T12:00:02.100Z',
            'model' => 'gpt-4',
            'internalModelId' => 'model-gpt4',
            'modelParameters' => ['temperature' => 0.7],
            'input' => '{"prompt":"Hello"}',
            'output' => '{"text":"Hi there"}',
            'metadata' => ['env' => 'prod'],
            'level' => 'DEFAULT',
            'statusMessage' => null,
            'parentObservationId' => 'aaaaaaaaaaaaaaaa',
            'promptId' => 'prompt-9',
            'promptName' => 'greeting',
            'promptVersion' => 3,
            'usageDetails' => ['input' => 10, 'output' => 5, 'total' => 15],
            'costDetails' => ['input' => 0.001, 'output' => 0.002, 'total' => 0.003],
            'totalCost' => 0.003,
            'usagePricingTierName' => 'standard',
            'environment' => 'production',
            'version' => 'v9',
            'bookmarked' => false,
            'public' => false,
            'userId' => 'user-1',
            'sessionId' => 'session-1',
            'latency' => 2.0,
            'timeToFirstToken' => 0.5,
            'traceName' => 'checkout',
            'tags' => ['prod', 'v4'],
            'release' => '1.2.3',
        ];
    }

    /**
     * A SPAN observation with only the core and basic field groups.
     *
     * @return array<string, mixed>
     */
    public static function v2Observation(): array
    {
        return [
            'id' => 'bbbbbbbbbbbbbbbb',
            'traceId' => '0192f1b42c7e7a1b8d3e9f0a1b2c3d4e',
            'projectId' => 'project-1',
            'type' => 'SPAN',
            'name' => 'retrieval',
            'isRootObservation' => false,
            'startTime' => '2024-05-02T09:00:00.000Z',
            'endTime' => '2024-05-02T09:00:01.000Z',
            'level' => 'WARNING',
            'statusMessage' => 'slow',
            'environment' => 'production',
            'parentObservationId' => 'aaaaaaaaaaaaaaaa',
            'model' => 'text-embedding-3',
            'internalModelId' => 'model-emb3',
            'usageDetails' => ['total' => 100],
            'costDetails' => ['total' => 0.01],
            'latency' => 1.0,
        ];
    }

    /**
     * The root observation of a trace.
     *
     * @return array<string, mixed>
     */
    public static function rootObservation(): array
    {
        return [
            'id' => 'aaaaaaaaaaaaaaaa',
            'traceId' => '0192f1b42c7e7a1b8d3e9f0a1b2c3d4e',
            'type' => 'SPAN',
            'name' => 'checkout',
            'isRootObservation' => true,
            'startTime' => '2024-05-02T09:00:00.000Z',
            'endTime' => '2024-05-02T09:00:05.000Z',
            'environment' => 'production',
            'traceName' => 'checkout',
        ];
    }

    /**
     * A cursor-paginated v2 list response (ObservationsV2Response).
     *
     * @return array<string, mixed>
     */
    public static function v2List(): array
    {
        return [
            'data' => [
                self::v2Observation(),
            ],
            'meta' => [
                'cursor' => 'eyJpZCI6Im9icy0yIn0=',
            ],
        ];
    }
}
