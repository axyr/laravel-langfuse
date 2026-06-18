<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

readonly class MetricsResponse
{
    /**
     * Each row is an associative array of the requested dimensions and metric
     * values; the exact keys depend on the query. Histogram measures return
     * an array of [lower, upper, height] tuples.
     *
     * @param  array<int, array<string, mixed>>  $data
     */
    public function __construct(
        public array $data,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $data['data'] ?? [];

        return new self(data: $items);
    }
}
