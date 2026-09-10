<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\IdGenerator;
use Axyr\Langfuse\Dto\PromptFactory;
use Axyr\Langfuse\Dto\TextPrompt;
use Axyr\Langfuse\Dto\Usage;
use Axyr\Langfuse\Enums\ObservationLevel;

const GENERATION_TRACE_ID = 'dddddddddddddddddddddddddddddddd';
const GENERATION_ID = 'eeeeeeeeeeeeeeee';
const GENERATION_PARENT_ID = 'ffffffffffffffff';

it('auto-generates a 16 hex observation id when not provided', function () {
    expect((new GenerationBody())->id)->toMatch('/^[0-9a-f]{16}$/');
});

it('normalises a custom id into an observation id', function () {
    $gen = new GenerationBody(id: 'gen-1');

    expect($gen->id)->toBe(IdGenerator::spanIdFromSeed('gen-1'))
        ->and($gen->toArray()['id'])->toBe(IdGenerator::spanIdFromSeed('gen-1'));
});

it('normalises the trace id and the parent observation id', function () {
    $gen = new GenerationBody(id: GENERATION_ID, traceId: 'trace-1', parentObservationId: 'parent-1');

    expect($gen->traceId)->toBe(IdGenerator::traceIdFromSeed('trace-1'))
        ->and($gen->parentObservationId)->toBe(IdGenerator::spanIdFromSeed('parent-1'));
});

it('can be constructed with all fields', function () {
    $usage = new Usage(input: 100, output: 200, total: 300);
    $gen = new GenerationBody(
        id: GENERATION_ID,
        traceId: GENERATION_TRACE_ID,
        name: 'chat-completion',
        startTime: '2024-01-01T00:00:00Z',
        endTime: '2024-01-01T00:00:01Z',
        completionStartTime: '2024-01-01T00:00:00.500Z',
        input: [['role' => 'user', 'content' => 'Hello']],
        output: ['role' => 'assistant', 'content' => 'Hi!'],
        metadata: ['provider' => 'openai'],
        level: ObservationLevel::DEFAULT,
        statusMessage: 'Success',
        parentObservationId: GENERATION_PARENT_ID,
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
    $gen = new GenerationBody(id: GENERATION_ID, name: 'test', model: 'gpt-4');
    $withTrace = $gen->withTraceId(GENERATION_TRACE_ID);

    expect($withTrace->traceId)->toBe(GENERATION_TRACE_ID)
        ->and($withTrace->id)->toBe(GENERATION_ID)
        ->and($withTrace->name)->toBe('test')
        ->and($withTrace->model)->toBe('gpt-4')
        ->and($gen->traceId)->toBeNull();
});

it('preserves new fields through withContext', function () {
    $gen = new GenerationBody(
        id: GENERATION_ID,
        promptName: 'my-prompt',
        promptVersion: 3,
        environment: 'staging',
    );

    $withContext = $gen->withContext(GENERATION_TRACE_ID, GENERATION_PARENT_ID);

    expect($withContext->id)->toBe(GENERATION_ID)
        ->and($withContext->traceId)->toBe(GENERATION_TRACE_ID)
        ->and($withContext->parentObservationId)->toBe(GENERATION_PARENT_ID)
        ->and($withContext->promptName)->toBe('my-prompt')
        ->and($withContext->promptVersion)->toBe(3)
        ->and($withContext->environment)->toBe('staging');
});

it('stamps a start time only when none is set', function () {
    $gen = new GenerationBody(id: GENERATION_ID);

    $started = $gen->startedAt('2024-01-01T00:00:00Z');

    expect($started->startTime)->toBe('2024-01-01T00:00:00Z')
        ->and($started->startedAt('2024-06-01T00:00:00Z'))->toBe($started);
});

it('builds the finished observation with completed', function () {
    $usage = new Usage(input: 10, output: 20, total: 30);
    $gen = new GenerationBody(id: GENERATION_ID, name: 'chat', model: 'gpt-4', startTime: '2024-01-01T00:00:00Z');

    $done = $gen->completed(
        endTime: '2024-01-01T00:00:02Z',
        output: 'answer',
        usage: $usage,
        statusMessage: 'stop',
    );

    expect($done->id)->toBe(GENERATION_ID)
        ->and($done->model)->toBe('gpt-4')
        ->and($done->startTime)->toBe('2024-01-01T00:00:00Z')
        ->and($done->endTime)->toBe('2024-01-01T00:00:02Z')
        ->and($done->output)->toBe('answer')
        ->and($done->usage)->toBe($usage)
        ->and($done->statusMessage)->toBe('stop');
});

it('serializes to array with nested usage', function () {
    $gen = new GenerationBody(
        id: GENERATION_ID,
        traceId: GENERATION_TRACE_ID,
        model: 'gpt-4',
        usage: new Usage(input: 100, output: 200),
    );

    $array = $gen->toArray();

    expect($array['usage'])->toBe(['input' => 100, 'output' => 200])
        ->and($array['model'])->toBe('gpt-4');
});

it('excludes null values from serialization', function () {
    $gen = new GenerationBody(id: GENERATION_ID, model: 'gpt-4');

    $array = $gen->toArray();

    expect($array)->toBe(['id' => GENERATION_ID, 'model' => 'gpt-4'])
        ->and($array)->not->toHaveKey('usage')
        ->and($array)->not->toHaveKey('completionStartTime')
        ->and($array)->not->toHaveKey('promptName')
        ->and($array)->not->toHaveKey('environment');
});

it('serializes level enum as string', function () {
    $gen = new GenerationBody(id: GENERATION_ID, level: ObservationLevel::ERROR);

    expect($gen->toArray()['level'])->toBe('ERROR');
});

it('includes new fields in serialization', function () {
    $gen = new GenerationBody(
        id: GENERATION_ID,
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
    expect(new GenerationBody(id: GENERATION_ID))
        ->toBeInstanceOf(\Axyr\Langfuse\Contracts\SerializableInterface::class);
});

it('includes empty usage in serialization when explicitly set', function () {
    $gen = new GenerationBody(id: GENERATION_ID, usage: new Usage());

    expect($gen->toArray())->toHaveKey('usage')
        ->and($gen->toArray()['usage'])->toBe([]);
});

it('stamps an environment only when none is set', function () {
    $body = new GenerationBody(id: GENERATION_ID, traceId: GENERATION_TRACE_ID, promptName: 'p', promptVersion: 1);

    $stamped = $body->withEnvironment('production');

    expect($stamped->environment)->toBe('production')
        ->and($stamped->traceId)->toBe(GENERATION_TRACE_ID)
        ->and($stamped->promptName)->toBe('p')
        ->and($body->withEnvironment(null))->toBe($body)
        ->and($stamped->withEnvironment('staging'))->toBe($stamped);
});

it('links a managed prompt only when not already linked', function () {
    $body = new GenerationBody(id: GENERATION_ID, environment: 'production');
    $prompt = new TextPrompt(name: 'movie-critic', version: 7, prompt: 'text');

    $linked = $body->withPrompt($prompt);

    expect($linked->promptName)->toBe('movie-critic')
        ->and($linked->promptVersion)->toBe(7)
        ->and($linked->environment)->toBe('production')
        ->and($body->withPrompt(null))->toBe($body)
        ->and($linked->withPrompt(new TextPrompt(name: 'other', version: 1, prompt: 'x')))->toBe($linked);
});

it('never links fallback prompts', function () {
    $body = new GenerationBody(id: GENERATION_ID);

    expect($body->withPrompt(PromptFactory::fallbackText('movie-critic', 'text')))->toBe($body);
});
