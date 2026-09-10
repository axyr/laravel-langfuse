<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

use Axyr\Langfuse\Enums\ObservationLevel;

/**
 * Mirrors the ObservationV2 schema; the wide constructor maps the response
 * fields one-to-one and is intentional.
 *
 * `type` stays a raw string: v4 adds AGENT, TOOL, CHAIN, RETRIEVER, EVALUATOR,
 * EMBEDDING and GUARDRAIL next to SPAN, GENERATION and EVENT, and unknown values
 * are passed through. Input and output are always returned as raw strings.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
readonly class ObservationResponse
{
    /**
     * @param  array<string, int|float>|null  $usageDetails
     * @param  array<string, int|float>|null  $costDetails
     * @param  array<string, mixed>|null  $modelParameters
     * @param  array<string>|null  $tags
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
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
        public ?bool $isRootObservation = null,
        public ?string $projectId = null,
        public ?bool $bookmarked = null,
        public ?bool $public = null,
        public ?string $userId = null,
        public ?string $sessionId = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?float $totalCost = null,
        public ?string $usagePricingTierName = null,
        public ?string $promptId = null,
        public ?float $timeToFirstToken = null,
        public ?string $traceName = null,
        public ?array $tags = null,
        public ?string $release = null,
        public ?array $modelParameters = null,
        public ?string $version = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    public static function fromArray(array $data): self
    {
        $levelValue = $data['level'] ?? null;

        /** @var list<string>|null $tags */
        $tags = is_array($data['tags'] ?? null) ? array_values($data['tags']) : null;

        /** @var array<string, mixed>|null $modelParameters */
        $modelParameters = is_array($data['modelParameters'] ?? null) ? $data['modelParameters'] : null;

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
            model: self::asNullableString($data['model'] ?? null),
            modelId: self::asNullableString($data['modelId'] ?? $data['internalModelId'] ?? null),
            input: $data['input'] ?? null,
            output: $data['output'] ?? null,
            metadata: $data['metadata'] ?? null,
            usageDetails: self::asNullableArray($data['usageDetails'] ?? null),
            costDetails: self::asNullableArray($data['costDetails'] ?? null),
            latency: self::asNullableFloat($data['latency'] ?? null),
            promptName: self::asNullableString($data['promptName'] ?? null),
            promptVersion: self::asNullableInt($data['promptVersion'] ?? null),
            isRootObservation: self::asNullableBool($data['isRootObservation'] ?? null),
            projectId: self::asNullableString($data['projectId'] ?? null),
            bookmarked: self::asNullableBool($data['bookmarked'] ?? null),
            public: self::asNullableBool($data['public'] ?? null),
            userId: self::asNullableString($data['userId'] ?? null),
            sessionId: self::asNullableString($data['sessionId'] ?? null),
            createdAt: self::asNullableString($data['createdAt'] ?? null),
            updatedAt: self::asNullableString($data['updatedAt'] ?? null),
            totalCost: self::asNullableFloat($data['totalCost'] ?? null),
            usagePricingTierName: self::asNullableString($data['usagePricingTierName'] ?? null),
            promptId: self::asNullableString($data['promptId'] ?? null),
            timeToFirstToken: self::asNullableFloat($data['timeToFirstToken'] ?? null),
            traceName: self::asNullableString($data['traceName'] ?? null),
            tags: $tags,
            release: self::asNullableString($data['release'] ?? null),
            modelParameters: $modelParameters,
            version: self::asNullableString($data['version'] ?? null),
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

    private static function asNullableBool(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
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
