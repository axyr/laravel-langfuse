<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Langfuse\Dto\EventBody;
use Langfuse\Dto\GenerationBody;
use Langfuse\Dto\ScoreBody;
use Langfuse\Dto\SpanBody;
use Langfuse\Dto\TraceBody;
use Langfuse\Dto\Usage;
use Langfuse\Enums\ScoreDataType;
use Langfuse\LangfuseFacade;

beforeEach(function () {
    config([
        'langfuse.public_key' => 'pk-e2e-test',
        'langfuse.secret_key' => 'sk-e2e-test',
        'langfuse.base_url' => 'https://e2e.langfuse.com',
        'langfuse.enabled' => true,
        'langfuse.flush_at' => 100,
    ]);

    // Reset singletons with new config
    $this->app->forgetInstance(\Langfuse\Config\LangfuseConfig::class);
    $this->app->forgetInstance(\Langfuse\Contracts\EventBatcherInterface::class);
    $this->app->forgetInstance(\Langfuse\Contracts\IngestionApiClientInterface::class);
    $this->app->forgetInstance(\Langfuse\Contracts\LangfuseClientInterface::class);
});

it('sends complete trace with nested observations on flush', function () {
    Http::fake([
        'e2e.langfuse.com/api/public/ingestion' => Http::response([
            'successes' => [
                ['id' => 'evt-1', 'status' => 201],
                ['id' => 'evt-2', 'status' => 201],
                ['id' => 'evt-3', 'status' => 201],
                ['id' => 'evt-4', 'status' => 201],
                ['id' => 'evt-5', 'status' => 201],
            ],
            'errors' => [],
        ]),
    ]);

    // Create trace
    $trace = LangfuseFacade::trace(new TraceBody(
        id: 'trace-e2e',
        name: 'e2e-test',
        userId: 'user-1',
        metadata: ['env' => 'test'],
        tags: ['e2e'],
    ));

    // Create span under trace
    $span = $trace->span(new SpanBody(
        id: 'span-e2e',
        name: 'processing',
        startTime: '2024-01-01T00:00:00Z',
    ));

    // Create generation under span
    $generation = $span->generation(new GenerationBody(
        id: 'gen-e2e',
        name: 'chat-completion',
        model: 'gpt-4',
        input: [['role' => 'user', 'content' => 'Hello']],
        startTime: '2024-01-01T00:00:00.100Z',
    ));

    // End generation
    $generation->end(
        output: ['role' => 'assistant', 'content' => 'Hi!'],
        usage: new Usage(input: 10, output: 5, total: 15),
    );

    // End span
    $span->end(
        endTime: '2024-01-01T00:00:01Z',
        output: 'completed',
    );

    // Flush all events
    LangfuseFacade::flush();

    // Verify HTTP was called
    Http::assertSent(function ($request) {
        $data = $request->data();

        // Should have batch with multiple events
        if (! isset($data['batch']) || ! is_array($data['batch'])) {
            return false;
        }

        // Verify auth header
        $expectedAuth = 'Basic ' . base64_encode('pk-e2e-test:sk-e2e-test');
        if (! $request->hasHeader('Authorization', $expectedAuth)) {
            return false;
        }

        // Verify URL
        if ($request->url() !== 'https://e2e.langfuse.com/api/public/ingestion') {
            return false;
        }

        // Collect event types
        $types = array_column($data['batch'], 'type');

        // Should have trace-create, span-create, generation-create, generation-update, span-update
        return in_array('trace-create', $types)
            && in_array('span-create', $types)
            && in_array('generation-create', $types)
            && in_array('generation-update', $types)
            && in_array('span-update', $types);
    });
});

it('sends trace with event and score on flush', function () {
    Http::fake([
        'e2e.langfuse.com/api/public/ingestion' => Http::response([
            'successes' => [],
            'errors' => [],
        ]),
    ]);

    $trace = LangfuseFacade::trace(new TraceBody(
        id: 'trace-events',
        name: 'event-test',
    ));

    // Add event
    $trace->event(new EventBody(
        id: 'event-1',
        name: 'user-action',
        input: ['action' => 'click'],
    ));

    // Add score
    $trace->score(new ScoreBody(
        id: 'score-1',
        name: 'satisfaction',
        value: 4.5,
        dataType: ScoreDataType::NUMERIC,
    ));

    LangfuseFacade::flush();

    Http::assertSent(function ($request) {
        $data = $request->data();
        $types = array_column($data['batch'], 'type');

        return in_array('trace-create', $types)
            && in_array('event-create', $types)
            && in_array('score-create', $types);
    });
});

it('validates complete payload structure matches API contract', function () {
    Http::fake([
        'e2e.langfuse.com/api/public/ingestion' => Http::response([
            'successes' => [['id' => 'evt-1', 'status' => 201]],
            'errors' => [],
        ]),
    ]);

    LangfuseFacade::trace(new TraceBody(
        id: 'trace-structure',
        name: 'structure-test',
        input: ['prompt' => 'test'],
        output: ['response' => 'test'],
        metadata: ['key' => 'value'],
    ));

    LangfuseFacade::flush();

    Http::assertSent(function ($request) {
        $data = $request->data();

        // Top-level must have batch and metadata keys
        if (! isset($data['batch']) || ! isset($data['metadata'])) {
            return false;
        }

        $event = $data['batch'][0];

        // Each event must have id, type, timestamp, body
        return isset($event['id'])
            && isset($event['type'])
            && isset($event['timestamp'])
            && isset($event['body'])
            && $event['body']['id'] === 'trace-structure'
            && $event['body']['name'] === 'structure-test';
    });
});

it('handles deeply nested spans', function () {
    Http::fake([
        'e2e.langfuse.com/api/public/ingestion' => Http::response([
            'successes' => [],
            'errors' => [],
        ]),
    ]);

    $trace = LangfuseFacade::trace(new TraceBody(id: 'trace-nested'));

    $parentSpan = $trace->span(new SpanBody(id: 'span-parent', name: 'parent'));
    $childSpan = $parentSpan->span(new SpanBody(id: 'span-child', name: 'child'));

    $childSpan->end(output: 'child done');
    $parentSpan->end(output: 'parent done');

    LangfuseFacade::flush();

    Http::assertSent(function ($request) {
        $data = $request->data();
        $bodies = array_column($data['batch'], 'body');

        // Find the child span create event
        foreach ($bodies as $body) {
            if (($body['id'] ?? '') === 'span-child' && isset($body['parentObservationId'])) {
                return $body['parentObservationId'] === 'span-parent'
                    && $body['traceId'] === 'trace-nested';
            }
        }

        return false;
    });
});
