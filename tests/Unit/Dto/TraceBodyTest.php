<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\TraceBody;

it('auto-generates id when not provided', function () {
    $trace = new TraceBody();

    expect($trace->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('uses provided id when given', function () {
    $trace = new TraceBody(id: 'trace-1');

    expect($trace->id)->toBe('trace-1');
});

it('auto-generates timestamp when not provided', function () {
    $trace = new TraceBody();

    expect($trace->timestamp)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/');
});

it('uses provided timestamp when given', function () {
    $trace = new TraceBody(timestamp: '2024-01-01T00:00:00.000000Z');

    expect($trace->timestamp)->toBe('2024-01-01T00:00:00.000000Z');
});

it('can be constructed with all fields', function () {
    $trace = new TraceBody(
        id: 'trace-1',
        name: 'test-trace',
        userId: 'user-1',
        sessionId: 'session-1',
        release: 'v1.0.0',
        version: '1',
        input: ['prompt' => 'hello'],
        output: ['response' => 'world'],
        metadata: ['key' => 'value'],
        tags: ['tag1', 'tag2'],
        public: true,
        timestamp: '2024-01-01T00:00:00.000000Z',
        environment: 'production',
    );

    expect($trace->id)->toBe('trace-1')
        ->and($trace->name)->toBe('test-trace')
        ->and($trace->userId)->toBe('user-1')
        ->and($trace->sessionId)->toBe('session-1')
        ->and($trace->release)->toBe('v1.0.0')
        ->and($trace->version)->toBe('1')
        ->and($trace->input)->toBe(['prompt' => 'hello'])
        ->and($trace->output)->toBe(['response' => 'world'])
        ->and($trace->metadata)->toBe(['key' => 'value'])
        ->and($trace->tags)->toBe(['tag1', 'tag2'])
        ->and($trace->public)->toBeTrue()
        ->and($trace->timestamp)->toBe('2024-01-01T00:00:00.000000Z')
        ->and($trace->environment)->toBe('production');
});

it('serializes to array with camelCase keys', function () {
    $trace = new TraceBody(
        id: 'trace-1',
        name: 'test',
        userId: 'user-1',
        sessionId: 'session-1',
        timestamp: '2024-01-01T00:00:00.000000Z',
    );

    $array = $trace->toArray();

    expect($array)->toHaveKeys(['id', 'name', 'userId', 'sessionId', 'timestamp'])
        ->and($array['userId'])->toBe('user-1')
        ->and($array['sessionId'])->toBe('session-1')
        ->and($array['timestamp'])->toBe('2024-01-01T00:00:00.000000Z');
});

it('excludes null values from serialization', function () {
    $trace = new TraceBody(id: 'trace-1', name: 'test');

    $array = $trace->toArray();

    expect($array)->toHaveKey('id')
        ->and($array)->toHaveKey('name')
        ->and($array)->toHaveKey('timestamp')
        ->and($array)->not->toHaveKey('userId')
        ->and($array)->not->toHaveKey('metadata')
        ->and($array)->not->toHaveKey('environment');
});

it('includes environment in serialization', function () {
    $trace = new TraceBody(id: 'trace-1', environment: 'staging');

    expect($trace->toArray()['environment'])->toBe('staging');
});

it('implements SerializableInterface', function () {
    $trace = new TraceBody(id: 'trace-1');

    expect($trace)->toBeInstanceOf(\Axyr\Langfuse\Contracts\SerializableInterface::class);
});
