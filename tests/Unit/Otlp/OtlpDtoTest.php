<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\CursorMeta;
use Axyr\Langfuse\Dto\OtelExportResult;
use Axyr\Langfuse\Dto\Otlp\OtlpAttribute;
use Axyr\Langfuse\Dto\Otlp\OtlpExportRequest;
use Axyr\Langfuse\Dto\Otlp\OtlpSpan;

it('serialises each attribute value kind', function () {
    expect(OtlpAttribute::string('k', 'v')->toArray())->toBe(['key' => 'k', 'value' => ['stringValue' => 'v']])
        ->and(OtlpAttribute::int('k', 7)->toArray())->toBe(['key' => 'k', 'value' => ['intValue' => 7]])
        ->and(OtlpAttribute::double('k', 1.5)->toArray())->toBe(['key' => 'k', 'value' => ['doubleValue' => 1.5]])
        ->and(OtlpAttribute::bool('k', true)->toArray())->toBe(['key' => 'k', 'value' => ['boolValue' => true]])
        ->and(OtlpAttribute::stringArray('k', ['a', 'b'])->toArray())->toBe([
            'key' => 'k',
            'value' => ['arrayValue' => ['values' => [['stringValue' => 'a'], ['stringValue' => 'b']]]],
        ]);
});

it('serialises a span with its parent, kind and status', function () {
    $span = new OtlpSpan(
        traceId: '0192f1b42c7e7a1b8d3e9f0a1b2c3d4e',
        spanId: '0192f1b42c7e7a1b',
        parentSpanId: 'aaaaaaaaaaaaaaaa',
        name: 'work',
        startTimeUnixNano: '1704067200000000000',
        endTimeUnixNano: '1704067201000000000',
        attributes: [OtlpAttribute::string('langfuse.observation.type', 'span')],
        statusCode: OtlpSpan::STATUS_ERROR,
        statusMessage: 'boom',
    );

    expect($span->toArray())->toBe([
        'traceId' => '0192f1b42c7e7a1b8d3e9f0a1b2c3d4e',
        'spanId' => '0192f1b42c7e7a1b',
        'name' => 'work',
        'kind' => 1,
        'startTimeUnixNano' => '1704067200000000000',
        'endTimeUnixNano' => '1704067201000000000',
        'attributes' => [['key' => 'langfuse.observation.type', 'value' => ['stringValue' => 'span']]],
        'status' => ['code' => 2, 'message' => 'boom'],
        'parentSpanId' => 'aaaaaaaaaaaaaaaa',
    ]);
});

it('omits an empty parent span id', function () {
    $span = new OtlpSpan(
        traceId: '0192f1b42c7e7a1b8d3e9f0a1b2c3d4e',
        spanId: '0192f1b42c7e7a1b',
        parentSpanId: '',
        name: 'root',
        startTimeUnixNano: '1',
        endTimeUnixNano: '2',
    );

    expect($span->toArray())->not->toHaveKey('parentSpanId')
        ->and($span->toArray()['status'])->toBe(['code' => 0, 'message' => '']);
});

it('wraps spans in one resourceSpans entry', function () {
    $request = new OtlpExportRequest(
        spans: [new OtlpSpan(
            traceId: '0192f1b42c7e7a1b8d3e9f0a1b2c3d4e',
            spanId: '0192f1b42c7e7a1b',
            parentSpanId: null,
            name: 'root',
            startTimeUnixNano: '1',
            endTimeUnixNano: '2',
        )],
        resourceAttributes: [OtlpAttribute::string('service.name', 'my-app')],
        scopeName: 'langfuse-php',
        scopeVersion: '0.4.0',
    );

    $payload = $request->toArray();

    expect(array_keys($payload))->toBe(['resourceSpans'])
        ->and($request->spanCount())->toBe(1)
        ->and($payload['resourceSpans'][0]['resource']['attributes'])
        ->toBe([['key' => 'service.name', 'value' => ['stringValue' => 'my-app']]])
        ->and($payload['resourceSpans'][0]['scopeSpans'][0]['scope'])
        ->toBe(['name' => 'langfuse-php', 'version' => '0.4.0'])
        ->and($payload['resourceSpans'][0]['scopeSpans'][0]['spans'])->toHaveCount(1);
});

it('describes a successful export', function () {
    $result = OtelExportResult::success();

    expect($result->accepted)->toBeTrue()
        ->and($result->retryable)->toBeFalse()
        ->and($result->hasPartialFailure())->toBeFalse();
});

it('describes a partial success as accepted but not retryable', function () {
    $result = OtelExportResult::partial(2, 'two rejected');

    expect($result->accepted)->toBeTrue()
        ->and($result->retryable)->toBeFalse()
        ->and($result->rejectedSpans)->toBe(2)
        ->and($result->errorMessage)->toBe('two rejected')
        ->and($result->hasPartialFailure())->toBeTrue();
});

it('describes a failed export', function () {
    $result = OtelExportResult::failed(status: 429, retryable: true, retryAfterSeconds: 30, errorMessage: 'slow down');

    expect($result->accepted)->toBeFalse()
        ->and($result->retryable)->toBeTrue()
        ->and($result->status)->toBe(429)
        ->and($result->retryAfterSeconds)->toBe(30)
        ->and($result->errorMessage)->toBe('slow down');
});

it('describes a transport error as retryable with no status', function () {
    $result = OtelExportResult::transportError('connection reset');

    expect($result->accepted)->toBeFalse()
        ->and($result->retryable)->toBeTrue()
        ->and($result->status)->toBeNull();
});

it('reads cursor meta', function () {
    expect(CursorMeta::fromArray(['limit' => 50, 'cursor' => 'abc'])->hasMore())->toBeTrue()
        ->and(CursorMeta::fromArray(['limit' => 50])->hasMore())->toBeFalse()
        ->and(CursorMeta::fromArray([])->limit)->toBeNull()
        ->and(CursorMeta::fromArray(['limit' => 'nope'])->limit)->toBeNull();
});
