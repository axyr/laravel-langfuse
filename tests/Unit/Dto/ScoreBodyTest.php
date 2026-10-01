<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\IdGenerator;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Enums\ScoreDataType;

const SCORE_TRACE_ID = '44444444444444444444444444444444';
const SCORE_OBSERVATION_ID = '5555555555555555';

it('auto-generates a uuid id when not provided', function () {
    $score = new ScoreBody(name: 'accuracy', traceId: SCORE_TRACE_ID);

    expect($score->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('keeps a free-form score id, which is not an otlp id', function () {
    expect((new ScoreBody(name: 'accuracy', id: 'score-1'))->id)->toBe('score-1');
});

it('normalises the trace id and the observation id', function () {
    $score = new ScoreBody(name: 'accuracy', traceId: 'trace-1', observationId: 'gen-1');

    expect($score->traceId)->toBe(IdGenerator::traceIdFromSeed('trace-1'))
        ->and($score->observationId)->toBe(IdGenerator::spanIdFromSeed('gen-1'));
});

it('can be constructed with all fields', function () {
    $score = new ScoreBody(
        name: 'accuracy',
        id: 'score-1',
        traceId: SCORE_TRACE_ID,
        value: 0.95,
        dataType: ScoreDataType::NUMERIC,
        observationId: SCORE_OBSERVATION_ID,
        comment: 'Good result',
        configId: 'config-1',
        sessionId: 'session-1',
        environment: 'production',
        datasetRunId: 'experiment-1',
        metadata: ['reviewer' => 'ci'],
        queueId: 'queue-1',
    );

    expect($score->value)->toBe(0.95)
        ->and($score->dataType)->toBe(ScoreDataType::NUMERIC)
        ->and($score->observationId)->toBe(SCORE_OBSERVATION_ID)
        ->and($score->comment)->toBe('Good result')
        ->and($score->configId)->toBe('config-1')
        ->and($score->sessionId)->toBe('session-1')
        ->and($score->environment)->toBe('production')
        ->and($score->datasetRunId)->toBe('experiment-1')
        ->and($score->metadata)->toBe(['reviewer' => 'ci'])
        ->and($score->queueId)->toBe('queue-1');
});

it('serializes to array with camelCase keys', function () {
    $score = new ScoreBody(
        name: 'accuracy',
        id: 'score-1',
        traceId: SCORE_TRACE_ID,
        value: 0.95,
        dataType: ScoreDataType::NUMERIC,
        observationId: SCORE_OBSERVATION_ID,
    );

    $array = $score->toArray();

    expect($array)->toHaveKeys(['id', 'traceId', 'name', 'value', 'dataType', 'observationId'])
        ->and($array['traceId'])->toBe(SCORE_TRACE_ID)
        ->and($array['dataType'])->toBe('NUMERIC')
        ->and($array['observationId'])->toBe(SCORE_OBSERVATION_ID);
});

it('emits the experiment, metadata and queue fields when set', function () {
    $score = new ScoreBody(
        name: 'accuracy',
        id: 'score-1',
        datasetRunId: 'experiment-1',
        metadata: ['reviewer' => 'ci'],
        queueId: 'queue-1',
    );

    $array = $score->toArray();

    expect($array['datasetRunId'])->toBe('experiment-1')
        ->and($array['metadata'])->toBe(['reviewer' => 'ci'])
        ->and($array['queueId'])->toBe('queue-1');
});

it('puts a string value in value, since v4 has no stringValue on the wire', function () {
    $score = new ScoreBody(
        name: 'sentiment',
        id: 'score-1',
        value: 'positive',
        dataType: ScoreDataType::CATEGORICAL,
    );

    expect($score->toArray()['value'])->toBe('positive')
        ->and($score->toArray())->not->toHaveKey('stringValue');
});

it('maps a legacy stringValue into value', function () {
    $score = new ScoreBody(
        name: 'sentiment',
        id: 'score-1',
        stringValue: 'positive',
        dataType: ScoreDataType::CATEGORICAL,
    );

    expect($score->stringValue)->toBe('positive')
        ->and($score->toArray()['value'])->toBe('positive')
        ->and($score->toArray())->not->toHaveKey('stringValue');
});

it('writes a boolean value as 1 or 0', function () {
    $yes = new ScoreBody(name: 'is_correct', id: 's1', value: true, dataType: ScoreDataType::BOOLEAN);
    $no = new ScoreBody(name: 'is_correct', id: 's2', value: false, dataType: ScoreDataType::BOOLEAN);

    expect($yes->toArray()['value'])->toBe(1)
        ->and($no->toArray()['value'])->toBe(0);
});

it('excludes null values from serialization', function () {
    $score = new ScoreBody(name: 'accuracy', id: 'score-1', traceId: SCORE_TRACE_ID);

    $array = $score->toArray();

    expect($array)->toBe(['id' => 'score-1', 'traceId' => SCORE_TRACE_ID, 'name' => 'accuracy'])
        ->and($array)->not->toHaveKey('value')
        ->and($array)->not->toHaveKey('dataType')
        ->and($array)->not->toHaveKey('sessionId')
        ->and($array)->not->toHaveKey('environment');
});

it('serializes enum data type as string value', function () {
    $score = new ScoreBody(
        name: 'is_correct',
        id: 'score-1',
        traceId: SCORE_TRACE_ID,
        dataType: ScoreDataType::BOOLEAN,
    );

    expect($score->toArray()['dataType'])->toBe('BOOLEAN');
});

it('supports the v4 text and correction data types', function () {
    $text = new ScoreBody(name: 'notes', id: 's1', value: 'looks good', dataType: ScoreDataType::TEXT);
    $correction = new ScoreBody(name: 'fix', id: 's2', value: 'the answer', dataType: ScoreDataType::CORRECTION);

    expect($text->toArray()['dataType'])->toBe('TEXT')
        ->and($text->toArray()['value'])->toBe('looks good')
        ->and($correction->toArray()['dataType'])->toBe('CORRECTION')
        ->and($correction->toArray()['value'])->toBe('the answer');
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
    expect(new ScoreBody(name: 'test', id: 'score-1'))
        ->toBeInstanceOf(\Axyr\Langfuse\Contracts\SerializableInterface::class);
});

it('creates new instance with trace id via withTraceId', function () {
    $score = new ScoreBody(name: 'accuracy', id: 'score-1', value: 0.95);
    $withTrace = $score->withTraceId(SCORE_TRACE_ID);

    expect($withTrace->traceId)->toBe(SCORE_TRACE_ID)
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
        datasetRunId: 'experiment-1',
        metadata: ['reviewer' => 'ci'],
        queueId: 'queue-1',
    );

    $withTrace = $score->withTraceId(SCORE_TRACE_ID);

    expect($withTrace->sessionId)->toBe('session-1')
        ->and($withTrace->environment)->toBe('production')
        ->and($withTrace->datasetRunId)->toBe('experiment-1')
        ->and($withTrace->metadata)->toBe(['reviewer' => 'ci'])
        ->and($withTrace->queueId)->toBe('queue-1');
});

it('allows construction without traceId', function () {
    $score = new ScoreBody(name: 'accuracy', id: 'score-1');

    expect($score->traceId)->toBeNull()
        ->and($score->toArray())->not->toHaveKey('traceId');
});

it('stamps an environment only when none is set', function () {
    $body = new ScoreBody(name: 'accuracy', id: 'score-1', traceId: SCORE_TRACE_ID);

    $stamped = $body->withEnvironment('production');

    expect($stamped->environment)->toBe('production')
        ->and($stamped->traceId)->toBe(SCORE_TRACE_ID)
        ->and($body->withEnvironment(null))->toBe($body)
        ->and($stamped->withEnvironment('staging'))->toBe($stamped);
});
