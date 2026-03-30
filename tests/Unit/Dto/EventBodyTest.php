<?php

declare(strict_types=1);

use Langfuse\Dto\EventBody;
use Langfuse\Enums\ObservationLevel;

it('can be constructed with required fields', function () {
    $event = new EventBody(id: 'event-1');

    expect($event->id)->toBe('event-1')
        ->and($event->traceId)->toBeNull()
        ->and($event->name)->toBeNull();
});

it('can be constructed with all fields', function () {
    $event = new EventBody(
        id: 'event-1',
        traceId: 'trace-1',
        name: 'test-event',
        startTime: '2024-01-01T00:00:00Z',
        input: ['key' => 'value'],
        output: 'result',
        metadata: ['env' => 'test'],
        level: ObservationLevel::WARNING,
        statusMessage: 'All good',
        parentObservationId: 'span-1',
        version: '1',
    );

    expect($event->traceId)->toBe('trace-1')
        ->and($event->name)->toBe('test-event')
        ->and($event->level)->toBe(ObservationLevel::WARNING)
        ->and($event->parentObservationId)->toBe('span-1');
});

it('creates new instance with trace id via withTraceId', function () {
    $event = new EventBody(id: 'event-1', name: 'test');
    $withTrace = $event->withTraceId('trace-99');

    expect($withTrace->traceId)->toBe('trace-99')
        ->and($withTrace->id)->toBe('event-1')
        ->and($withTrace->name)->toBe('test')
        ->and($event->traceId)->toBeNull();
});

it('serializes to array with camelCase keys excluding nulls', function () {
    $event = new EventBody(
        id: 'event-1',
        traceId: 'trace-1',
        name: 'test',
        level: ObservationLevel::ERROR,
    );

    $array = $event->toArray();

    expect($array)->toBe([
        'id' => 'event-1',
        'traceId' => 'trace-1',
        'name' => 'test',
        'level' => 'ERROR',
    ]);
});

it('implements SerializableInterface', function () {
    $event = new EventBody(id: 'event-1');

    expect($event)->toBeInstanceOf(\Langfuse\Contracts\SerializableInterface::class);
});
