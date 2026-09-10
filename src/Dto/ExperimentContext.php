<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

/**
 * Experiment a trace belongs to. Langfuse creates the experiment implicitly from
 * the `langfuse.experiment.*` attributes carried by every span of the trace, so
 * both `id` and `name` must be unique per experiment.
 */
readonly class ExperimentContext
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $datasetId = null,
        public ?string $description = null,
        public ?array $metadata = null,
    ) {}

    /**
     * Derives the id from the name, which the docs already require to be unique
     * per experiment.
     *
     * @param array<string, mixed>|null $metadata
     */
    public static function named(
        string $name,
        ?string $datasetId = null,
        ?string $description = null,
        ?array $metadata = null,
    ): self {
        return new self(
            id: $name,
            name: $name,
            datasetId: $datasetId,
            description: $description,
            metadata: $metadata,
        );
    }
}
