<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\EventBody;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Objects\NullLangfuseGeneration;
use Axyr\Langfuse\Objects\NullLangfuseSpan;
use Axyr\Langfuse\Objects\NullLangfuseTrace;

it('does not enqueue events on construction', function () {
    $trace = new NullLangfuseTrace();

    expect($trace)->toBeInstanceOf(NullLangfuseTrace::class);
});

it('returns empty string for getId', function () {
    $trace = new NullLangfuseTrace();

    expect($trace->getId())->toBe('');
});

it('update is a no-op', function () {
    $trace = new NullLangfuseTrace();

    $trace->update(new TraceBody(name: 'updated'));

    expect($trace->getId())->toBe('');
});

it('span returns a NullLangfuseSpan', function () {
    $trace = new NullLangfuseTrace();

    $span = $trace->span(new SpanBody(name: 'child'));

    expect($span)->toBeInstanceOf(NullLangfuseSpan::class);
});

it('generation returns a NullLangfuseGeneration', function () {
    $trace = new NullLangfuseTrace();

    $generation = $trace->generation(new GenerationBody(name: 'gen'));

    expect($generation)->toBeInstanceOf(NullLangfuseGeneration::class);
});

it('event is a no-op', function () {
    $trace = new NullLangfuseTrace();

    $trace->event(new EventBody(name: 'test'));

    expect($trace->getId())->toBe('');
});

it('score is a no-op', function () {
    $trace = new NullLangfuseTrace();

    $trace->score(new ScoreBody(name: 'accuracy', traceId: 'trace-1', value: 0.9));

    expect($trace->getId())->toBe('');
});
