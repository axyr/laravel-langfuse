<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

use Axyr\Langfuse\Contracts\SerializableInterface;

readonly class Usage implements SerializableInterface
{
    public function __construct(
        public ?int $input = null,
        public ?int $output = null,
        public ?int $total = null,
        public ?string $unit = null,
        public ?float $inputCost = null,
        public ?float $outputCost = null,
        public ?float $totalCost = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'input' => $this->input,
            'output' => $this->output,
            'total' => $this->total,
            'unit' => $this->unit,
            'inputCost' => $this->inputCost,
            'outputCost' => $this->outputCost,
            'totalCost' => $this->totalCost,
        ], fn(mixed $value): bool => $value !== null);
    }
}
