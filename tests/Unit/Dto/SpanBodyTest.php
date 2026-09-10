<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\IdGenerator;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Enums\ObservationLevel;
use Axyr\Langfuse\Enums\ObservationType;

const SPAN_TRACE_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const SPAN_ID = 'bbbbbbbbbbbbbbbb';
const SPAN_PARENT_ID = 'cccccccccccccccc';

it('auto-generates a 16 hex observation id when not provided', function () {
    expect((new SpanBody())->id)->toMatch('/^[0-9a-f]{16}$/');
});

it('normalises a custom id into an observation id', function () {
    $span = new SpanBody(id: 'span-1');

    expect($span->id)->toBe(IdGenerator::spanIdFromSeed('span-1'))
        ->and($span->toArray()['id'])->toBe(IdGenerator::spanIdFromSeed('span-1'));
});

it('keeps a custom id that already is an observation id', function () {
    expect((new SpanBody(id: SPAN_ID))->id)->toBe(SPAN_ID);
});

it('normalises the trace id and the parent observation id', function () {
    $span = new SpanBody(id: SPAN_ID, traceId: 'trace-1', parentObservationId: 'parent-1');

    expect($span->traceId)->toBe(IdGenerator::traceIdFromSeed('trace-1'))
        ->and($span->parentObservationId)->toBe(IdGenerator::spanIdFromSeed('parent-1'));
});

it('can be constructed with all fields', function () {
    $span = new SpanBody(
        id: SPAN_ID,
        traceId: SPAN_TRACE_ID,
        name: 'test-span',
        startTime: '2024-01-01T00:00:00Z',
        endTime: '2024-01-01T00:00:01Z',
        input: 'input data',
        output: 'output data',
        metadata: ['key' => 'value'],
        level: ObservationLevel::DEBUG,
        statusMessage: 'OK',
        parentObservationId: SPAN_PARENT_ID,
        version: '2',
        environment: 'staging',
        type: ObservationType::Tool,
    );

    expect($span->endTime)->toBe('2024-01-01T00:00:01Z')
        ->and($span->parentObservationId)->toBe(SPAN_PARENT_ID)
        ->and($span->level)->toBe(ObservationLevel::DEBUG)
        ->and($span->environment)->toBe('staging')
        ->and($span->type)->toBe(ObservationType::Tool);
});

it('creates new instance with trace id via withTraceId', function () {
    $span = new SpanBody(id: SPAN_ID, name: 'test', endTime: '2024-01-01T00:00:01Z');
    $withTrace = $span->withTraceId(SPAN_TRACE_ID);

    expect($withTrace->traceId)->toBe(SPAN_TRACE_ID)
        ->and($withTrace->id)->toBe(SPAN_ID)
        ->and($withTrace->name)->toBe('test')
        ->and($withTrace->endTime)->toBe('2024-01-01T00:00:01Z')
        ->and($span->traceId)->toBeNull();
});

it('preserves environment and type through withContext', function () {
    $span = new SpanBody(id: SPAN_ID, environment: 'production', type: ObservationType::Retriever);
    $withContext = $span->withContext(SPAN_TRACE_ID, SPAN_PARENT_ID);

    expect($withContext->environment)->toBe('production')
        ->and($withContext->traceId)->toBe(SPAN_TRACE_ID)
        ->and($withContext->parentObservationId)->toBe(SPAN_PARENT_ID)
        ->and($withContext->type)->toBe(ObservationType::Retriever);
});

it('stamps a start time only when none is set', function () {
    $span = new SpanBody(id: SPAN_ID);

    $started = $span->startedAt('2024-01-01T00:00:00Z');

    expect($started->startTime)->toBe('2024-01-01T00:00:00Z')
        ->and($started->startedAt('2024-06-01T00:00:00Z'))->toBe($started);
});

it('builds the finished observation with completed', function () {
    $span = new SpanBody(id: SPAN_ID, traceId: SPAN_TRACE_ID, name: 'work', input: 'in', startTime: '2024-01-01T00:00:00Z');

    $done = $span->completed(
        endTime: '2024-01-01T00:00:05Z',
        output: 'out',
        statusMessage: 'failed',
        level: ObservationLevel::ERROR,
    );

    expect($done->id)->toBe(SPAN_ID)
        ->and($done->traceId)->toBe(SPAN_TRACE_ID)
        ->and($done->name)->toBe('work')
        ->and($done->input)->toBe('in')
        ->and($done->startTime)->toBe('2024-01-01T00:00:00Z')
        ->and($done->endTime)->toBe('2024-01-01T00:00:05Z')
        ->and($done->output)->toBe('out')
        ->and($done->statusMessage)->toBe('failed')
        ->and($done->level)->toBe(ObservationLevel::ERROR);
});

it('keeps the values it already had when completed passes nulls', function () {
    $span = new SpanBody(id: SPAN_ID, output: 'kept', level: ObservationLevel::WARNING);

    $done = $span->completed(endTime: '2024-01-01T00:00:05Z');

    expect($done->output)->toBe('kept')
        ->and($done->level)->toBe(ObservationLevel::WARNING);
});

it('serializes to array with camelCase keys excluding nulls', function () {
    $span = new SpanBody(
        id: SPAN_ID,
        traceId: SPAN_TRACE_ID,
        startTime: '2024-01-01T00:00:00Z',
        endTime: '2024-01-01T00:00:01Z',
    );

    expect($span->toArray())->toBe([
        'id' => SPAN_ID,
        'traceId' => SPAN_TRACE_ID,
        'startTime' => '2024-01-01T00:00:00Z',
        'endTime' => '2024-01-01T00:00:01Z',
    ]);
});

it('includes environment and type in serialization', function () {
    $span = new SpanBody(id: SPAN_ID, environment: 'production', type: ObservationType::Agent);

    expect($span->toArray()['environment'])->toBe('production')
        ->and($span->toArray()['type'])->toBe('agent');
});

it('implements SerializableInterface', function () {
    expect(new SpanBody(id: SPAN_ID))->toBeInstanceOf(\Axyr\Langfuse\Contracts\SerializableInterface::class);
});

it('stamps an environment only when none is set', function () {
    $body = new SpanBody(id: SPAN_ID, traceId: SPAN_TRACE_ID, parentObservationId: SPAN_PARENT_ID);

    $stamped = $body->withEnvironment('production');

    expect($stamped->environment)->toBe('production')
        ->and($stamped->traceId)->toBe(SPAN_TRACE_ID)
        ->and($stamped->parentObservationId)->toBe(SPAN_PARENT_ID)
        ->and($body->withEnvironment(null))->toBe($body)
        ->and($stamped->withEnvironment('staging'))->toBe($stamped);
});
