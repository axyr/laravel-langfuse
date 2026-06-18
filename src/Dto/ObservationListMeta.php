<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

readonly class ObservationListMeta
{
    public function __construct(
        public ?string $cursor = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            cursor: is_string($data['cursor'] ?? null) ? $data['cursor'] : null,
        );
    }
}
