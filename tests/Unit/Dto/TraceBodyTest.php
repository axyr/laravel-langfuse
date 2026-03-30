<?php

declare(strict_types=1);

use Langfuse\Dto\TraceBody;

it('can be constructed with required fields', function () {
    $trace = new TraceBody(id: 'trace-1');

    expect($trace->id)->toBe('trace-1')
        ->and($trace->name)->toBeNull()
        ->and($trace->userId)->toBeNull();
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
        ->and($trace->public)->toBeTrue();
});

it('serializes to array with camelCase keys', function () {
    $trace = new TraceBody(
        id: 'trace-1',
        name: 'test',
        userId: 'user-1',
        sessionId: 'session-1',
    );

    $array = $trace->toArray();

    expect($array)->toHaveKeys(['id', 'name', 'userId', 'sessionId'])
        ->and($array['userId'])->toBe('user-1')
        ->and($array['sessionId'])->toBe('session-1');
});

it('excludes null values from serialization', function () {
    $trace = new TraceBody(id: 'trace-1', name: 'test');

    $array = $trace->toArray();

    expect($array)->toBe(['id' => 'trace-1', 'name' => 'test'])
        ->and($array)->not->toHaveKey('userId')
        ->and($array)->not->toHaveKey('metadata');
});

it('implements SerializableInterface', function () {
    $trace = new TraceBody(id: 'trace-1');

    expect($trace)->toBeInstanceOf(\Langfuse\Contracts\SerializableInterface::class);
});
