<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

readonly class ScoreTraceData
{
    /**
     * @param  array<string>  $tags
     */
    public function __construct(
        public ?string $userId = null,
        public array $tags = [],
        public ?string $environment = null,
        public ?string $sessionId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string> $tags */
        $tags = is_array($data['tags'] ?? null) ? $data['tags'] : [];

        return new self(
            userId: self::asNullableString($data['userId'] ?? null),
            tags: $tags,
            environment: self::asNullableString($data['environment'] ?? null),
            sessionId: self::asNullableString($data['sessionId'] ?? null),
        );
    }

    private static function asNullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
