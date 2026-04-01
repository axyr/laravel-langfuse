<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\EventBody;
use Axyr\Langfuse\Enums\ObservationLevel;

it('auto-generates id when not provided', function () {
    $event = new EventBody();

    expect($event->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('uses provided id when given', function () {
    $event = new EventBody(id: 'event-1');

    expect($event->id)->toBe('event-1');
});

it('auto-generates startTime when not provided', function () {
    $event = new EventBody();

    expect($event->startTime)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/');
});

it('uses provided startTime when given', function () {
    $event = new EventBody(startTime: '2024-01-01T00:00:00Z');

    expect($event->startTime)->toBe('2024-01-01T00:00:00Z');
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
        environment: 'production',
    );

    expect($event->traceId)->toBe('trace-1')
        ->and($event->name)->toBe('test-event')
        ->and($event->level)->toBe(ObservationLevel::WARNING)
        ->and($event->parentObservationId)->toBe('span-1')
        ->and($event->environment)->toBe('production');
});

it('creates new instance with trace id via withTraceId', function () {
    $event = new EventBody(id: 'event-1', name: 'test', startTime: '2024-01-01T00:00:00Z');
    $withTrace = $event->withTraceId('trace-99');

    expect($withTrace->traceId)->toBe('trace-99')
        ->and($withTrace->id)->toBe('event-1')
        ->and($withTrace->name)->toBe('test')
        ->and($withTrace->startTime)->toBe('2024-01-01T00:00:00Z')
        ->and($event->traceId)->toBeNull();
});

it('preserves environment through withContext', function () {
    $event = new EventBody(id: 'event-1', environment: 'staging');
    $withContext = $event->withContext('trace-1', 'parent-1');

    expect($withContext->environment)->toBe('staging');
});

it('serializes to array with camelCase keys excluding nulls', function () {
    $event = new EventBody(
        id: 'event-1',
        traceId: 'trace-1',
        name: 'test',
        startTime: '2024-01-01T00:00:00Z',
        level: ObservationLevel::ERROR,
    );

    $array = $event->toArray();

    expect($array)->toBe([
        'id' => 'event-1',
        'traceId' => 'trace-1',
        'name' => 'test',
        'startTime' => '2024-01-01T00:00:00Z',
        'level' => 'ERROR',
    ]);
});

it('includes environment in serialization', function () {
    $event = new EventBody(id: 'event-1', environment: 'production');

    expect($event->toArray()['environment'])->toBe('production');
});

it('implements SerializableInterface', function () {
    $event = new EventBody(id: 'event-1');

    expect($event)->toBeInstanceOf(\Axyr\Langfuse\Contracts\SerializableInterface::class);
});
