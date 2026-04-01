<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Enums\ObservationLevel;

it('auto-generates id when not provided', function () {
    $span = new SpanBody();

    expect($span->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('uses provided id when given', function () {
    $span = new SpanBody(id: 'span-1');

    expect($span->id)->toBe('span-1');
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
        environment: 'staging',
    );

    expect($span->endTime)->toBe('2024-01-01T00:00:01Z')
        ->and($span->parentObservationId)->toBe('span-0')
        ->and($span->level)->toBe(ObservationLevel::DEBUG)
        ->and($span->environment)->toBe('staging');
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

it('preserves environment through withContext', function () {
    $span = new SpanBody(id: 'span-1', environment: 'production');
    $withContext = $span->withContext('trace-1', 'parent-1');

    expect($withContext->environment)->toBe('production')
        ->and($withContext->traceId)->toBe('trace-1')
        ->and($withContext->parentObservationId)->toBe('parent-1');
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

it('includes environment in serialization', function () {
    $span = new SpanBody(id: 'span-1', environment: 'production');

    expect($span->toArray()['environment'])->toBe('production');
});

it('implements SerializableInterface', function () {
    $span = new SpanBody(id: 'span-1');

    expect($span)->toBeInstanceOf(\Axyr\Langfuse\Contracts\SerializableInterface::class);
});
