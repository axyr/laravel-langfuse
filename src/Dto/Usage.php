<?php

declare(strict_types=1);

namespace Langfuse\Dto;

use Langfuse\Contracts\SerializableInterface;

readonly class Usage implements SerializableInterface
{
    public function __construct(
        public ?int $input = null,
        public ?int $output = null,
        public ?int $total = null,
        public ?string $unit = null,
        public ?int $inputCost = null,
        public ?int $outputCost = null,
        public ?int $totalCost = null,
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
