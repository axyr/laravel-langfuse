<?php

declare(strict_types=1);

use Langfuse\Dto\ScoreBody;
use Langfuse\Enums\ScoreDataType;

it('can be constructed with required fields', function () {
    $score = new ScoreBody(
        id: 'score-1',
        traceId: 'trace-1',
        name: 'accuracy',
    );

    expect($score->id)->toBe('score-1')
        ->and($score->traceId)->toBe('trace-1')
        ->and($score->name)->toBe('accuracy')
        ->and($score->value)->toBeNull();
});

it('can be constructed with all fields', function () {
    $score = new ScoreBody(
        id: 'score-1',
        traceId: 'trace-1',
        name: 'accuracy',
        value: 0.95,
        stringValue: 'high',
        dataType: ScoreDataType::NUMERIC,
        observationId: 'obs-1',
        comment: 'Good result',
        configId: 'config-1',
    );

    expect($score->value)->toBe(0.95)
        ->and($score->stringValue)->toBe('high')
        ->and($score->dataType)->toBe(ScoreDataType::NUMERIC)
        ->and($score->observationId)->toBe('obs-1')
        ->and($score->comment)->toBe('Good result')
        ->and($score->configId)->toBe('config-1');
});

it('serializes to array with camelCase keys', function () {
    $score = new ScoreBody(
        id: 'score-1',
        traceId: 'trace-1',
        name: 'accuracy',
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
        id: 'score-1',
        traceId: 'trace-1',
        name: 'accuracy',
    );

    $array = $score->toArray();

    expect($array)->toBe(['id' => 'score-1', 'traceId' => 'trace-1', 'name' => 'accuracy'])
        ->and($array)->not->toHaveKey('value')
        ->and($array)->not->toHaveKey('dataType');
});

it('serializes enum data type as string value', function () {
    $score = new ScoreBody(
        id: 'score-1',
        traceId: 'trace-1',
        name: 'is_correct',
        dataType: ScoreDataType::BOOLEAN,
    );

    expect($score->toArray()['dataType'])->toBe('BOOLEAN');
});

it('implements SerializableInterface', function () {
    $score = new ScoreBody(id: 'score-1', traceId: 'trace-1', name: 'test');

    expect($score)->toBeInstanceOf(\Langfuse\Contracts\SerializableInterface::class);
});

it('creates new instance with trace id via withTraceId', function () {
    $score = new ScoreBody(id: 'score-1', name: 'accuracy', value: 0.95);
    $withTrace = $score->withTraceId('trace-99');

    expect($withTrace->traceId)->toBe('trace-99')
        ->and($withTrace->id)->toBe('score-1')
        ->and($withTrace->name)->toBe('accuracy')
        ->and($withTrace->value)->toBe(0.95)
        ->and($score->traceId)->toBeNull();
});

it('allows construction without traceId', function () {
    $score = new ScoreBody(id: 'score-1', name: 'accuracy');

    expect($score->traceId)->toBeNull();
});
