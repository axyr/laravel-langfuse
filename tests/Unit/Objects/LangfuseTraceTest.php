<?php

declare(strict_types=1);

use Axyr\Langfuse\Batch\NullEventBatcher;
use Axyr\Langfuse\Contracts\EventBatcherInterface;
use Axyr\Langfuse\Dto\EventBody;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\IngestionEvent;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Enums\EventType;
use Axyr\Langfuse\Objects\LangfuseGeneration;
use Axyr\Langfuse\Objects\LangfuseSpan;
use Axyr\Langfuse\Objects\LangfuseTrace;

it('enqueues trace-create event on construction', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')
        ->once()
        ->with(Mockery::on(function (IngestionEvent $event) {
            return $event->type === EventType::TraceCreate
                && $event->body instanceof TraceBody
                && $event->body->id === 'trace-1';
        }));

    new LangfuseTrace(
        body: new TraceBody(id: 'trace-1', name: 'test'),
        batcher: $batcher,
    );
});

it('exposes trace id', function () {
    $trace = new LangfuseTrace(
        body: new TraceBody(id: 'trace-1'),
        batcher: new NullEventBatcher(),
    );

    expect($trace->getId())->toBe('trace-1');
});

it('creates child span with correct trace id', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')->twice(); // trace + span

    $trace = new LangfuseTrace(
        body: new TraceBody(id: 'trace-1'),
        batcher: $batcher,
    );

    $span = $trace->span(new SpanBody(id: 'span-1', name: 'child-span'));

    expect($span)->toBeInstanceOf(LangfuseSpan::class);
});

it('creates child generation with correct trace id', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')->twice(); // trace + generation

    $trace = new LangfuseTrace(
        body: new TraceBody(id: 'trace-1'),
        batcher: $batcher,
    );

    $gen = $trace->generation(new GenerationBody(id: 'gen-1', model: 'gpt-4'));

    expect($gen)->toBeInstanceOf(LangfuseGeneration::class);
});

it('creates child event with correct trace id', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')
        ->twice() // trace + event
        ->with(Mockery::on(function (IngestionEvent $event) {
            if ($event->type === EventType::EventCreate) {
                return $event->body instanceof EventBody
                    && $event->body->traceId === 'trace-1';
            }

            return true;
        }));

    $trace = new LangfuseTrace(
        body: new TraceBody(id: 'trace-1'),
        batcher: $batcher,
    );

    $trace->event(new EventBody(id: 'event-1', name: 'child-event'));
});

it('enqueues trace-create event with same id on update', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')
        ->twice() // initial create + update
        ->with(Mockery::on(function (IngestionEvent $event) {
            return $event->type === EventType::TraceCreate
                && $event->body instanceof TraceBody
                && $event->body->id === 'trace-1';
        }));

    $trace = new LangfuseTrace(
        body: new TraceBody(id: 'trace-1', name: 'original'),
        batcher: $batcher,
    );

    $trace->update(new TraceBody(output: 'final result', metadata: ['key' => 'value']));
});

it('preserves trace id on update ignoring body id', function () {
    $events = [];
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')
        ->twice()
        ->with(Mockery::on(function (IngestionEvent $event) use (&$events) {
            $events[] = $event;

            return true;
        }));

    $trace = new LangfuseTrace(
        body: new TraceBody(id: 'trace-1', name: 'original'),
        batcher: $batcher,
    );

    $trace->update(new TraceBody(id: 'ignored-id', output: 'result'));

    expect($events[1]->body->id)->toBe('trace-1')
        ->and($events[1]->body->output)->toBe('result');
});

it('creates score referencing the trace', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')
        ->twice() // trace + score
        ->with(Mockery::on(function (IngestionEvent $event) {
            if ($event->type === EventType::ScoreCreate) {
                return $event->body instanceof ScoreBody
                    && $event->body->traceId === 'trace-1';
            }

            return true;
        }));

    $trace = new LangfuseTrace(
        body: new TraceBody(id: 'trace-1'),
        batcher: $batcher,
    );

    $trace->score(new ScoreBody(id: 'score-1', name: 'accuracy'));
});
