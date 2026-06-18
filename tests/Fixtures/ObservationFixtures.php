<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/**
 * Shared observation read-API fixtures, modelled on the payloads in
 * docs/openapi/langfuse.yml (GET /api/public/observations/{id} and
 * GET /api/public/v2/observations).
 */
class ObservationFixtures
{
    /**
     * A single GENERATION observation as returned by
     * GET /api/public/observations/{observationId} (ObservationsView).
     *
     * @return array<string, mixed>
     */
    public static function generation(): array
    {
        return [
            'id' => 'obs-1',
            'traceId' => 'trace-123',
            'type' => 'GENERATION',
            'name' => 'llm-call',
            'startTime' => '2024-05-01T12:00:00.000Z',
            'endTime' => '2024-05-01T12:00:02.000Z',
            'completionStartTime' => '2024-05-01T12:00:00.500Z',
            'model' => 'gpt-4',
            'modelParameters' => ['temperature' => 0.7],
            'input' => ['prompt' => 'Hello'],
            'output' => ['text' => 'Hi there'],
            'metadata' => ['env' => 'prod'],
            'level' => 'DEFAULT',
            'statusMessage' => null,
            'parentObservationId' => null,
            'promptId' => 'prompt-9',
            'usageDetails' => ['input' => 10, 'output' => 5, 'total' => 15],
            'costDetails' => ['input' => 0.001, 'output' => 0.002, 'total' => 0.003],
            'environment' => 'production',
            'promptName' => 'greeting',
            'promptVersion' => 3,
            'modelId' => 'model-gpt4',
            'latency' => 2.0,
            'timeToFirstToken' => 0.5,
        ];
    }

    /**
     * A single SPAN observation as returned by the v2 list endpoint
     * (ObservationV2 - uses providedModelName/internalModelId).
     *
     * @return array<string, mixed>
     */
    public static function v2Observation(): array
    {
        return [
            'id' => 'obs-2',
            'traceId' => 'trace-456',
            'type' => 'SPAN',
            'name' => 'retrieval',
            'startTime' => '2024-05-02T09:00:00.000Z',
            'endTime' => '2024-05-02T09:00:01.000Z',
            'level' => 'WARNING',
            'statusMessage' => 'slow',
            'environment' => 'production',
            'parentObservationId' => 'obs-1',
            'providedModelName' => 'text-embedding-3',
            'internalModelId' => 'model-emb3',
            'usageDetails' => ['total' => 100],
            'costDetails' => ['total' => 0.01],
            'latency' => 1.0,
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
