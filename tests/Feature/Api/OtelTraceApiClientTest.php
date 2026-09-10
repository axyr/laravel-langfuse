<?php

declare(strict_types=1);

use Axyr\Langfuse\Api\OtelTraceApiClient;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Dto\Otlp\OtlpAttribute;
use Axyr\Langfuse\Dto\Otlp\OtlpExportRequest;
use Axyr\Langfuse\Dto\Otlp\OtlpSpan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

const OTEL_URL = 'cloud.langfuse.com/api/public/otel/v1/traces';

function exportRequest(): OtlpExportRequest
{
    return new OtlpExportRequest(
        spans: [new OtlpSpan(
            traceId: '0192f1b42c7e7a1b8d3e9f0a1b2c3d4e',
            spanId: '0192f1b42c7e7a1b',
            parentSpanId: null,
            name: 'work',
            startTimeUnixNano: '1704067200000000000',
            endTimeUnixNano: '1704067201000000000',
        )],
        resourceAttributes: [OtlpAttribute::string('service.name', 'my-app')],
        scopeName: 'langfuse-php',
        scopeVersion: '0.4.0',
    );
}

function otelConfig(bool $compression = false): LangfuseConfig
{
    return new LangfuseConfig(
        publicKey: 'pk-test',
        secretKey: 'sk-test',
        baseUrl: 'https://cloud.langfuse.com',
        compression: $compression,
    );
}

it('posts the otlp payload to the traces endpoint with the ingestion version header', function () {
    Http::fake([OTEL_URL => Http::response([])]);

    $config = otelConfig();
    $result = (new OtelTraceApiClient($config))->export(exportRequest());

    expect($result->accepted)->toBeTrue()
        ->and($result->retryable)->toBeFalse();

    Http::assertSent(function ($request) use ($config) {
        $body = json_decode($request->body(), true);

        return $request->url() === 'https://cloud.langfuse.com/api/public/otel/v1/traces'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', $config->authHeader())
            && $request->hasHeader('Content-Type', 'application/json')
            && $request->hasHeader('x-langfuse-ingestion-version', '4')
            && ! $request->hasHeader('Content-Encoding')
            && $body['resourceSpans'][0]['scopeSpans'][0]['spans'][0]['name'] === 'work';
    });
});

it('gzips the body when compression is enabled', function () {
    Http::fake([OTEL_URL => Http::response([])]);

    (new OtelTraceApiClient(otelConfig(compression: true)))->export(exportRequest());

    Http::assertSent(function ($request) {
        $decoded = gzdecode($request->body());

        return $request->hasHeader('Content-Encoding', 'gzip')
            && is_string($decoded)
            && str_contains($decoded, '"resourceSpans"');
    });
});

it('treats an empty object as success', function () {
    Http::fake([OTEL_URL => Http::response([])]);

    $result = (new OtelTraceApiClient(otelConfig()))->export(exportRequest());

    expect($result->accepted)->toBeTrue()
        ->and($result->rejectedSpans)->toBe(0)
        ->and($result->hasPartialFailure())->toBeFalse();
});

it('reports and logs a partial success without asking for a retry', function () {
    Log::shouldReceive('warning')
        ->once()
        ->with('Langfuse OTLP partial success', Mockery::on(function (array $context) {
            return $context['rejectedSpans'] === 2 && $context['errorMessage'] === 'two spans dropped';
        }));

    Http::fake([
        OTEL_URL => Http::response([
            'partialSuccess' => ['rejectedSpans' => 2, 'errorMessage' => 'two spans dropped'],
        ]),
    ]);

    $result = (new OtelTraceApiClient(otelConfig()))->export(exportRequest());

    expect($result->accepted)->toBeTrue()
        ->and($result->retryable)->toBeFalse()
        ->and($result->rejectedSpans)->toBe(2)
        ->and($result->errorMessage)->toBe('two spans dropped')
        ->and($result->hasPartialFailure())->toBeTrue();
});

it('drops a 400 without asking for a retry', function () {
    Log::shouldReceive('warning')
        ->once()
        ->with('Langfuse OTLP export failed', Mockery::on(function (array $context) {
            return $context['status'] === 400 && $context['retryable'] === false;
        }));

    Http::fake([OTEL_URL => Http::response('Bad Request', 400)]);

    $result = (new OtelTraceApiClient(otelConfig()))->export(exportRequest());

    expect($result->accepted)->toBeFalse()
        ->and($result->retryable)->toBeFalse()
        ->and($result->status)->toBe(400);
});

it('reports a 429 as retryable with the Retry-After delay', function () {
    Log::shouldReceive('warning')->once()->with('Langfuse OTLP export failed', Mockery::any());

    Http::fake([OTEL_URL => Http::response('Too Many Requests', 429, ['Retry-After' => '30'])]);

    $result = (new OtelTraceApiClient(otelConfig()))->export(exportRequest());

    expect($result->accepted)->toBeFalse()
        ->and($result->retryable)->toBeTrue()
        ->and($result->status)->toBe(429)
        ->and($result->retryAfterSeconds)->toBe(30);
});

it('reports the other retryable statuses', function (int $status) {
    Log::shouldReceive('warning')->once()->with('Langfuse OTLP export failed', Mockery::any());

    Http::fake([OTEL_URL => Http::response('', $status)]);

    $result = (new OtelTraceApiClient(otelConfig()))->export(exportRequest());

    expect($result->retryable)->toBeTrue()
        ->and($result->retryAfterSeconds)->toBeNull();
})->with([502, 503, 504]);

it('returns a retryable transport error and logs when the request throws', function () {
    Log::shouldReceive('warning')
        ->once()
        ->with('Langfuse OTLP export error', Mockery::on(function (array $context) {
            return str_contains($context['message'], 'Connection refused');
        }));

    Http::fake([OTEL_URL => fn() => throw new \Exception('Connection refused')]);

    $result = (new OtelTraceApiClient(otelConfig()))->export(exportRequest());

    expect($result->accepted)->toBeFalse()
        ->and($result->retryable)->toBeTrue()
        ->and($result->status)->toBeNull();
});

it('exports a raw payload for the queue job', function () {
    Http::fake([OTEL_URL => Http::response([])]);

    $payload = ['resourceSpans' => [['scopeSpans' => [['spans' => [['name' => 'raw']]]]]]];

    $result = (new OtelTraceApiClient(otelConfig()))->exportRaw($payload);

    expect($result->accepted)->toBeTrue();

    Http::assertSent(function ($request) {
        return json_decode($request->body(), true)['resourceSpans'][0]['scopeSpans'][0]['spans'][0]['name'] === 'raw';
    });
});
