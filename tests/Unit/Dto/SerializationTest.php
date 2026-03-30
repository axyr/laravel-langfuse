<?php

declare(strict_types=1);

use Langfuse\Dto\EventBody;
use Langfuse\Dto\GenerationBody;
use Langfuse\Dto\ScoreBody;
use Langfuse\Dto\SpanBody;
use Langfuse\Dto\TraceBody;
use Langfuse\Dto\Usage;
use Langfuse\Enums\ObservationLevel;
use Langfuse\Enums\ScoreDataType;

dataset('trace bodies', function () {
    yield 'minimal trace' => [
        new TraceBody(id: 'trace-min'),
        ['id' => 'trace-min'],
    ];

    yield 'trace with name' => [
        new TraceBody(id: 'trace-name', name: 'my-trace'),
        ['id' => 'trace-name', 'name' => 'my-trace'],
    ];

    yield 'trace with all fields' => [
        new TraceBody(
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
        ),
        [
            'id' => 'trace-full',
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
        ],
    ];
});

dataset('score bodies', function () {
    yield 'minimal score' => [
        new ScoreBody(id: 'score-min', traceId: 'trace-1', name: 'accuracy'),
        ['id' => 'score-min', 'traceId' => 'trace-1', 'name' => 'accuracy'],
    ];

    yield 'numeric score' => [
        new ScoreBody(id: 'score-num', traceId: 'trace-1', name: 'accuracy', value: 0.95, dataType: ScoreDataType::NUMERIC),
        ['id' => 'score-num', 'traceId' => 'trace-1', 'name' => 'accuracy', 'value' => 0.95, 'dataType' => 'NUMERIC'],
    ];

    yield 'boolean score' => [
        new ScoreBody(id: 'score-bool', traceId: 'trace-1', name: 'is_correct', stringValue: 'true', dataType: ScoreDataType::BOOLEAN),
        ['id' => 'score-bool', 'traceId' => 'trace-1', 'name' => 'is_correct', 'stringValue' => 'true', 'dataType' => 'BOOLEAN'],
    ];

    yield 'categorical score' => [
        new ScoreBody(id: 'score-cat', traceId: 'trace-1', name: 'quality', stringValue: 'good', dataType: ScoreDataType::CATEGORICAL),
        ['id' => 'score-cat', 'traceId' => 'trace-1', 'name' => 'quality', 'stringValue' => 'good', 'dataType' => 'CATEGORICAL'],
    ];
});

dataset('event bodies', function () {
    yield 'minimal event' => [
        new EventBody(id: 'event-min'),
        ['id' => 'event-min'],
    ];

    yield 'event with level' => [
        new EventBody(id: 'event-lvl', name: 'test', level: ObservationLevel::WARNING),
        ['id' => 'event-lvl', 'name' => 'test', 'level' => 'WARNING'],
    ];
});

dataset('span bodies', function () {
    yield 'minimal span' => [
        new SpanBody(id: 'span-min'),
        ['id' => 'span-min'],
    ];

    yield 'span with times' => [
        new SpanBody(id: 'span-time', startTime: '2024-01-01T00:00:00Z', endTime: '2024-01-01T00:00:01Z'),
        ['id' => 'span-time', 'startTime' => '2024-01-01T00:00:00Z', 'endTime' => '2024-01-01T00:00:01Z'],
    ];
});

dataset('generation bodies', function () {
    yield 'minimal generation' => [
        new GenerationBody(id: 'gen-min'),
        ['id' => 'gen-min'],
    ];

    yield 'generation with model' => [
        new GenerationBody(id: 'gen-model', model: 'gpt-4', modelParameters: ['temperature' => 0.7]),
        ['id' => 'gen-model', 'model' => 'gpt-4', 'modelParameters' => ['temperature' => 0.7]],
    ];

    yield 'generation with usage' => [
        new GenerationBody(id: 'gen-usage', usage: new Usage(input: 100, output: 200, total: 300)),
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
        new Usage(inputCost: 10, outputCost: 20, totalCost: 30),
        ['inputCost' => 10, 'outputCost' => 20, 'totalCost' => 30],
    ];
});

it('serializes trace body correctly', function (TraceBody $body, array $expected) {
    expect($body->toArray())->toBe($expected);
})->with('trace bodies');

it('serializes score body correctly', function (ScoreBody $body, array $expected) {
    expect($body->toArray())->toBe($expected);
})->with('score bodies');

it('serializes event body correctly', function (EventBody $body, array $expected) {
    expect($body->toArray())->toBe($expected);
})->with('event bodies');

it('serializes span body correctly', function (SpanBody $body, array $expected) {
    expect($body->toArray())->toBe($expected);
})->with('span bodies');

it('serializes generation body correctly', function (GenerationBody $body, array $expected) {
    expect($body->toArray())->toBe($expected);
})->with('generation bodies');

it('serializes usage correctly', function (Usage $body, array $expected) {
    expect($body->toArray())->toBe($expected);
})->with('usage bodies');
