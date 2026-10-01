<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\EventBody;
use Axyr\Langfuse\Dto\GenerationBody;
use Axyr\Langfuse\Dto\IdGenerator;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Dto\Usage;
use Axyr\Langfuse\Enums\ScoreDataType;
use Axyr\Langfuse\LangfuseFacade;
use Illuminate\Support\Facades\Http;

const OTEL_ENDPOINT = 'e2e.langfuse.com/api/public/otel/v1/traces';
const INGESTION_ENDPOINT = 'e2e.langfuse.com/api/public/ingestion';

beforeEach(function () {
    config([
        'langfuse.public_key' => 'pk-e2e-test',
        'langfuse.secret_key' => 'sk-e2e-test',
        'langfuse.base_url' => 'https://e2e.langfuse.com',
        'langfuse.enabled' => true,
        'langfuse.flush_at' => 100,
        'langfuse.service_name' => 'e2e-app',
    ]);

    // Reset singletons with new config
    $this->app->forgetInstance(\Axyr\Langfuse\Config\LangfuseConfig::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Contracts\EventBatcherInterface::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Contracts\IngestionApiClientInterface::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Contracts\OtelTraceApiClientInterface::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Contracts\ScoreApiClientInterface::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Contracts\LangfuseClientInterface::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Objects\OpenObservationRegistry::class);
});

/**
 * @return array<int, array<string, mixed>>
 */
function sentSpans(\Illuminate\Http\Client\Request $request): array
{
    /** @var array<string, mixed> $body */
    $body = json_decode($request->body(), true);

    return $body['resourceSpans'][0]['scopeSpans'][0]['spans'];
}

/**
 * @param  array<string, mixed>  $span
 * @return array<string, mixed>
 */
function sentAttributes(array $span): array
{
    $attributes = [];

    foreach ($span['attributes'] as $attribute) {
        $attributes[$attribute['key']] = array_values($attribute['value'])[0];
    }

    return $attributes;
}

it('sends the complete trace with nested observations to the otlp endpoint on flush', function () {
    Http::fake([OTEL_ENDPOINT => Http::response([])]);

    $trace = LangfuseFacade::trace(new TraceBody(
        id: 'trace-e2e',
        name: 'e2e-test',
        userId: 'user-1',
        metadata: ['env' => 'test'],
        tags: ['e2e'],
    ));

    $span = $trace->span(new SpanBody(
        id: 'span-e2e',
        name: 'processing',
        startTime: '2024-01-01T00:00:00Z',
    ));

    $generation = $span->generation(new GenerationBody(
        id: 'gen-e2e',
        name: 'chat-completion',
        model: 'gpt-4',
        input: [['role' => 'user', 'content' => 'Hello']],
        startTime: '2024-01-01T00:00:00.100Z',
    ));

    $generation->end(
        output: ['role' => 'assistant', 'content' => 'Hi!'],
        usage: new Usage(input: 10, output: 5, total: 15),
    );

    $span->end(endTime: '2024-01-01T00:00:01Z', output: 'completed');
    $trace->end(endTime: '2024-01-01T00:00:02Z', output: 'done');

    LangfuseFacade::flush();

    Http::assertSent(function ($request) {
        $expectedAuth = 'Basic ' . base64_encode('pk-e2e-test:sk-e2e-test');

        if ($request->url() !== 'https://e2e.langfuse.com/api/public/otel/v1/traces') {
            return false;
        }

        if (! $request->hasHeader('Authorization', $expectedAuth)
            || ! $request->hasHeader('x-langfuse-ingestion-version', '4')) {
            return false;
        }

        $names = array_column(sentSpans($request), 'name');

        return $names === ['chat-completion', 'processing', 'e2e-test'];
    });
});

it('exports each observation exactly once', function () {
    Http::fake([OTEL_ENDPOINT => Http::response([])]);

    $trace = LangfuseFacade::trace(new TraceBody(id: 'trace-once', name: 'once'));
    $trace->span(new SpanBody(name: 'work'))->end();
    $trace->end();

    LangfuseFacade::flush();

    Http::assertSentCount(1);
    Http::assertSent(fn($request) => count(sentSpans($request)) === 2);
});

it('copies the trace-level attributes onto every span', function () {
    Http::fake([OTEL_ENDPOINT => Http::response([])]);

    $trace = LangfuseFacade::trace(new TraceBody(
        id: 'trace-attrs',
        name: 'attrs',
        userId: 'user-1',
        sessionId: 'session-1',
        tags: ['e2e'],
    ));

    $trace->span(new SpanBody(name: 'work'))->end();
    $trace->end();

    LangfuseFacade::flush();

    Http::assertSent(function ($request) {
        foreach (sentSpans($request) as $span) {
            $attributes = sentAttributes($span);

            if (($attributes['langfuse.user.id'] ?? null) !== 'user-1'
                || ($attributes['langfuse.session.id'] ?? null) !== 'session-1'
                || ($attributes['langfuse.trace.name'] ?? null) !== 'attrs') {
                return false;
            }
        }

        return true;
    });
});

it('sends an event to otlp and a score to the ingestion endpoint', function () {
    Http::fake([
        OTEL_ENDPOINT => Http::response([]),
        INGESTION_ENDPOINT => Http::response(['successes' => [], 'errors' => []]),
    ]);

    $trace = LangfuseFacade::trace(new TraceBody(id: 'trace-events', name: 'event-test'));

    $trace->event(new EventBody(id: 'event-1', name: 'user-action', input: ['action' => 'click']));

    $trace->score(new ScoreBody(
        id: 'score-1',
        name: 'satisfaction',
        value: 4.5,
        dataType: ScoreDataType::NUMERIC,
    ));

    LangfuseFacade::flush();

    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://e2e.langfuse.com/api/public/otel/v1/traces') {
            return false;
        }

        $spans = sentSpans($request);

        return count($spans) === 1
            && $spans[0]['name'] === 'user-action'
            && sentAttributes($spans[0])['langfuse.observation.type'] === 'event';
    });

    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://e2e.langfuse.com/api/public/ingestion') {
            return false;
        }

        $batch = $request->data()['batch'];

        return count($batch) === 1
            && $batch[0]['type'] === 'score-create'
            && $batch[0]['body']['name'] === 'satisfaction'
            && $batch[0]['body']['traceId'] === IdGenerator::traceIdFromSeed('trace-events');
    });
});

it('validates the payload structure matches the otlp contract', function () {
    Http::fake([OTEL_ENDPOINT => Http::response([])]);

    $trace = LangfuseFacade::trace(new TraceBody(
        id: 'trace-structure',
        name: 'structure-test',
        input: ['prompt' => 'test'],
        metadata: ['key' => 'value'],
    ));

    $trace->end(output: ['response' => 'test']);

    LangfuseFacade::flush();

    Http::assertSent(function ($request) {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->body(), true);

        if (array_keys($payload) !== ['resourceSpans']) {
            return false;
        }

        $resourceSpan = $payload['resourceSpans'][0];
        $span = $resourceSpan['scopeSpans'][0]['spans'][0];
        $attributes = sentAttributes($span);

        return $resourceSpan['scopeSpans'][0]['scope']['name'] === 'langfuse-php'
            && $span['traceId'] === IdGenerator::traceIdFromSeed('trace-structure')
            && preg_match('/^[0-9a-f]{16}$/', $span['spanId']) === 1
            && ! isset($span['parentSpanId'])
            && $span['kind'] === 1
            && is_string($span['startTimeUnixNano'])
            && is_string($span['endTimeUnixNano'])
            && $span['name'] === 'structure-test'
            && $attributes['langfuse.observation.input'] === '{"prompt":"test"}'
            && $attributes['langfuse.observation.output'] === '{"response":"test"}'
            && $attributes['langfuse.trace.metadata.key'] === 'value';
    });
});

it('handles deeply nested spans', function () {
    Http::fake([OTEL_ENDPOINT => Http::response([])]);

    $trace = LangfuseFacade::trace(new TraceBody(id: 'trace-nested'));

    $parentSpan = $trace->span(new SpanBody(id: 'span-parent', name: 'parent'));
    $childSpan = $parentSpan->span(new SpanBody(id: 'span-child', name: 'child'));

    $childSpan->end(output: 'child done');
    $parentSpan->end(output: 'parent done');
    $trace->end();

    LangfuseFacade::flush();

    Http::assertSent(function ($request) {
        $spans = collect(sentSpans($request))->keyBy('name');

        return $spans['child']['parentSpanId'] === $spans['parent']['spanId']
            && $spans['parent']['parentSpanId'] !== $spans['parent']['spanId']
            && $spans['child']['traceId'] === IdGenerator::traceIdFromSeed('trace-nested')
            && $spans['parent']['spanId'] === IdGenerator::spanIdFromSeed('span-parent')
            && $spans['child']['spanId'] === IdGenerator::spanIdFromSeed('span-child');
    });
});

it('ends the still open observations when the application terminates', function () {
    Http::fake([OTEL_ENDPOINT => Http::response([])]);

    $trace = LangfuseFacade::trace(new TraceBody(id: 'trace-terminate', name: 'terminate'));
    $trace->span(new SpanBody(name: 'unfinished'));

    $this->app->terminate();

    Http::assertSent(function ($request) {
        $spans = collect(sentSpans($request))->keyBy('name');

        return $spans->has('unfinished')
            && $spans->has('terminate')
            && sentAttributes($spans['unfinished'])['langfuse.observation.status_message'] === 'ended by shutdown';
    });
});
