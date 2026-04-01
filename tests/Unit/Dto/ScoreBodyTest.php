<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Enums\ScoreDataType;

it('auto-generates id when not provided', function () {
    $score = new ScoreBody(name: 'accuracy', traceId: 'trace-1');

    expect($score->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('uses provided id when given', function () {
    $score = new ScoreBody(name: 'accuracy', id: 'score-1');

    expect($score->id)->toBe('score-1');
});

it('can be constructed with required fields', function () {
    $score = new ScoreBody(
        name: 'accuracy',
        id: 'score-1',
        traceId: 'trace-1',
    );

    expect($score->id)->toBe('score-1')
        ->and($score->traceId)->toBe('trace-1')
        ->and($score->name)->toBe('accuracy')
        ->and($score->value)->toBeNull();
});

it('can be constructed with all fields', function () {
    $score = new ScoreBody(
        name: 'accuracy',
        id: 'score-1',
        traceId: 'trace-1',
        value: 0.95,
        stringValue: 'high',
        dataType: ScoreDataType::NUMERIC,
        observationId: 'obs-1',
        comment: 'Good result',
        configId: 'config-1',
        sessionId: 'session-1',
        environment: 'production',
    );

    expect($score->value)->toBe(0.95)
        ->and($score->stringValue)->toBe('high')
        ->and($score->dataType)->toBe(ScoreDataType::NUMERIC)
        ->and($score->observationId)->toBe('obs-1')
        ->and($score->comment)->toBe('Good result')
        ->and($score->configId)->toBe('config-1')
        ->and($score->sessionId)->toBe('session-1')
        ->and($score->environment)->toBe('production');
});

it('serializes to array with camelCase keys', function () {
    $score = new ScoreBody(
        name: 'accuracy',
        id: 'score-1',
        traceId: 'trace-1',
        value: 0.95,
        dataType: ScoreDataType::NUMERIC,
        observationId: 'obs-1',
    );

    $array = $score->toArray();

    expect($array)->toHaveKeys(['id', 'traceId', 'name', 'value', 'dataType', 'observationId'])
        ->and($array['traceId'])->toBe('trace-1')
        ->and($array['dataType'])->toBe('NUMERIC')
        ->and($array['observationId'])->toBe('obs-1');
});

it('excludes null values from serialization', function () {
    $score = new ScoreBody(
        name: 'accuracy',
        id: 'score-1',
        traceId: 'trace-1',
    );

    $array = $score->toArray();

    expect($array)->toBe(['id' => 'score-1', 'traceId' => 'trace-1', 'name' => 'accuracy'])
        ->and($array)->not->toHaveKey('value')
        ->and($array)->not->toHaveKey('dataType')
        ->and($array)->not->toHaveKey('sessionId')
        ->and($array)->not->toHaveKey('environment');
});

it('serializes enum data type as string value', function () {
    $score = new ScoreBody(
        name: 'is_correct',
        id: 'score-1',
        traceId: 'trace-1',
        dataType: ScoreDataType::BOOLEAN,
    );

    expect($score->toArray()['dataType'])->toBe('BOOLEAN');
});

it('includes new fields in serialization', function () {
    $score = new ScoreBody(
        name: 'accuracy',
        id: 'score-1',
        sessionId: 'session-1',
        environment: 'staging',
    );

    $array = $score->toArray();

    expect($array['sessionId'])->toBe('session-1')
        ->and($array['environment'])->toBe('staging');
});

it('implements SerializableInterface', function () {
    $score = new ScoreBody(name: 'test', id: 'score-1', traceId: 'trace-1');

    expect($score)->toBeInstanceOf(\Axyr\Langfuse\Contracts\SerializableInterface::class);
});

it('creates new instance with trace id via withTraceId', function () {
    $score = new ScoreBody(name: 'accuracy', id: 'score-1', value: 0.95);
    $withTrace = $score->withTraceId('trace-99');

    expect($withTrace->traceId)->toBe('trace-99')
        ->and($withTrace->id)->toBe('score-1')
        ->and($withTrace->name)->toBe('accuracy')
        ->and($withTrace->value)->toBe(0.95)
        ->and($score->traceId)->toBeNull();
});

it('preserves new fields through withTraceId', function () {
    $score = new ScoreBody(
        name: 'accuracy',
        id: 'score-1',
        sessionId: 'session-1',
        environment: 'production',
    );
    $withTrace = $score->withTraceId('trace-99');

    expect($withTrace->sessionId)->toBe('session-1')
        ->and($withTrace->environment)->toBe('production');
});

it('allows construction without traceId', function () {
    $score = new ScoreBody(name: 'accuracy', id: 'score-1');

    expect($score->traceId)->toBeNull();
});
