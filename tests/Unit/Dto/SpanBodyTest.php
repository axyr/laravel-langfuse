<?php

declare(strict_types=1);

use Langfuse\Dto\SpanBody;
use Langfuse\Enums\ObservationLevel;

it('can be constructed with required fields', function () {
    $span = new SpanBody(id: 'span-1');

    expect($span->id)->toBe('span-1')
        ->and($span->traceId)->toBeNull()
        ->and($span->endTime)->toBeNull();
});

it('can be constructed with all fields', function () {
    $span = new SpanBody(
        id: 'span-1',
        traceId: 'trace-1',
        name: 'test-span',
        startTime: '2024-01-01T00:00:00Z',
        endTime: '2024-01-01T00:00:01Z',
        input: 'input data',
        output: 'output data',
        metadata: ['key' => 'value'],
        level: ObservationLevel::DEBUG,
        statusMessage: 'OK',
        parentObservationId: 'span-0',
        version: '2',
    );

    expect($span->endTime)->toBe('2024-01-01T00:00:01Z')
        ->and($span->parentObservationId)->toBe('span-0')
        ->and($span->level)->toBe(ObservationLevel::DEBUG);
});

it('creates new instance with trace id via withTraceId', function () {
    $span = new SpanBody(id: 'span-1', name: 'test', endTime: '2024-01-01T00:00:01Z');
    $withTrace = $span->withTraceId('trace-99');

    expect($withTrace->traceId)->toBe('trace-99')
        ->and($withTrace->id)->toBe('span-1')
        ->and($withTrace->name)->toBe('test')
        ->and($withTrace->endTime)->toBe('2024-01-01T00:00:01Z')
        ->and($span->traceId)->toBeNull();
});

it('serializes to array with camelCase keys excluding nulls', function () {
    $span = new SpanBody(
        id: 'span-1',
        traceId: 'trace-1',
        startTime: '2024-01-01T00:00:00Z',
        endTime: '2024-01-01T00:00:01Z',
    );

    $array = $span->toArray();

    expect($array)->toBe([
        'id' => 'span-1',
        'traceId' => 'trace-1',
        'startTime' => '2024-01-01T00:00:00Z',
        'endTime' => '2024-01-01T00:00:01Z',
    ]);
});

it('implements SerializableInterface', function () {
    $span = new SpanBody(id: 'span-1');

    expect($span)->toBeInstanceOf(\Langfuse\Contracts\SerializableInterface::class);
});
