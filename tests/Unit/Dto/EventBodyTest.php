<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\EventBody;
use Axyr\Langfuse\Dto\IdGenerator;
use Axyr\Langfuse\Enums\ObservationLevel;

const EVENT_TRACE_ID = '11111111111111111111111111111111';
const EVENT_ID = '2222222222222222';
const EVENT_PARENT_ID = '3333333333333333';

it('auto-generates a 16 hex observation id when not provided', function () {
    expect((new EventBody())->id)->toMatch('/^[0-9a-f]{16}$/');
});

it('normalises a custom id into an observation id', function () {
    $event = new EventBody(id: 'event-1');

    expect($event->id)->toBe(IdGenerator::spanIdFromSeed('event-1'))
        ->and($event->toArray()['id'])->toBe(IdGenerator::spanIdFromSeed('event-1'));
});

it('normalises the trace id and the parent observation id', function () {
    $event = new EventBody(id: EVENT_ID, traceId: 'trace-1', parentObservationId: 'parent-1');

    expect($event->traceId)->toBe(IdGenerator::traceIdFromSeed('trace-1'))
        ->and($event->parentObservationId)->toBe(IdGenerator::spanIdFromSeed('parent-1'));
});

it('auto-generates startTime when not provided', function () {
    expect((new EventBody())->startTime)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/');
});

it('uses provided startTime when given', function () {
    expect((new EventBody(startTime: '2024-01-01T00:00:00Z'))->startTime)->toBe('2024-01-01T00:00:00Z');
});

it('can be constructed with all fields', function () {
    $event = new EventBody(
        id: EVENT_ID,
        traceId: EVENT_TRACE_ID,
        name: 'test-event',
        startTime: '2024-01-01T00:00:00Z',
        input: ['key' => 'value'],
        output: 'result',
        metadata: ['env' => 'test'],
        level: ObservationLevel::WARNING,
        statusMessage: 'All good',
        parentObservationId: EVENT_PARENT_ID,
        version: '1',
        environment: 'production',
    );

    expect($event->traceId)->toBe(EVENT_TRACE_ID)
        ->and($event->name)->toBe('test-event')
        ->and($event->level)->toBe(ObservationLevel::WARNING)
        ->and($event->parentObservationId)->toBe(EVENT_PARENT_ID)
        ->and($event->environment)->toBe('production');
});

it('creates new instance with trace id via withTraceId', function () {
    $event = new EventBody(id: EVENT_ID, name: 'test', startTime: '2024-01-01T00:00:00Z');
    $withTrace = $event->withTraceId(EVENT_TRACE_ID);

    expect($withTrace->traceId)->toBe(EVENT_TRACE_ID)
        ->and($withTrace->id)->toBe(EVENT_ID)
        ->and($withTrace->name)->toBe('test')
        ->and($withTrace->startTime)->toBe('2024-01-01T00:00:00Z')
        ->and($event->traceId)->toBeNull();
});

it('preserves environment through withContext', function () {
    $event = new EventBody(id: EVENT_ID, environment: 'staging');

    expect($event->withContext(EVENT_TRACE_ID, EVENT_PARENT_ID)->environment)->toBe('staging');
});

it('serializes to array with camelCase keys excluding nulls', function () {
    $event = new EventBody(
        id: EVENT_ID,
        traceId: EVENT_TRACE_ID,
        name: 'test',
        startTime: '2024-01-01T00:00:00Z',
        level: ObservationLevel::ERROR,
    );

    expect($event->toArray())->toBe([
        'id' => EVENT_ID,
        'traceId' => EVENT_TRACE_ID,
        'name' => 'test',
        'startTime' => '2024-01-01T00:00:00Z',
        'level' => 'ERROR',
    ]);
});

it('includes environment in serialization', function () {
    $event = new EventBody(id: EVENT_ID, environment: 'production');

    expect($event->toArray()['environment'])->toBe('production');
});

it('implements SerializableInterface', function () {
    expect(new EventBody(id: EVENT_ID))->toBeInstanceOf(\Axyr\Langfuse\Contracts\SerializableInterface::class);
});

it('stamps an environment only when none is set', function () {
    $body = new EventBody(id: EVENT_ID, traceId: EVENT_TRACE_ID, parentObservationId: EVENT_PARENT_ID);

    $stamped = $body->withEnvironment('production');

    expect($stamped->environment)->toBe('production')
        ->and($stamped->traceId)->toBe(EVENT_TRACE_ID)
        ->and($stamped->parentObservationId)->toBe(EVENT_PARENT_ID)
        ->and($body->withEnvironment(null))->toBe($body)
        ->and($stamped->withEnvironment('staging'))->toBe($stamped);
});
