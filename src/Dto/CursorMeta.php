<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

/**
 * Cursor pagination meta, as used by the v3 scores and the experiments APIs.
 * The cursor is absent on the last page.
 */
readonly class CursorMeta
{
    public function __construct(
        public ?int $limit = null,
        public ?string $cursor = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            limit: is_int($data['limit'] ?? null) ? $data['limit'] : null,
            cursor: is_string($data['cursor'] ?? null) ? $data['cursor'] : null,
        );
    }

    public function hasMore(): bool
    {
        return $this->cursor !== null;
    }
}
