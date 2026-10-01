<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Exceptions;

use RuntimeException;

/**
 * Thrown from a queue job when Langfuse answered with a status the OTLP spec
 * says may be retried, so the worker retries the batch instead of dropping it.
 */
class LangfuseExportRetryException extends RuntimeException
{
    public static function forStatus(?int $status, ?string $message = null): self
    {
        return new self(sprintf(
            'Langfuse OTLP export failed with a retryable status (%s): %s',
            $status === null ? 'transport error' : (string) $status,
            $message ?? 'no message',
        ));
    }
}
