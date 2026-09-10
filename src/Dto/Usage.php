<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

use Axyr\Langfuse\Contracts\SerializableInterface;

/**
 * Token and cost counts for a generation. v4 has no usage `unit`, and accepts
 * arbitrary usage and cost keys next to the conventional input/output/total
 * ones (cached tokens, reasoning tokens, ...): pass those through `$details`
 * and `$costDetails`.
 */
readonly class Usage implements SerializableInterface
{
    /**
     * @param array<string, int|float>|null $details
     * @param array<string, int|float>|null $costDetails
     */
    public function __construct(
        public ?int $input = null,
        public ?int $output = null,
        public ?int $total = null,
        public ?float $inputCost = null,
        public ?float $outputCost = null,
        public ?float $totalCost = null,
        public ?array $details = null,
        public ?array $costDetails = null,
    ) {}

    /**
     * Usage keys as Langfuse stores them, conventional keys first.
     *
     * @return array<string, int|float>
     */
    public function usageDetails(): array
    {
        return array_merge(
            array_filter([
                'input' => $this->input,
                'output' => $this->output,
                'total' => $this->total,
            ], fn(mixed $value): bool => $value !== null),
            $this->details ?? [],
        );
    }

    /**
     * @return array<string, int|float>
     */
    public function costDetails(): array
    {
        return array_merge(
            array_filter([
                'input' => $this->inputCost,
                'output' => $this->outputCost,
                'total' => $this->totalCost,
            ], fn(mixed $value): bool => $value !== null),
            $this->costDetails ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'input' => $this->input,
            'output' => $this->output,
            'total' => $this->total,
            'inputCost' => $this->inputCost,
            'outputCost' => $this->outputCost,
            'totalCost' => $this->totalCost,
            'details' => $this->details,
            'costDetails' => $this->costDetails,
        ], fn(mixed $value): bool => $value !== null);
    }
}
