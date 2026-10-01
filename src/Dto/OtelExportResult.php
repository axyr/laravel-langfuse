<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Dto;

/**
 * Outcome of one OTLP export. The synchronous batcher only logs; the queue job
 * uses `retryable` and `retryAfterSeconds` to decide whether to retry.
 */
readonly class OtelExportResult
{
    public function __construct(
        public bool $accepted,
        public bool $retryable = false,
        public ?int $status = null,
        public ?int $retryAfterSeconds = null,
        public int $rejectedSpans = 0,
        public ?string $errorMessage = null,
    ) {}

    public static function success(): self
    {
        return new self(accepted: true);
    }

    /**
     * OTLP partial success: the batch was accepted, some spans were not, and the
     * spec forbids retrying it.
     */
    public static function partial(int $rejectedSpans, ?string $errorMessage): self
    {
        return new self(
            accepted: true,
            rejectedSpans: $rejectedSpans,
            errorMessage: $errorMessage,
        );
    }

    public static function failed(int $status, bool $retryable, ?int $retryAfterSeconds = null, ?string $errorMessage = null): self
    {
        return new self(
            accepted: false,
            retryable: $retryable,
            status: $status,
            retryAfterSeconds: $retryAfterSeconds,
            errorMessage: $errorMessage,
        );
    }

    /**
     * A transport error: no response at all, so the payload may still be valid.
     */
    public static function transportError(string $errorMessage): self
    {
        return new self(
            accepted: false,
            retryable: true,
            errorMessage: $errorMessage,
        );
    }

    public function hasPartialFailure(): bool
    {
        return $this->rejectedSpans > 0;
    }
}
