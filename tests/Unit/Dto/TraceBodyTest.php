<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\ExperimentContext;
use Axyr\Langfuse\Dto\ExperimentItemContext;
use Axyr\Langfuse\Dto\IdGenerator;
use Axyr\Langfuse\Dto\TraceBody;

it('auto-generates a 32 hex trace id when not provided', function () {
    $trace = new TraceBody();

    expect($trace->id)->toMatch('/^[0-9a-f]{32}$/');
});

it('normalises a custom id into a trace id', function () {
    $trace = new TraceBody(id: 'trace-1');

    expect($trace->id)->toBe(IdGenerator::traceIdFromSeed('trace-1'))
        ->and($trace->id)->toMatch('/^[0-9a-f]{32}$/');
});

it('keeps a custom id that already is a trace id', function () {
    $id = str_repeat('ab', 16);

    expect((new TraceBody(id: $id))->id)->toBe($id);
});

it('carries the normalised id into the serialised body', function () {
    $trace = new TraceBody(id: 'trace-1');

    expect($trace->toArray()['id'])->toBe(IdGenerator::traceIdFromSeed('trace-1'));
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

    expect($trace->id)->toBe(IdGenerator::traceIdFromSeed('trace-1'))
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
    $trace = new TraceBody(name: 'test');

    $array = $trace->toArray();

    expect($array)->toHaveKey('id')
        ->and($array)->toHaveKey('name')
        ->and($array)->toHaveKey('timestamp')
        ->and($array)->not->toHaveKey('userId')
        ->and($array)->not->toHaveKey('metadata')
        ->and($array)->not->toHaveKey('environment');
});

it('includes environment in serialization', function () {
    $trace = new TraceBody(environment: 'staging');

    expect($trace->toArray()['environment'])->toBe('staging');
});

it('implements SerializableInterface', function () {
    expect(new TraceBody())->toBeInstanceOf(\Axyr\Langfuse\Contracts\SerializableInterface::class);
});

it('stamps an environment only when none is set', function () {
    $body = new TraceBody(id: 'trace-1', timestamp: '2024-01-01T00:00:00Z');

    $stamped = $body->withEnvironment('production');

    expect($stamped->environment)->toBe('production')
        ->and($stamped->id)->toBe($body->id)
        ->and($stamped->timestamp)->toBe('2024-01-01T00:00:00Z')
        ->and($body->withEnvironment(null))->toBe($body)
        ->and($stamped->withEnvironment('staging'))->toBe($stamped);
});

it('fills userId and sessionId only when unset', function () {
    $body = new TraceBody(id: 'trace-1', environment: 'production');

    $filled = $body->withUserId('42')->withSessionId('session-1');

    expect($filled->userId)->toBe('42')
        ->and($filled->sessionId)->toBe('session-1')
        ->and($filled->environment)->toBe('production')
        ->and($filled->id)->toBe($body->id)
        ->and($body->withUserId(null))->toBe($body)
        ->and($body->withSessionId(null))->toBe($body)
        ->and($filled->withUserId('other'))->toBe($filled)
        ->and($filled->withSessionId('other'))->toBe($filled);
});

it('merges an update over the current body, keeping id and timestamp', function () {
    $body = new TraceBody(
        id: 'trace-1',
        name: 'original',
        input: 'question',
        metadata: ['source' => 'test'],
        timestamp: '2024-01-01T00:00:00Z',
        environment: 'production',
    );

    $merged = $body->mergedWith(new TraceBody(
        userId: 'user-1',
        output: 'answer',
        metadata: ['error' => 'boom'],
    ));

    expect($merged->id)->toBe($body->id)
        ->and($merged->timestamp)->toBe('2024-01-01T00:00:00Z')
        ->and($merged->name)->toBe('original')
        ->and($merged->input)->toBe('question')
        ->and($merged->userId)->toBe('user-1')
        ->and($merged->output)->toBe('answer')
        ->and($merged->environment)->toBe('production')
        ->and($merged->metadata)->toBe(['source' => 'test', 'error' => 'boom']);
});

it('lets the update override a field that is already set', function () {
    $body = new TraceBody(name: 'original', output: 'first');

    $merged = $body->mergedWith(new TraceBody(name: 'renamed', output: 'second'));

    expect($merged->name)->toBe('renamed')
        ->and($merged->output)->toBe('second');
});

it('links a trace to an experiment item', function () {
    $body = (new TraceBody(name: 'run'))->forExperimentItem(
        ExperimentContext::named('nightly-eval', datasetId: 'ds-1'),
        new ExperimentItemContext(itemId: 'item-1', expectedOutput: 'yes'),
    );

    expect($body->name)->toBe('run')
        ->and($body->experiment?->id)->toBe('nightly-eval')
        ->and($body->experiment?->name)->toBe('nightly-eval')
        ->and($body->experiment?->datasetId)->toBe('ds-1')
        ->and($body->experimentItem?->itemId)->toBe('item-1')
        ->and($body->experimentItem?->expectedOutput)->toBe('yes');
});

it('stamps a release only when none is set', function () {
    $body = new TraceBody(id: 'trace-1', name: 'test', timestamp: '2024-01-01T00:00:00Z');

    $stamped = $body->withRelease('1.2.3');

    expect($stamped->release)->toBe('1.2.3')
        ->and($stamped->name)->toBe('test')
        ->and($stamped->id)->toBe($body->id)
        ->and($stamped->timestamp)->toBe('2024-01-01T00:00:00Z')
        ->and($body->withRelease(null))->toBe($body)
        ->and($stamped->withRelease('9.9.9'))->toBe($stamped);
});
