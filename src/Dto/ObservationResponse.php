<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

use Axyr\Langfuse\Enums\ObservationLevel;

/**
 * Curated projection of the ObservationsView / ObservationV2 schemas; the wide
 * constructor maps the response fields one-to-one and is intentional.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
readonly class ObservationResponse
{
    /**
     * @param  array<string, int|float>|null  $usageDetails
     * @param  array<string, int|float>|null  $costDetails
     */
    public function __construct(
        public string $id,
        public string $type,
        public ?string $name,
        public ?string $traceId,
        public ?string $parentObservationId,
        public string $startTime,
        public ?string $endTime,
        public ?string $completionStartTime,
        public ?ObservationLevel $level,
        public ?string $statusMessage,
        public ?string $environment,
        public ?string $model,
        public ?string $modelId,
        public mixed $input,
        public mixed $output,
        public mixed $metadata,
        public ?array $usageDetails,
        public ?array $costDetails,
        public ?float $latency,
        public ?string $promptName,
        public ?int $promptVersion,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $levelValue = $data['level'] ?? null;

        return new self(
            id: self::asString($data['id'] ?? null),
            type: self::asString($data['type'] ?? null),
            name: self::asNullableString($data['name'] ?? null),
            traceId: self::asNullableString($data['traceId'] ?? null),
            parentObservationId: self::asNullableString($data['parentObservationId'] ?? null),
            startTime: self::asString($data['startTime'] ?? null),
            endTime: self::asNullableString($data['endTime'] ?? null),
            completionStartTime: self::asNullableString($data['completionStartTime'] ?? null),
            level: is_string($levelValue) ? ObservationLevel::tryFrom($levelValue) : null,
            statusMessage: self::asNullableString($data['statusMessage'] ?? null),
            environment: self::asNullableString($data['environment'] ?? null),
            model: self::asNullableString($data['model'] ?? $data['providedModelName'] ?? null),
            modelId: self::asNullableString($data['modelId'] ?? $data['internalModelId'] ?? null),
            input: $data['input'] ?? null,
            output: $data['output'] ?? null,
            metadata: $data['metadata'] ?? null,
            usageDetails: self::asNullableArray($data['usageDetails'] ?? null),
            costDetails: self::asNullableArray($data['costDetails'] ?? null),
            latency: self::asNullableFloat($data['latency'] ?? null),
            promptName: self::asNullableString($data['promptName'] ?? null),
            promptVersion: self::asNullableInt($data['promptVersion'] ?? null),
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

    private static function asNullableFloat(mixed $value): ?float
    {
        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    private static function asNullableInt(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    /**
     * @return array<string, int|float>|null
     */
    private static function asNullableArray(mixed $value): ?array
    {
        /** @var array<string, int|float>|null */
        return is_array($value) ? $value : null;
    }
}
