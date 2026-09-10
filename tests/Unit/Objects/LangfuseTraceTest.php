<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\EventBody;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\IdGenerator;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Enums\ObservationType;
use Axyr\Langfuse\Objects\LangfuseGeneration;
use Axyr\Langfuse\Objects\LangfuseSpan;
use Axyr\Langfuse\Objects\LangfuseTrace;
use Axyr\Langfuse\Objects\OpenObservationRegistry;
use Axyr\Langfuse\Testing\RecordingEventBatcher;
use Axyr\Langfuse\Tracing\CompletedObservation;
use Illuminate\Support\Facades\Log;

function traceOn(RecordingEventBatcher $batcher, ?TraceBody $body = null, ?OpenObservationRegistry $registry = null): LangfuseTrace
{
    return new LangfuseTrace(
        body: $body ?? new TraceBody(id: 'trace-1'),
        batcher: $batcher,
        registry: $registry,
    );
}

it('sends nothing when the trace is created', function () {
    $batcher = new RecordingEventBatcher();

    traceOn($batcher, new TraceBody(id: 'trace-1', name: 'test'));

    expect($batcher->observations())->toBeEmpty()
        ->and($batcher->count())->toBe(0);
});

it('exposes the normalised trace id and a separate root observation id', function () {
    $trace = traceOn(new RecordingEventBatcher());

    expect($trace->getId())->toBe(IdGenerator::traceIdFromSeed('trace-1'))
        ->and($trace->getRootObservationId())->toMatch('/^[0-9a-f]{16}$/');
});

it('exports the root span once when the trace ends', function () {
    $batcher = new RecordingEventBatcher();
    $trace = traceOn($batcher, new TraceBody(id: 'trace-1', name: 'checkout', input: 'question'));

    $trace->end(endTime: '2024-01-01T00:00:05Z', output: 'answer');

    expect($batcher->observations())->toHaveCount(1);

    $root = $batcher->observations()[0];

    expect($root->isRoot())->toBeTrue()
        ->and($root->traceId())->toBe($trace->getId())
        ->and($root->observationId())->toBe($trace->getRootObservationId())
        ->and($root->parentId())->toBeNull()
        ->and($root->name())->toBe('checkout')
        ->and($root->type())->toBe(ObservationType::Span)
        ->and($root->endTime)->toBe('2024-01-01T00:00:05Z')
        ->and($root->body->output)->toBe('answer');
});

it('warns and does nothing when a trace ends twice', function () {
    Log::shouldReceive('warning')->once()->with('Langfuse trace ended twice', Mockery::any());

    $batcher = new RecordingEventBatcher();
    $trace = traceOn($batcher);

    $trace->end();
    $trace->end();

    expect($batcher->observations())->toHaveCount(1);
});

it('warns and ignores an update after the trace ended', function () {
    Log::shouldReceive('warning')->once()->with('Langfuse trace updated after it ended', Mockery::any());

    $batcher = new RecordingEventBatcher();
    $trace = traceOn($batcher, new TraceBody(id: 'trace-1', name: 'original'));

    $trace->end();
    $trace->update(new TraceBody(name: 'renamed'));

    expect($batcher->observations()[0]->body->name)->toBe('original');
});

it('merges an update into the in-memory trace without sending anything', function () {
    $batcher = new RecordingEventBatcher();
    $trace = traceOn($batcher, new TraceBody(id: 'trace-1', name: 'original', metadata: ['source' => 'test']));

    $trace->update(new TraceBody(userId: 'user-1', output: 'result', metadata: ['step' => 2]));

    expect($batcher->observations())->toBeEmpty();

    $trace->end();

    $body = $batcher->observations()[0]->body;

    expect($body->name)->toBe('original')
        ->and($body->userId)->toBe('user-1')
        ->and($body->output)->toBe('result')
        ->and($body->metadata)->toBe(['source' => 'test', 'step' => 2]);
});

it('preserves the trace id and timestamp on update', function () {
    $batcher = new RecordingEventBatcher();
    $trace = traceOn($batcher, new TraceBody(id: 'trace-1', timestamp: '2024-01-01T00:00:00Z'));

    $trace->update(new TraceBody(id: 'ignored-id', output: 'result'));
    $trace->end();

    $body = $batcher->observations()[0]->body;

    expect($body->id)->toBe($trace->getId())
        ->and($body->timestamp)->toBe('2024-01-01T00:00:00Z')
        ->and($body->output)->toBe('result');
});

it('nests children under the root observation', function () {
    $batcher = new RecordingEventBatcher();
    $trace = traceOn($batcher);

    $span = $trace->span(new SpanBody(name: 'child-span'));
    $generation = $trace->generation(new GenerationBody(name: 'child-generation'));

    expect($span)->toBeInstanceOf(LangfuseSpan::class)
        ->and($generation)->toBeInstanceOf(LangfuseGeneration::class)
        ->and($span->getBody()->parentObservationId)->toBe($trace->getRootObservationId())
        ->and($span->getBody()->traceId)->toBe($trace->getId())
        ->and($generation->getBody()->parentObservationId)->toBe($trace->getRootObservationId())
        ->and($generation->getBody()->traceId)->toBe($trace->getId());
});

it('leaves an explicitly chosen parent alone', function () {
    $batcher = new RecordingEventBatcher();
    $trace = traceOn($batcher);
    $parentId = '9999999999999999';

    $span = $trace->span(new SpanBody(parentObservationId: $parentId));

    expect($span->getBody()->parentObservationId)->toBe($parentId);
});

it('exports an event immediately with equal start and end times', function () {
    $batcher = new RecordingEventBatcher();
    $trace = traceOn($batcher);

    $trace->event(new EventBody(name: 'child-event', startTime: '2024-01-01T00:00:00Z'));

    $observations = $batcher->observationsOfType(ObservationType::Event);

    expect($observations)->toHaveCount(1)
        ->and($observations[0]->traceId())->toBe($trace->getId())
        ->and($observations[0]->parentId())->toBe($trace->getRootObservationId())
        ->and($observations[0]->startTime)->toBe('2024-01-01T00:00:00Z')
        ->and($observations[0]->endTime)->toBe('2024-01-01T00:00:00Z');
});

it('creates a score referencing the trace', function () {
    $batcher = new RecordingEventBatcher();
    $trace = traceOn($batcher);

    $trace->score(new ScoreBody(id: 'score-1', name: 'accuracy'));

    expect($batcher->scores())->toHaveCount(1)
        ->and($batcher->scores()[0]->traceId)->toBe($trace->getId());
});

it('propagates the trace environment to child observations and scores', function () {
    $batcher = new RecordingEventBatcher();
    $trace = traceOn($batcher, new TraceBody(id: 'trace-1', environment: 'staging'));

    $span = $trace->span(new SpanBody(name: 'span'));
    $generation = $trace->generation(new GenerationBody(name: 'gen'));
    $trace->event(new EventBody(name: 'evt'));
    $trace->score(new ScoreBody(name: 'accuracy', value: 1.0));

    expect($span->getBody()->environment)->toBe('staging')
        ->and($generation->getBody()->environment)->toBe('staging')
        ->and($batcher->observations()[0]->body->environment)->toBe('staging')
        ->and($batcher->scores()[0]->environment)->toBe('staging');
});

it('keeps an explicit child environment over the trace environment', function () {
    $batcher = new RecordingEventBatcher();
    $trace = traceOn($batcher, new TraceBody(id: 'trace-1', environment: 'staging'));

    $generation = $trace->generation(new GenerationBody(environment: 'production'));

    expect($generation->getBody()->environment)->toBe('production');
});

it('leaves children without environment when the trace has none', function () {
    $batcher = new RecordingEventBatcher();
    $trace = traceOn($batcher);

    expect($trace->generation(new GenerationBody())->getBody()->environment)->toBeNull();
});

it('registers itself and unregisters when it ends', function () {
    $registry = new OpenObservationRegistry();
    $batcher = new RecordingEventBatcher();

    $trace = traceOn($batcher, null, $registry);

    expect($registry->count())->toBe(1)
        ->and($trace->hasEnded())->toBeFalse();

    $trace->end();

    expect($registry->count())->toBe(0)
        ->and($trace->hasEnded())->toBeTrue()
        ->and($registry->all())->toHaveCount(1);
});

it('shares one trace context with its children', function () {
    $batcher = new RecordingEventBatcher();
    $trace = traceOn($batcher);

    $span = $trace->span(new SpanBody(name: 'child'));
    $trace->update(new TraceBody(userId: 'user-late'));
    $span->end();

    /** @var CompletedObservation $exported */
    $exported = $batcher->observations()[0];

    expect($exported->context->body()->userId)->toBe('user-late');
});
