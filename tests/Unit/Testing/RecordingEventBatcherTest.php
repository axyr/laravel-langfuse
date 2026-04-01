<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\IngestionEvent;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Enums\EventType;
use Axyr\Langfuse\Testing\RecordingEventBatcher;

it('records enqueued events', function () {
    $batcher = new RecordingEventBatcher();
    $event = new IngestionEvent(
        id: '1',
        type: EventType::TraceCreate,
        timestamp: '2024-01-01T00:00:00Z',
        body: new TraceBody(id: 'trace-1'),
    );

    $batcher->enqueue($event);

    expect($batcher->events())->toHaveCount(1)
        ->and($batcher->events()[0])->toBe($event);
});

it('counts events', function () {
    $batcher = new RecordingEventBatcher();

    expect($batcher->count())->toBe(0);

    $batcher->enqueue(new IngestionEvent(
        id: '1',
        type: EventType::TraceCreate,
        timestamp: '2024-01-01T00:00:00Z',
        body: new TraceBody(id: 'trace-1'),
    ));

    expect($batcher->count())->toBe(1);
});

it('filters events by type', function () {
    $batcher = new RecordingEventBatcher();

    $batcher->enqueue(new IngestionEvent(
        id: '1',
        type: EventType::TraceCreate,
        timestamp: '2024-01-01T00:00:00Z',
        body: new TraceBody(id: 'trace-1'),
    ));

    $batcher->enqueue(new IngestionEvent(
        id: '2',
        type: EventType::ScoreCreate,
        timestamp: '2024-01-01T00:00:00Z',
        body: new \Axyr\Langfuse\Dto\ScoreBody(id: 'score-1', name: 'test'),
    ));

    expect($batcher->eventsOfType('trace-create'))->toHaveCount(1)
        ->and($batcher->eventsOfType('score-create'))->toHaveCount(1)
        ->and($batcher->eventsOfType('span-create'))->toHaveCount(0);
});

it('resets events', function () {
    $batcher = new RecordingEventBatcher();

    $batcher->enqueue(new IngestionEvent(
        id: '1',
        type: EventType::TraceCreate,
        timestamp: '2024-01-01T00:00:00Z',
        body: new TraceBody(id: 'trace-1'),
    ));

    $batcher->reset();

    expect($batcher->events())->toBeEmpty()
        ->and($batcher->count())->toBe(0);
});

it('flush is a no-op', function () {
    $batcher = new RecordingEventBatcher();

    $batcher->enqueue(new IngestionEvent(
        id: '1',
        type: EventType::TraceCreate,
        timestamp: '2024-01-01T00:00:00Z',
        body: new TraceBody(id: 'trace-1'),
    ));

    $batcher->flush();

    // Events should still be there after flush
    expect($batcher->events())->toHaveCount(1);
});
