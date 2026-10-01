<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

/**
 * Mirrors the ExperimentItem schema. `id` is the root observation id of the
 * item's trace; `experimentItemId` is the dataset item id.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
readonly class ExperimentItemResponse
{
    /**
     * @param  array<ScoreResponse>|null  $scores
     */
    public function __construct(
        public string $id,
        public string $traceId,
        public ?string $startTime = null,
        public ?string $endTime = null,
        public ?string $level = null,
        public ?string $environment = null,
        public ?string $experimentId = null,
        public ?string $experimentName = null,
        public ?string $experimentItemId = null,
        public ?string $experimentDatasetId = null,
        public ?string $experimentItemVersion = null,
        public mixed $input = null,
        public mixed $output = null,
        public mixed $expectedOutput = null,
        public mixed $metadata = null,
        public mixed $experimentItemMetadata = null,
        public mixed $experimentMetadata = null,
        public ?string $experimentDescription = null,
        public ?array $scores = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    public static function fromArray(array $data): self
    {
        /** @var array<int, array<string, mixed>>|null $scores */
        $scores = is_array($data['scores'] ?? null) ? $data['scores'] : null;

        return new self(
            id: self::asString($data['id'] ?? null),
            traceId: self::asString($data['traceId'] ?? null),
            startTime: self::asNullableString($data['startTime'] ?? null),
            endTime: self::asNullableString($data['endTime'] ?? null),
            level: self::asNullableString($data['level'] ?? null),
            environment: self::asNullableString($data['environment'] ?? null),
            experimentId: self::asNullableString($data['experimentId'] ?? null),
            experimentName: self::asNullableString($data['experimentName'] ?? null),
            experimentItemId: self::asNullableString($data['experimentItemId'] ?? null),
            experimentDatasetId: self::asNullableString($data['experimentDatasetId'] ?? null),
            experimentItemVersion: self::asNullableString($data['experimentItemVersion'] ?? null),
            input: $data['input'] ?? null,
            output: $data['output'] ?? null,
            expectedOutput: $data['expectedOutput'] ?? null,
            metadata: $data['metadata'] ?? null,
            experimentItemMetadata: $data['experimentItemMetadata'] ?? null,
            experimentMetadata: $data['experimentMetadata'] ?? null,
            experimentDescription: self::asNullableString($data['experimentDescription'] ?? null),
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
