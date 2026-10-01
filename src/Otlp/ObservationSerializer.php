<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Otlp;

use Axyr\Langfuse\Dto\Otlp\OtlpSpan;
use Axyr\Langfuse\Dto\Timestamp;
use Axyr\Langfuse\Enums\ObservationLevel;
use Axyr\Langfuse\Tracing\CompletedObservation;
use Illuminate\Support\Facades\Log;

/**
 * Turns a completed observation plus its live trace context into one OTLP span.
 * Trace-level attributes are read here, at serialisation, so anything set on the
 * trace before the batch leaves the process is copied onto every span.
 */
class ObservationSerializer
{
    public function __construct(
        private readonly TraceAttributes $traceAttributes = new TraceAttributes(),
        private readonly ObservationAttributes $observationAttributes = new ObservationAttributes(),
    ) {}

    public function toSpan(CompletedObservation $observation): OtlpSpan
    {
        $attributes = array_merge(
            $this->traceAttributes->forTrace($observation->context->body()),
            $this->observationAttributes->forObservation($observation),
        );

        $isError = $observation->level() === ObservationLevel::ERROR;

        return new OtlpSpan(
            traceId: $observation->traceId(),
            spanId: $observation->observationId(),
            parentSpanId: $observation->parentId(),
            name: $observation->name(),
            startTimeUnixNano: $this->unixNano($observation->startTime),
            endTimeUnixNano: $this->unixNano($observation->endTime),
            attributes: array_values($attributes),
            statusCode: $isError ? OtlpSpan::STATUS_ERROR : OtlpSpan::STATUS_UNSET,
            statusMessage: $isError ? ($observation->statusMessage() ?? '') : '',
        );
    }

    private function unixNano(string $iso): string
    {
        $nanoseconds = Timestamp::toUnixNano($iso);

        if ($nanoseconds === null) {
            Log::warning('Langfuse timestamp could not be parsed', ['timestamp' => $iso]);

            return Timestamp::nowUnixNano();
        }

        return $nanoseconds;
    }
}
