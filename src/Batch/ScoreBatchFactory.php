<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Batch;

use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Dto\IdGenerator;
use Axyr\Langfuse\Dto\IngestionBatch;
use Axyr\Langfuse\Dto\IngestionEvent;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Enums\EventType;
use Axyr\Langfuse\Version;

/**
 * Scores still travel as `score-create` events on the ingestion endpoint, which
 * v4 keeps supporting. The envelope timestamp is what identifies a score for
 * overwriting, alongside its id and name.
 */
class ScoreBatchFactory
{
    public function __construct(
        private readonly LangfuseConfig $config,
    ) {}

    public function event(ScoreBody $score, ?string $timestamp = null): IngestionEvent
    {
        return new IngestionEvent(
            id: IdGenerator::uuid(),
            type: EventType::ScoreCreate,
            timestamp: $timestamp ?? IdGenerator::timestamp(),
            body: $score,
        );
    }

    /**
     * @param array<int, IngestionEvent> $events
     */
    public function batch(array $events): IngestionBatch
    {
        return new IngestionBatch(
            batch: array_values($events),
            metadata: $this->metadata(count($events)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(int $batchSize): array
    {
        return [
            'batch_size' => $batchSize,
            'sdk_name' => Version::SDK_NAME,
            'sdk_version' => Version::SDK_VERSION,
            'public_key' => $this->config->publicKey,
        ];
    }
}
