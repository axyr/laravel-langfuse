<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

/**
 * The dataset item a trace runs. These attributes go on the root span only.
 */
readonly class ExperimentItemContext
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public string $itemId,
        public mixed $expectedOutput = null,
        public ?string $version = null,
        public ?array $metadata = null,
    ) {}
}
