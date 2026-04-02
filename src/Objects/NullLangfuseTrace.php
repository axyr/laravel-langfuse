<?php

declare(strict_types=1);

namespace Axyr\Langfuse\Objects;

use Axyr\Langfuse\Dto\EventBody;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TraceBody;

class NullLangfuseTrace extends LangfuseTrace
{
    public function __construct() {}

    public function getId(): string
    {
        return '';
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

    public function score(ScoreBody $score): void {}
}
