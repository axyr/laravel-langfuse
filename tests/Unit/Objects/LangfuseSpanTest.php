<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\EventBody;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Enums\ObservationLevel;
use Axyr\Langfuse\Enums\ObservationType;
use Axyr\Langfuse\Objects\LangfuseGeneration;
use Axyr\Langfuse\Objects\LangfuseSpan;
use Axyr\Langfuse\Objects\OpenObservationRegistry;
use Axyr\Langfuse\Testing\RecordingEventBatcher;
use Axyr\Langfuse\Tracing\TraceContext;
use Illuminate\Support\Facades\Log;

function spanContext(?string $environment = null): TraceContext
{
    return new TraceContext(new TraceBody(id: 'trace-1', environment: $environment));
}

function spanOn(RecordingEventBatcher $batcher, ?SpanBody $body = null, ?OpenObservationRegistry $registry = null, ?TraceContext $context = null): LangfuseSpan
{
    $context ??= spanContext();

    return new LangfuseSpan(
        body: ($body ?? new SpanBody(id: 'span-1'))->withTraceId($context->traceId()),
        batcher: $batcher,
        context: $context,
        registry: $registry,
    );
}

it('sends nothing when the span is created', function () {
    $batcher = new RecordingEventBatcher();

    spanOn($batcher, new SpanBody(id: 'span-1', name: 'test'));

    expect($batcher->observations())->toBeEmpty();
});

it('exposes id and trace id', function () {
    $context = spanContext();
    $span = spanOn(new RecordingEventBatcher(), null, null, $context);

    expect($span->getId())->toBe((new SpanBody(id: 'span-1'))->id)
        ->and($span->getTraceId())->toBe($context->traceId());
});

it('stamps a start time at creation', function () {
    $span = spanOn(new RecordingEventBatcher());

    expect($span->getBody()->startTime)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/');
});

it('keeps an explicit start time', function () {
    $span = spanOn(new RecordingEventBatcher(), new SpanBody(startTime: '2024-01-01T00:00:00Z'));

    expect($span->getBody()->startTime)->toBe('2024-01-01T00:00:00Z');
});

it('exports one completed observation on end', function () {
    $batcher = new RecordingEventBatcher();
    $span = spanOn($batcher, new SpanBody(id: 'span-1', name: 'work', startTime: '2024-01-01T00:00:00Z'));

    $span->end(endTime: '2024-01-01T00:00:05Z', output: 'done');

    expect($batcher->observations())->toHaveCount(1);

    $exported = $batcher->observations()[0];

    expect($exported->observationId())->toBe($span->getId())
        ->and($exported->name())->toBe('work')
        ->and($exported->type())->toBe(ObservationType::Span)
        ->and($exported->startTime)->toBe('2024-01-01T00:00:00Z')
        ->and($exported->endTime)->toBe('2024-01-01T00:00:05Z')
        ->and($exported->body->output)->toBe('done');
});

it('carries the span type override into the exported observation', function () {
    $batcher = new RecordingEventBatcher();
    $span = spanOn($batcher, new SpanBody(name: 'search', type: ObservationType::Retriever));

    $span->end();

    expect($batcher->observations()[0]->type())->toBe(ObservationType::Retriever);
});

it('warns and does nothing when a span ends twice', function () {
    Log::shouldReceive('warning')->once()->with('Langfuse span ended twice', Mockery::any());

    $batcher = new RecordingEventBatcher();
    $span = spanOn($batcher);

    $span->end();
    $span->end();

    expect($batcher->observations())->toHaveCount(1);
});

it('creates nested spans and generations with itself as parent', function () {
    $batcher = new RecordingEventBatcher();
    $span = spanOn($batcher);

    $child = $span->span(new SpanBody(name: 'nested'));
    $generation = $span->generation(new GenerationBody(name: 'gen'));

    expect($child)->toBeInstanceOf(LangfuseSpan::class)
        ->and($generation)->toBeInstanceOf(LangfuseGeneration::class)
        ->and($child->getBody()->parentObservationId)->toBe($span->getId())
        ->and($child->getBody()->traceId)->toBe($span->getTraceId())
        ->and($generation->getBody()->parentObservationId)->toBe($span->getId());
});

it('exports a child event immediately with itself as parent', function () {
    $batcher = new RecordingEventBatcher();
    $span = spanOn($batcher);

    $span->event(new EventBody(name: 'child-event'));

    $events = $batcher->observationsOfType(ObservationType::Event);

    expect($events)->toHaveCount(1)
        ->and($events[0]->parentId())->toBe($span->getId());
});

it('propagates the span environment to nested observations and to its own export', function () {
    $batcher = new RecordingEventBatcher();
    $span = spanOn($batcher, new SpanBody(environment: 'staging'), null, spanContext('staging'));

    $nested = $span->span(new SpanBody());
    $generation = $span->generation(new GenerationBody());
    $span->event(new EventBody());
    $span->end();

    expect($nested->getBody()->environment)->toBe('staging')
        ->and($generation->getBody()->environment)->toBe('staging')
        ->and($batcher->observations()[0]->body->environment)->toBe('staging')
        ->and($batcher->observations()[1]->body->environment)->toBe('staging');
});

it('registers itself and unregisters when it ends', function () {
    $registry = new OpenObservationRegistry();
    $batcher = new RecordingEventBatcher();

    $span = spanOn($batcher, null, $registry);

    expect($registry->count())->toBe(1);

    $span->end();

    expect($registry->count())->toBe(0)
        ->and($span->hasEnded())->toBeTrue();
});

it('marks a span ended by shutdown with a warning level', function () {
    $batcher = new RecordingEventBatcher();
    $span = spanOn($batcher);

    $span->endOnShutdown();

    expect($batcher->observations()[0]->body->level)->toBe(ObservationLevel::WARNING)
        ->and($batcher->observations()[0]->body->statusMessage)->toBe('ended by shutdown');
});
