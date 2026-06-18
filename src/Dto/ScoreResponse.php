<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

readonly class ScoreResponse
{
    public function __construct(
        public string $id,
        public string $dataType,
        public string $name,
        public ?float $value,
        public ?string $stringValue,
        public string $source,
        public string $timestamp,
        public string $createdAt,
        public string $updatedAt,
        public string $environment,
        public ?string $traceId = null,
        public ?string $sessionId = null,
        public ?string $observationId = null,
        public ?string $datasetRunId = null,
        public ?string $authorUserId = null,
        public ?string $comment = null,
        public ?string $configId = null,
        public ?string $queueId = null,
        public mixed $metadata = null,
        public ?ScoreTraceData $trace = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $trace */
        $trace = is_array($data['trace'] ?? null) ? $data['trace'] : [];

        return new self(
            id: self::asString($data['id'] ?? null),
            dataType: self::asString($data['dataType'] ?? null),
            name: self::asString($data['name'] ?? null),
            value: self::asNullableFloat($data['value'] ?? null),
            stringValue: self::asNullableString($data['stringValue'] ?? null),
            source: self::asString($data['source'] ?? null),
            timestamp: self::asString($data['timestamp'] ?? null),
            createdAt: self::asString($data['createdAt'] ?? null),
            updatedAt: self::asString($data['updatedAt'] ?? null),
            environment: self::asString($data['environment'] ?? null),
            traceId: self::asNullableString($data['traceId'] ?? null),
            sessionId: self::asNullableString($data['sessionId'] ?? null),
            observationId: self::asNullableString($data['observationId'] ?? null),
            datasetRunId: self::asNullableString($data['datasetRunId'] ?? null),
            authorUserId: self::asNullableString($data['authorUserId'] ?? null),
            comment: self::asNullableString($data['comment'] ?? null),
            configId: self::asNullableString($data['configId'] ?? null),
            queueId: self::asNullableString($data['queueId'] ?? null),
            metadata: $data['metadata'] ?? null,
            trace: $trace === [] ? null : ScoreTraceData::fromArray($trace),
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
}
