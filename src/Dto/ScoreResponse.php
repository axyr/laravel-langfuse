<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

/**
 * Mirrors the ScoreV3 schema. `value` is polymorphic: a number for NUMERIC, a
 * boolean for BOOLEAN and a string for CATEGORICAL, TEXT and CORRECTION. There
 * is no `stringValue` and no nested trace object in v3; what the score is
 * attached to is in `subject`, requested with `fields=subject`.
 *
 * `dataType` and `source` stay raw strings so values this package does not know
 * yet are preserved.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
readonly class ScoreResponse
{
    public function __construct(
        public string $id,
        public string $dataType,
        public string $name,
        public float|bool|string|null $value,
        public string $source,
        public string $timestamp,
        public string $createdAt,
        public string $updatedAt,
        public string $environment,
        public ?string $projectId = null,
        public ?ScoreSubject $subject = null,
        public ?string $comment = null,
        public ?string $configId = null,
        public mixed $metadata = null,
        public ?string $authorUserId = null,
        public ?string $queueId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $subject */
        $subject = is_array($data['subject'] ?? null) ? $data['subject'] : [];

        return new self(
            id: self::asString($data['id'] ?? null),
            dataType: self::asString($data['dataType'] ?? null),
            name: self::asString($data['name'] ?? null),
            value: self::asValue($data['value'] ?? null),
            source: self::asString($data['source'] ?? null),
            timestamp: self::asString($data['timestamp'] ?? null),
            createdAt: self::asString($data['createdAt'] ?? null),
            updatedAt: self::asString($data['updatedAt'] ?? null),
            environment: self::asString($data['environment'] ?? null),
            projectId: self::asNullableString($data['projectId'] ?? null),
            subject: $subject === [] ? null : ScoreSubject::fromArray($subject),
            comment: self::asNullableString($data['comment'] ?? null),
            configId: self::asNullableString($data['configId'] ?? null),
            metadata: $data['metadata'] ?? null,
            authorUserId: self::asNullableString($data['authorUserId'] ?? null),
            queueId: self::asNullableString($data['queueId'] ?? null),
        );
    }

    public function traceId(): ?string
    {
        if ($this->subject?->kind === ScoreSubject::KIND_TRACE) {
            return $this->subject->id;
        }

        return $this->subject?->traceId;
    }

    public function observationId(): ?string
    {
        return $this->idForKind(ScoreSubject::KIND_OBSERVATION);
    }

    public function sessionId(): ?string
    {
        return $this->idForKind(ScoreSubject::KIND_SESSION);
    }

    public function experimentId(): ?string
    {
        return $this->idForKind(ScoreSubject::KIND_EXPERIMENT);
    }

    private function idForKind(string $kind): ?string
    {
        return $this->subject?->kind === $kind ? $this->subject->id : null;
    }

    private static function asValue(mixed $value): float|bool|string|null
    {
        if (is_bool($value) || is_string($value)) {
            return $value;
        }

        return self::asNullableFloat($value);
    }

    private static function asNullableFloat(mixed $value): ?float
    {
        return is_int($value) || is_float($value) ? (float) $value : null;
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
