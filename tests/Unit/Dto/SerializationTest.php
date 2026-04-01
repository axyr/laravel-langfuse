<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\EventBody;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Dto\Usage;
use Axyr\Langfuse\Enums\ObservationLevel;
use Axyr\Langfuse\Enums\ScoreDataType;

dataset('trace bodies', function () {
    yield 'minimal trace' => [
        fn() => new TraceBody(id: 'trace-min', timestamp: '2024-01-01T00:00:00.000000Z'),
        ['id' => 'trace-min', 'timestamp' => '2024-01-01T00:00:00.000000Z'],
    ];

    yield 'trace with name' => [
        fn() => new TraceBody(id: 'trace-name', name: 'my-trace', timestamp: '2024-01-01T00:00:00.000000Z'),
        ['id' => 'trace-name', 'timestamp' => '2024-01-01T00:00:00.000000Z', 'name' => 'my-trace'],
    ];

    yield 'trace with all fields' => [
        fn() => new TraceBody(
            id: 'trace-full',
            name: 'full-trace',
            userId: 'user-1',
            sessionId: 'session-1',
            release: 'v1.0.0',
            version: '1',
            input: 'hello',
            output: 'world',
            metadata: ['key' => 'value'],
            tags: ['tag1'],
            public: true,
            timestamp: '2024-01-01T00:00:00.000000Z',
            environment: 'production',
        ),
        [
            'id' => 'trace-full',
            'timestamp' => '2024-01-01T00:00:00.000000Z',
            'name' => 'full-trace',
            'userId' => 'user-1',
            'sessionId' => 'session-1',
            'release' => 'v1.0.0',
            'version' => '1',
            'input' => 'hello',
            'output' => 'world',
            'metadata' => ['key' => 'value'],
            'tags' => ['tag1'],
            'public' => true,
            'environment' => 'production',
        ],
    ];
});

dataset('score bodies', function () {
    yield 'minimal score' => [
        fn() => new ScoreBody(name: 'accuracy', id: 'score-min', traceId: 'trace-1'),
        ['id' => 'score-min', 'traceId' => 'trace-1', 'name' => 'accuracy'],
    ];

    yield 'numeric score' => [
        fn() => new ScoreBody(name: 'accuracy', id: 'score-num', traceId: 'trace-1', value: 0.95, dataType: ScoreDataType::NUMERIC),
        ['id' => 'score-num', 'traceId' => 'trace-1', 'name' => 'accuracy', 'value' => 0.95, 'dataType' => 'NUMERIC'],
    ];

    yield 'boolean score' => [
        fn() => new ScoreBody(name: 'is_correct', id: 'score-bool', traceId: 'trace-1', stringValue: 'true', dataType: ScoreDataType::BOOLEAN),
        ['id' => 'score-bool', 'traceId' => 'trace-1', 'name' => 'is_correct', 'stringValue' => 'true', 'dataType' => 'BOOLEAN'],
    ];

    yield 'categorical score' => [
        fn() => new ScoreBody(name: 'quality', id: 'score-cat', traceId: 'trace-1', stringValue: 'good', dataType: ScoreDataType::CATEGORICAL),
        ['id' => 'score-cat', 'traceId' => 'trace-1', 'name' => 'quality', 'stringValue' => 'good', 'dataType' => 'CATEGORICAL'],
    ];
});

dataset('event bodies', function () {
    yield 'minimal event' => [
        fn() => new EventBody(id: 'event-min', startTime: '2024-01-01T00:00:00.000000Z'),
        ['id' => 'event-min', 'startTime' => '2024-01-01T00:00:00.000000Z'],
    ];

    yield 'event with level' => [
        fn() => new EventBody(id: 'event-lvl', name: 'test', startTime: '2024-01-01T00:00:00.000000Z', level: ObservationLevel::WARNING),
        ['id' => 'event-lvl', 'name' => 'test', 'startTime' => '2024-01-01T00:00:00.000000Z', 'level' => 'WARNING'],
    ];
});

dataset('span bodies', function () {
    yield 'minimal span' => [
        fn() => new SpanBody(id: 'span-min'),
        ['id' => 'span-min'],
    ];

    yield 'span with times' => [
        fn() => new SpanBody(id: 'span-time', startTime: '2024-01-01T00:00:00Z', endTime: '2024-01-01T00:00:01Z'),
        ['id' => 'span-time', 'startTime' => '2024-01-01T00:00:00Z', 'endTime' => '2024-01-01T00:00:01Z'],
    ];
});

dataset('generation bodies', function () {
    yield 'minimal generation' => [
        fn() => new GenerationBody(id: 'gen-min'),
        ['id' => 'gen-min'],
    ];

    yield 'generation with model' => [
        fn() => new GenerationBody(id: 'gen-model', model: 'gpt-4', modelParameters: ['temperature' => 0.7]),
        ['id' => 'gen-model', 'model' => 'gpt-4', 'modelParameters' => ['temperature' => 0.7]],
    ];

    yield 'generation with usage' => [
        fn() => new GenerationBody(id: 'gen-usage', usage: new Usage(input: 100, output: 200, total: 300)),
        ['id' => 'gen-usage', 'usage' => ['input' => 100, 'output' => 200, 'total' => 300]],
    ];
});

dataset('usage bodies', function () {
    yield 'empty usage' => [
        new Usage(),
        [],
    ];

    yield 'token usage' => [
        new Usage(input: 100, output: 200, total: 300, unit: 'TOKENS'),
        ['input' => 100, 'output' => 200, 'total' => 300, 'unit' => 'TOKENS'],
    ];

    yield 'cost usage' => [
        new Usage(inputCost: 0.0005, outputCost: 0.0015, totalCost: 0.002),
        ['inputCost' => 0.0005, 'outputCost' => 0.0015, 'totalCost' => 0.002],
    ];
});

it('serializes trace body correctly', function (Closure|TraceBody $body, array $expected) {
    $body = $body instanceof Closure ? $body() : $body;
    expect($body->toArray())->toBe($expected);
})->with('trace bodies');

it('serializes score body correctly', function (Closure|ScoreBody $body, array $expected) {
    $body = $body instanceof Closure ? $body() : $body;
    expect($body->toArray())->toBe($expected);
})->with('score bodies');

it('serializes event body correctly', function (Closure|EventBody $body, array $expected) {
    $body = $body instanceof Closure ? $body() : $body;
    expect($body->toArray())->toBe($expected);
})->with('event bodies');

it('serializes span body correctly', function (Closure|SpanBody $body, array $expected) {
    $body = $body instanceof Closure ? $body() : $body;
    expect($body->toArray())->toBe($expected);
})->with('span bodies');

it('serializes generation body correctly', function (Closure|GenerationBody $body, array $expected) {
    $body = $body instanceof Closure ? $body() : $body;
    expect($body->toArray())->toBe($expected);
})->with('generation bodies');

it('serializes usage correctly', function (Usage $body, array $expected) {
    expect($body->toArray())->toBe($expected);
})->with('usage bodies');
