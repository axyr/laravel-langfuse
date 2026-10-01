<?php

declare(strict_types=1);

use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Otlp\OtlpRequestFactory;
use Axyr\Langfuse\Tracing\CompletedObservation;
use Axyr\Langfuse\Tracing\TraceContext;
use Axyr\Langfuse\Version;
use Illuminate\Support\Facades\Log;

function factoryConfig(): LangfuseConfig
{
    return new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', serviceName: 'my-app');
}

function spanObservation(TraceContext $context, string $name = 'work', mixed $input = null): CompletedObservation
{
    $body = (new SpanBody(name: $name, input: $input, startTime: '2024-01-01T00:00:00Z', endTime: '2024-01-01T00:00:01Z'))
        ->withContext($context->traceId(), $context->rootObservationId());

    return CompletedObservation::span($body, $context);
}

it('returns no requests for an empty batch', function () {
    expect((new OtlpRequestFactory(factoryConfig()))->build([]))->toBe([]);
});

it('builds one request carrying the resource attributes and the scope', function () {
    $context = new TraceContext(new TraceBody(id: 'trace-1'));

    $requests = (new OtlpRequestFactory(factoryConfig()))->build([spanObservation($context)]);

    expect($requests)->toHaveCount(1);

    $payload = $requests[0]->toArray();
    $resourceSpan = $payload['resourceSpans'][0];

    expect($resourceSpan['resource']['attributes'])->toBe([
        ['key' => 'service.name', 'value' => ['stringValue' => 'my-app']],
        ['key' => 'telemetry.sdk.name', 'value' => ['stringValue' => Version::SDK_NAME]],
        ['key' => 'telemetry.sdk.language', 'value' => ['stringValue' => 'php']],
        ['key' => 'telemetry.sdk.version', 'value' => ['stringValue' => Version::SDK_VERSION]],
    ])
        ->and($resourceSpan['scopeSpans'][0]['scope'])->toBe([
            'name' => Version::SDK_NAME,
            'version' => Version::SDK_VERSION,
        ])
        ->and($resourceSpan['scopeSpans'][0]['spans'])->toHaveCount(1);
});

it('follows the otlp json rules for a span', function () {
    $context = new TraceContext(new TraceBody(id: 'trace-1'));

    $payload = (new OtlpRequestFactory(factoryConfig()))->build([spanObservation($context)])[0]->toArray();
    $span = $payload['resourceSpans'][0]['scopeSpans'][0]['spans'][0];

    expect($span['traceId'])->toMatch('/^[0-9a-f]{32}$/')
        ->and($span['spanId'])->toMatch('/^[0-9a-f]{16}$/')
        ->and($span['parentSpanId'])->toBe($context->rootObservationId())
        ->and($span['kind'])->toBe(1)
        ->and($span['startTimeUnixNano'])->toBeString()
        ->and($span['endTimeUnixNano'])->toBeString()
        ->and($span['status'])->toBe(['code' => 0, 'message' => '']);
});

it('omits parentSpanId on the root span', function () {
    $context = new TraceContext(new TraceBody(id: 'trace-1', name: 'root'));

    $payload = (new OtlpRequestFactory(factoryConfig()))
        ->build([CompletedObservation::root($context, '2024-01-01T00:00:05Z')])[0]
        ->toArray();

    expect($payload['resourceSpans'][0]['scopeSpans'][0]['spans'][0])->not->toHaveKey('parentSpanId');
});

it('keeps every observation of a batch in a single request when it fits', function () {
    $context = new TraceContext(new TraceBody(id: 'trace-1'));

    $requests = (new OtlpRequestFactory(factoryConfig()))->build([
        CompletedObservation::root($context, '2024-01-01T00:00:05Z'),
        spanObservation($context, 'one'),
        spanObservation($context, 'two'),
    ]);

    expect($requests)->toHaveCount(1)
        ->and($requests[0]->spanCount())->toBe(3);
});

it('splits a batch that would exceed the request limit', function () {
    $context = new TraceContext(new TraceBody(id: 'trace-1'));
    $big = str_repeat('x', (int) (OtlpRequestFactory::MAX_REQUEST_BYTES * 0.6));

    $requests = (new OtlpRequestFactory(factoryConfig()))->build([
        spanObservation($context, 'one', $big),
        spanObservation($context, 'two', $big),
    ]);

    expect($requests)->toHaveCount(2)
        ->and($requests[0]->spanCount())->toBe(1)
        ->and($requests[1]->spanCount())->toBe(1);
});

it('exports an oversized observation on its own and warns', function () {
    Log::shouldReceive('warning')
        ->once()
        ->with('Langfuse observation exceeds the OTLP request limit', Mockery::any());

    $context = new TraceContext(new TraceBody(id: 'trace-1'));
    $huge = str_repeat('x', OtlpRequestFactory::MAX_REQUEST_BYTES + 1024);

    $requests = (new OtlpRequestFactory(factoryConfig()))->build([spanObservation($context, 'huge', $huge)]);

    expect($requests)->toHaveCount(1)
        ->and($requests[0]->spanCount())->toBe(1);
});
