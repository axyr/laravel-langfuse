<?php

declare(strict_types=1);

use Langfuse\Contracts\EventBatcherInterface;
use Langfuse\Dto\EventBody;
use Langfuse\Dto\GenerationBody;
use Langfuse\Dto\IngestionEvent;
use Langfuse\Dto\SpanBody;
use Langfuse\Enums\EventType;
use Langfuse\Objects\LangfuseGeneration;
use Langfuse\Objects\LangfuseSpan;

it('enqueues span-create event on construction', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')
        ->once()
        ->with(Mockery::on(function (IngestionEvent $event) {
            return $event->type === EventType::SpanCreate
                && $event->body instanceof SpanBody
                && $event->body->id === 'span-1'
                && $event->body->traceId === 'trace-1';
        }));

    new LangfuseSpan(
        body: new SpanBody(id: 'span-1', traceId: 'trace-1', name: 'test'),
        batcher: $batcher,
    );
});

it('exposes id and trace id', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')->once();

    $span = new LangfuseSpan(
        body: new SpanBody(id: 'span-1', traceId: 'trace-1'),
        batcher: $batcher,
    );

    expect($span->getId())->toBe('span-1')
        ->and($span->getTraceId())->toBe('trace-1');
});

it('creates nested span with parent observation id', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')
        ->twice() // parent span + child span
        ->with(Mockery::on(function (IngestionEvent $event) {
            if ($event->body instanceof SpanBody && $event->body->id === 'child-span') {
                return $event->body->parentObservationId === 'span-1'
                    && $event->body->traceId === 'trace-1';
            }

            return true;
        }));

    $span = new LangfuseSpan(
        body: new SpanBody(id: 'span-1', traceId: 'trace-1'),
        batcher: $batcher,
    );

    $child = $span->span(new SpanBody(id: 'child-span', name: 'nested'));

    expect($child)->toBeInstanceOf(LangfuseSpan::class);
});

it('creates child generation with parent observation id', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')
        ->twice()
        ->with(Mockery::on(function (IngestionEvent $event) {
            if ($event->body instanceof GenerationBody) {
                return $event->body->parentObservationId === 'span-1'
                    && $event->body->traceId === 'trace-1';
            }

            return true;
        }));

    $span = new LangfuseSpan(
        body: new SpanBody(id: 'span-1', traceId: 'trace-1'),
        batcher: $batcher,
    );

    $gen = $span->generation(new GenerationBody(id: 'gen-1', model: 'gpt-4'));

    expect($gen)->toBeInstanceOf(LangfuseGeneration::class);
});

it('creates child event with parent observation id', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')
        ->twice()
        ->with(Mockery::on(function (IngestionEvent $event) {
            if ($event->type === EventType::EventCreate) {
                return $event->body instanceof EventBody
                    && $event->body->parentObservationId === 'span-1'
                    && $event->body->traceId === 'trace-1';
            }

            return true;
        }));

    $span = new LangfuseSpan(
        body: new SpanBody(id: 'span-1', traceId: 'trace-1'),
        batcher: $batcher,
    );

    $span->event(new EventBody(id: 'event-1', name: 'child-event'));
});

it('sends span-update event on end', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')
        ->twice() // create + update
        ->with(Mockery::on(function (IngestionEvent $event) {
            if ($event->type === EventType::SpanUpdate) {
                return $event->body instanceof SpanBody
                    && $event->body->id === 'span-1'
                    && $event->body->output === 'done';
            }

            return true;
        }));

    $span = new LangfuseSpan(
        body: new SpanBody(id: 'span-1', traceId: 'trace-1'),
        batcher: $batcher,
    );

    $span->end(output: 'done');
});
