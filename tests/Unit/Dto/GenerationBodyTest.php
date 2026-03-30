<?php

declare(strict_types=1);

use Langfuse\Dto\GenerationBody;
use Langfuse\Dto\Usage;
use Langfuse\Enums\ObservationLevel;

it('can be constructed with required fields', function () {
    $gen = new GenerationBody(id: 'gen-1');

    expect($gen->id)->toBe('gen-1')
        ->and($gen->model)->toBeNull()
        ->and($gen->usage)->toBeNull();
});

it('can be constructed with all fields', function () {
    $usage = new Usage(input: 100, output: 200, total: 300);
    $gen = new GenerationBody(
        id: 'gen-1',
        traceId: 'trace-1',
        name: 'chat-completion',
        startTime: '2024-01-01T00:00:00Z',
        endTime: '2024-01-01T00:00:01Z',
        completionStartTime: '2024-01-01T00:00:00.500Z',
        input: [['role' => 'user', 'content' => 'Hello']],
        output: ['role' => 'assistant', 'content' => 'Hi!'],
        metadata: ['provider' => 'openai'],
        level: ObservationLevel::DEFAULT,
        statusMessage: 'Success',
        parentObservationId: 'span-1',
        version: '1',
        model: 'gpt-4',
        modelParameters: ['temperature' => 0.7],
        usage: $usage,
    );

    expect($gen->model)->toBe('gpt-4')
        ->and($gen->completionStartTime)->toBe('2024-01-01T00:00:00.500Z')
        ->and($gen->modelParameters)->toBe(['temperature' => 0.7])
        ->and($gen->usage)->toBe($usage);
});

it('creates new instance with trace id via withTraceId', function () {
    $gen = new GenerationBody(id: 'gen-1', name: 'test', model: 'gpt-4');
    $withTrace = $gen->withTraceId('trace-99');

    expect($withTrace->traceId)->toBe('trace-99')
        ->and($withTrace->id)->toBe('gen-1')
        ->and($withTrace->name)->toBe('test')
        ->and($withTrace->model)->toBe('gpt-4')
        ->and($gen->traceId)->toBeNull();
});

it('serializes to array with nested usage', function () {
    $gen = new GenerationBody(
        id: 'gen-1',
        traceId: 'trace-1',
        model: 'gpt-4',
        usage: new Usage(input: 100, output: 200),
    );

    $array = $gen->toArray();

    expect($array['usage'])->toBe(['input' => 100, 'output' => 200])
        ->and($array['model'])->toBe('gpt-4');
});

it('excludes null values from serialization', function () {
    $gen = new GenerationBody(id: 'gen-1', model: 'gpt-4');

    $array = $gen->toArray();

    expect($array)->toBe(['id' => 'gen-1', 'model' => 'gpt-4'])
        ->and($array)->not->toHaveKey('usage')
        ->and($array)->not->toHaveKey('completionStartTime');
});

it('serializes level enum as string', function () {
    $gen = new GenerationBody(id: 'gen-1', level: ObservationLevel::ERROR);

    expect($gen->toArray()['level'])->toBe('ERROR');
});

it('implements SerializableInterface', function () {
    $gen = new GenerationBody(id: 'gen-1');

    expect($gen)->toBeInstanceOf(\Langfuse\Contracts\SerializableInterface::class);
});

it('excludes empty usage from serialization', function () {
    $gen = new GenerationBody(id: 'gen-1', usage: new Usage());

    expect($gen->toArray())->not->toHaveKey('usage');
});
