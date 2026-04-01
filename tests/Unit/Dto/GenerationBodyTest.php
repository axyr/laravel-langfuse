<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\Usage;
use Axyr\Langfuse\Enums\ObservationLevel;

it('auto-generates id when not provided', function () {
    $gen = new GenerationBody();

    expect($gen->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('uses provided id when given', function () {
    $gen = new GenerationBody(id: 'gen-1');

    expect($gen->id)->toBe('gen-1');
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
        promptName: 'my-prompt',
        promptVersion: 2,
        environment: 'production',
    );

    expect($gen->model)->toBe('gpt-4')
        ->and($gen->completionStartTime)->toBe('2024-01-01T00:00:00.500Z')
        ->and($gen->modelParameters)->toBe(['temperature' => 0.7])
        ->and($gen->usage)->toBe($usage)
        ->and($gen->promptName)->toBe('my-prompt')
        ->and($gen->promptVersion)->toBe(2)
        ->and($gen->environment)->toBe('production');
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

it('preserves new fields through withContext', function () {
    $gen = new GenerationBody(
        id: 'gen-1',
        promptName: 'my-prompt',
        promptVersion: 3,
        environment: 'staging',
    );

    $withContext = $gen->withContext('trace-1', 'parent-1');

    expect($withContext->id)->toBe('gen-1')
        ->and($withContext->traceId)->toBe('trace-1')
        ->and($withContext->parentObservationId)->toBe('parent-1')
        ->and($withContext->promptName)->toBe('my-prompt')
        ->and($withContext->promptVersion)->toBe(3)
        ->and($withContext->environment)->toBe('staging');
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
        ->and($array)->not->toHaveKey('completionStartTime')
        ->and($array)->not->toHaveKey('promptName')
        ->and($array)->not->toHaveKey('environment');
});

it('serializes level enum as string', function () {
    $gen = new GenerationBody(id: 'gen-1', level: ObservationLevel::ERROR);

    expect($gen->toArray()['level'])->toBe('ERROR');
});

it('includes new fields in serialization', function () {
    $gen = new GenerationBody(
        id: 'gen-1',
        promptName: 'my-prompt',
        promptVersion: 2,
        environment: 'production',
    );

    $array = $gen->toArray();

    expect($array['promptName'])->toBe('my-prompt')
        ->and($array['promptVersion'])->toBe(2)
        ->and($array['environment'])->toBe('production');
});

it('implements SerializableInterface', function () {
    $gen = new GenerationBody(id: 'gen-1');

    expect($gen)->toBeInstanceOf(\Axyr\Langfuse\Contracts\SerializableInterface::class);
});

it('includes empty usage in serialization when explicitly set', function () {
    $gen = new GenerationBody(id: 'gen-1', usage: new Usage());

    expect($gen->toArray())->toHaveKey('usage')
        ->and($gen->toArray()['usage'])->toBe([]);
});
