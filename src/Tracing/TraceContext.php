<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Tracing;

use Axyr\Langfuse\Dto\IdGenerator;
use Axyr\Langfuse\Dto\TraceBody;

/**
 * The live, mutable state of one trace, shared by its root observation and every
 * child. Trace-level attributes are read from here when the batch is serialised,
 * so anything set on the trace before the batch leaves the process is copied
 * onto every span of that trace.
 */
class TraceContext
{
    private TraceBody $body;

    private readonly string $rootObservationId;

    public function __construct(TraceBody $body, ?string $rootObservationId = null)
    {
        $this->body = $body;
        $this->rootObservationId = IdGenerator::normalizeObservationId($rootObservationId);
    }

    public function traceId(): string
    {
        return $this->body->id;
    }

    public function rootObservationId(): string
    {
        return $this->rootObservationId;
    }

    public function body(): TraceBody
    {
        return $this->body;
    }

    public function environment(): ?string
    {
        return $this->body->environment;
    }

    public function merge(TraceBody $update): void
    {
        $this->body = $this->body->mergedWith($update);
    }
}
