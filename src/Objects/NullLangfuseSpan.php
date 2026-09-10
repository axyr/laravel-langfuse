<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Objects;

use Axyr\Langfuse\Dto\EventBody;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Enums\ObservationLevel;

class NullLangfuseSpan extends LangfuseSpan
{
    public function __construct() {}

    public function getId(): string
    {
        return '';
    }

    public function getTraceId(): ?string
    {
        return null;
    }

    public function getBody(): SpanBody
    {
        return new SpanBody();
    }

    public function hasEnded(): bool
    {
        return true;
    }

    public function span(SpanBody $span): LangfuseSpan
    {
        return new NullLangfuseSpan();
    }

    public function generation(GenerationBody $generation): LangfuseGeneration
    {
        return new NullLangfuseGeneration();
    }

    public function event(EventBody $event): void {}

    public function end(
        ?string $endTime = null,
        mixed $output = null,
        ?string $statusMessage = null,
        ?ObservationLevel $level = null,
    ): void {}

    public function endOnShutdown(): void {}
}
