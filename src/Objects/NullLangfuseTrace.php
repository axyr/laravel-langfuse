<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Objects;

use Axyr\Langfuse\Dto\EventBody;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Tracing\TraceContext;

/**
 * The "no current trace" sentinel: it registers nothing and exports nothing.
 */
class NullLangfuseTrace extends LangfuseTrace
{
    public function __construct() {}

    public function getId(): string
    {
        return '';
    }

    public function getRootObservationId(): string
    {
        return '';
    }

    public function getBody(): TraceBody
    {
        return new TraceBody();
    }

    public function getContext(): TraceContext
    {
        return new TraceContext(new TraceBody());
    }

    public function hasEnded(): bool
    {
        return true;
    }

    public function update(TraceBody $body): void {}

    public function span(SpanBody $span): LangfuseSpan
    {
        return new NullLangfuseSpan();
    }

    public function generation(GenerationBody $generation): LangfuseGeneration
    {
        return new NullLangfuseGeneration();
    }

    public function event(EventBody $event): void {}

    public function score(ScoreBody $score, ?string $timestamp = null): void {}

    public function end(?string $endTime = null, mixed $output = null): void {}

    public function endOnShutdown(): void {}
}
