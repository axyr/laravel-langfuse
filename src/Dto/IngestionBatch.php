<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

use Axyr\Langfuse\Contracts\SerializableInterface;

readonly class IngestionBatch implements SerializableInterface
{
    /**
     * @param  array<IngestionEvent>  $batch
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public array $batch,
        public array $metadata = [],
        public ?string $environment = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'batch' => array_map(
                fn(IngestionEvent $event): array => $this->serializeEvent($event),
                $this->batch,
            ),
            'metadata' => (object) $this->metadata,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEvent(IngestionEvent $event): array
    {
        $serialized = $event->toArray();

        if ($this->environment !== null && is_array($serialized['body'] ?? null) && ! isset($serialized['body']['environment'])) {
            $serialized['body']['environment'] = $this->environment;
        }

        return $serialized;
    }
}
