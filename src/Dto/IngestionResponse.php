<?php

declare(strict_types=1);

namespace Langfuse\Dto;

readonly class IngestionResponse
{
    /**
     * @param array<IngestionSuccess> $successes
     * @param array<IngestionError> $errors
     */
    public function __construct(
        public array $successes,
        public array $errors,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<array{id: string, status: int}> $successes */
        $successes = $data['successes'] ?? [];

        /** @var array<array{id: string, status: int, message: string, error?: string}> $errors */
        $errors = $data['errors'] ?? [];

        return new self(
            successes: array_map(
                fn(array $item): IngestionSuccess => new IngestionSuccess(
                    id: $item['id'],
                    status: $item['status'],
                ),
                $successes,
            ),
            errors: array_map(
                fn(array $item): IngestionError => new IngestionError(
                    id: $item['id'],
                    status: $item['status'],
                    message: $item['message'],
                    error: $item['error'] ?? null,
                ),
                $errors,
            ),
        );
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
