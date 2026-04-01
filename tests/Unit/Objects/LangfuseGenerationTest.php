<?php

declare(strict_types=1);

use Axyr\Langfuse\Contracts\EventBatcherInterface;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\IngestionEvent;
use Axyr\Langfuse\Dto\Usage;
use Axyr\Langfuse\Enums\EventType;
use Axyr\Langfuse\Enums\ObservationLevel;
use Axyr\Langfuse\Objects\LangfuseGeneration;

it('enqueues generation-create event on construction', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')
        ->once()
        ->with(Mockery::on(function (IngestionEvent $event) {
            return $event->type === EventType::GenerationCreate
                && $event->body instanceof GenerationBody
                && $event->body->id === 'gen-1'
                && $event->body->model === 'gpt-4';
        }));

    new LangfuseGeneration(
        body: new GenerationBody(id: 'gen-1', traceId: 'trace-1', model: 'gpt-4'),
        batcher: $batcher,
    );
});

it('exposes id and trace id', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')->once();

    $gen = new LangfuseGeneration(
        body: new GenerationBody(id: 'gen-1', traceId: 'trace-1'),
        batcher: $batcher,
    );

    expect($gen->getId())->toBe('gen-1')
        ->and($gen->getTraceId())->toBe('trace-1');
});

it('sends generation-update event on end', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')
        ->twice() // create + update
        ->with(Mockery::on(function (IngestionEvent $event) {
            if ($event->type === EventType::GenerationUpdate) {
                return $event->body instanceof GenerationBody
                    && $event->body->id === 'gen-1'
                    && $event->body->output === 'Hello world!';
            }

            return true;
        }));

    $gen = new LangfuseGeneration(
        body: new GenerationBody(id: 'gen-1', traceId: 'trace-1'),
        batcher: $batcher,
    );

    $gen->end(output: 'Hello world!');
});

it('sends generation-update with usage on end', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')
        ->twice()
        ->with(Mockery::on(function (IngestionEvent $event) {
            if ($event->type === EventType::GenerationUpdate) {
                return $event->body instanceof GenerationBody
                    && $event->body->usage instanceof Usage
                    && $event->body->usage->input === 50
                    && $event->body->usage->output === 100;
            }

            return true;
        }));

    $gen = new LangfuseGeneration(
        body: new GenerationBody(id: 'gen-1', traceId: 'trace-1'),
        batcher: $batcher,
    );

    $gen->end(
        output: 'result',
        usage: new Usage(input: 50, output: 100, total: 150),
    );
});

it('sends generation-update with level on end', function () {
    $batcher = Mockery::mock(EventBatcherInterface::class);
    $batcher->shouldReceive('enqueue')
        ->twice()
        ->with(Mockery::on(function (IngestionEvent $event) {
            if ($event->type === EventType::GenerationUpdate) {
                return $event->body instanceof GenerationBody
                    && $event->body->level === ObservationLevel::ERROR
                    && $event->body->statusMessage === 'Rate limited';
            }

            return true;
        }));

    $gen = new LangfuseGeneration(
        body: new GenerationBody(id: 'gen-1', traceId: 'trace-1'),
        batcher: $batcher,
    );

    $gen->end(
        level: ObservationLevel::ERROR,
        statusMessage: 'Rate limited',
    );
});
