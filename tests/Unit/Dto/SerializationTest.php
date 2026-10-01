<?php

declare(strict_types=1);

use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\Timestamp;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Dto\Usage;
use Axyr\Langfuse\Objects\LangfuseTrace;
use Axyr\Langfuse\Otlp\OtlpRequestFactory;
use Axyr\Langfuse\Testing\RecordingEventBatcher;
use Axyr\Langfuse\Version;

/**
 * The canary payload: one trace with a root span, one generation and one child
 * span, serialised exactly as it goes on the wire to
 * POST /api/public/otel/v1/traces. Keep this in step with what the manual canary
 * run against a real Langfuse project sends.
 *
 * @return array<string, mixed>
 */
function canaryPayload(): array
{
    static $payload = null;

    if ($payload !== null) {
        return $payload;
    }

    $batcher = new RecordingEventBatcher();

    $trace = new LangfuseTrace(
        body: new TraceBody(
            id: 'canary-trace',
            name: 'canary',
            userId: 'user-1',
            sessionId: 'session-1',
            release: '1.2.3',
            version: 'v9',
            input: 'What is Langfuse?',
            metadata: ['source' => 'canary'],
            tags: ['canary', 'v4'],
            public: false,
            timestamp: '2024-01-01T00:00:00.000000Z',
            environment: 'staging',
        ),
        batcher: $batcher,
    );

    $trace->generation(new GenerationBody(
        id: 'canary-generation',
        name: 'gpt-4',
        startTime: '2024-01-01T00:00:00.500000Z',
        model: 'gpt-4',
        modelParameters: ['temperature' => 0.7],
        promptName: 'movie-critic',
        promptVersion: 7,
    ))->end(
        endTime: '2024-01-01T00:00:02.000000Z',
        output: 'An LLM observability platform.',
        usage: new Usage(input: 10, output: 20, total: 30, totalCost: 0.003),
    );

    $trace->span(new SpanBody(
        id: 'canary-span',
        name: 'lookup',
        startTime: '2024-01-01T00:00:02.000000Z',
        input: ['query' => 'langfuse'],
    ))->end(endTime: '2024-01-01T00:00:03.000000Z', output: ['hits' => 3]);

    $trace->end(endTime: '2024-01-01T00:00:04.000000Z', output: 'An LLM observability platform.');

    $factory = new OtlpRequestFactory(new LangfuseConfig(
        publicKey: 'pk-canary',
        secretKey: 'sk-canary',
        serviceName: 'canary-app',
    ));

    $payload = $factory->build($batcher->observations())[0]->toArray();

    return $payload;
}

/**
 * @return array<string, mixed>
 */
function spanNamed(string $name): array
{
    $spans = canaryPayload()['resourceSpans'][0]['scopeSpans'][0]['spans'];

    foreach ($spans as $span) {
        if ($span['name'] === $name) {
            return $span;
        }
    }

    throw new RuntimeException("No span named {$name} in the canary payload.");
}

/**
 * @param  array<string, mixed>  $span
 * @return array<string, mixed>
 */
function flatAttributes(array $span): array
{
    $attributes = [];

    foreach ($span['attributes'] as $attribute) {
        $attributes[$attribute['key']] = array_values($attribute['value'])[0];
    }

    return $attributes;
}

it('posts one resourceSpans entry with the resource and the scope', function () {
    $payload = canaryPayload();

    expect(array_keys($payload))->toBe(['resourceSpans'])
        ->and($payload['resourceSpans'])->toHaveCount(1)
        ->and($payload['resourceSpans'][0]['resource']['attributes'])->toBe([
            ['key' => 'service.name', 'value' => ['stringValue' => 'canary-app']],
            ['key' => 'telemetry.sdk.name', 'value' => ['stringValue' => 'langfuse-php']],
            ['key' => 'telemetry.sdk.language', 'value' => ['stringValue' => 'php']],
            ['key' => 'telemetry.sdk.version', 'value' => ['stringValue' => Version::SDK_VERSION]],
        ])
        ->and($payload['resourceSpans'][0]['scopeSpans'][0]['scope'])->toBe([
            'name' => 'langfuse-php',
            'version' => Version::SDK_VERSION,
        ]);
});

it('exports the generation, the child span and the root span exactly once each', function () {
    $spans = canaryPayload()['resourceSpans'][0]['scopeSpans'][0]['spans'];

    expect($spans)->toHaveCount(3)
        ->and(array_column($spans, 'name'))->toBe(['gpt-4', 'lookup', 'canary']);
});

it('nests both children under the root span of the trace', function () {
    $root = spanNamed('canary');

    expect($root)->not->toHaveKey('parentSpanId')
        ->and(spanNamed('gpt-4')['parentSpanId'])->toBe($root['spanId'])
        ->and(spanNamed('lookup')['parentSpanId'])->toBe($root['spanId'])
        ->and(spanNamed('gpt-4')['traceId'])->toBe($root['traceId'])
        ->and(spanNamed('lookup')['traceId'])->toBe($root['traceId']);
});

it('writes hex ids and nanosecond string edges', function () {
    $generation = spanNamed('gpt-4');

    expect($generation['traceId'])->toMatch('/^[0-9a-f]{32}$/')
        ->and($generation['spanId'])->toMatch('/^[0-9a-f]{16}$/')
        ->and($generation['kind'])->toBe(1)
        ->and($generation['startTimeUnixNano'])->toBe(Timestamp::toUnixNano('2024-01-01T00:00:00.500000Z'))
        ->and($generation['endTimeUnixNano'])->toBe(Timestamp::toUnixNano('2024-01-01T00:00:02.000000Z'))
        ->and($generation['status'])->toBe(['code' => 0, 'message' => '']);
});

it('puts the trace-level attributes on every span', function () {
    foreach (['canary', 'gpt-4', 'lookup'] as $name) {
        expect(flatAttributes(spanNamed($name)))->toMatchArray([
            'langfuse.trace.name' => 'canary',
            'langfuse.user.id' => 'user-1',
            'langfuse.session.id' => 'session-1',
            'langfuse.release' => '1.2.3',
            'langfuse.version' => 'v9',
            'langfuse.environment' => 'staging',
            'langfuse.trace.public' => false,
            'langfuse.trace.metadata.source' => 'canary',
        ]);
    }
});

it('emits the trace tags as an otlp array value', function () {
    $tags = array_values(array_filter(
        spanNamed('canary')['attributes'],
        fn(array $attribute): bool => $attribute['key'] === 'langfuse.trace.tags',
    ));

    expect($tags[0]['value'])->toBe([
        'arrayValue' => ['values' => [['stringValue' => 'canary'], ['stringValue' => 'v4']]],
    ]);
});

it('carries the trace input and output on the root observation only', function () {
    expect(flatAttributes(spanNamed('canary')))->toMatchArray([
        'langfuse.observation.type' => 'span',
        'langfuse.observation.input' => 'What is Langfuse?',
        'langfuse.observation.output' => 'An LLM observability platform.',
    ]);
});

it('maps the generation model, usage, cost and prompt link', function () {
    expect(flatAttributes(spanNamed('gpt-4')))->toMatchArray([
        'langfuse.observation.type' => 'generation',
        'langfuse.observation.model.name' => 'gpt-4',
        'langfuse.observation.model.parameters' => '{"temperature":0.7}',
        'langfuse.observation.usage_details' => '{"input":10,"output":20,"total":30}',
        'langfuse.observation.cost_details' => '{"total":0.003}',
        'langfuse.observation.prompt.name' => 'movie-critic',
        'langfuse.observation.prompt.version' => 7,
        'langfuse.observation.output' => 'An LLM observability platform.',
    ]);
});

it('json encodes structured span input and output', function () {
    expect(flatAttributes(spanNamed('lookup')))->toMatchArray([
        'langfuse.observation.type' => 'span',
        'langfuse.observation.input' => '{"query":"langfuse"}',
        'langfuse.observation.output' => '{"hits":3}',
    ]);
});

it('encodes to json without escaped slashes or unicode', function () {
    $json = json_encode(canaryPayload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    expect($json)->toBeString()
        ->and($json)->toContain('"resourceSpans"');
});
