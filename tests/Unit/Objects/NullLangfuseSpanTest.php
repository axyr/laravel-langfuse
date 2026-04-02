<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\EventBody;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Objects\NullLangfuseGeneration;
use Axyr\Langfuse\Objects\NullLangfuseSpan;

it('does not enqueue events on construction', function () {
    $span = new NullLangfuseSpan();

    expect($span)->toBeInstanceOf(NullLangfuseSpan::class);
});

it('returns empty string for getId', function () {
    $span = new NullLangfuseSpan();

    expect($span->getId())->toBe('');
});

it('returns null for getTraceId', function () {
    $span = new NullLangfuseSpan();

    expect($span->getTraceId())->toBeNull();
});

it('span returns a NullLangfuseSpan', function () {
    $span = new NullLangfuseSpan();

    $child = $span->span(new SpanBody(name: 'child'));

    expect($child)->toBeInstanceOf(NullLangfuseSpan::class);
});

it('generation returns a NullLangfuseGeneration', function () {
    $span = new NullLangfuseSpan();

    $generation = $span->generation(new GenerationBody(name: 'gen'));

    expect($generation)->toBeInstanceOf(NullLangfuseGeneration::class);
});

it('event is a no-op', function () {
    $span = new NullLangfuseSpan();

    $span->event(new EventBody(name: 'test'));

    expect($span->getId())->toBe('');
});

it('end is a no-op', function () {
    $span = new NullLangfuseSpan();

    $span->end(output: 'done');

    expect($span->getId())->toBe('');
});
