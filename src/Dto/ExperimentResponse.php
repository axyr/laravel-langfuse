<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

/**
 * Mirrors the Experiment schema. Experiments replace dataset runs in v4.
 */
readonly class ExperimentResponse
{
    /**
     * @param  array<ScoreResponse>|null  $scores
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description = null,
        public ?string $startTime = null,
        public ?string $endTime = null,
        public ?int $itemCount = null,
        public ?string $datasetId = null,
        public mixed $metadata = null,
        public ?array $scores = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<int, array<string, mixed>>|null $scores */
        $scores = is_array($data['scores'] ?? null) ? $data['scores'] : null;

        return new self(
            id: self::asString($data['id'] ?? null),
            name: self::asString($data['name'] ?? null),
            description: self::asNullableString($data['description'] ?? null),
            startTime: self::asNullableString($data['startTime'] ?? null),
            endTime: self::asNullableString($data['endTime'] ?? null),
            itemCount: is_int($data['itemCount'] ?? null) ? $data['itemCount'] : null,
            datasetId: self::asNullableString($data['datasetId'] ?? null),
            metadata: $data['metadata'] ?? null,
            scores: $scores === null ? null : array_map(
                fn(array $score): ScoreResponse => ScoreResponse::fromArray($score),
                $scores,
            ),
        );
    }

    private static function asString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function asNullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
