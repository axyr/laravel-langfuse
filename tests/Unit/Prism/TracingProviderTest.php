<?php

declare(strict_types=1);

use Axyr\Langfuse\Cache\PromptCache;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\PromptApiClientInterface;
use Axyr\Langfuse\Contracts\ScoreApiClientInterface;
use Axyr\Langfuse\Dto\IngestionEvent;
use Axyr\Langfuse\LangfuseClient;
use Axyr\Langfuse\Prism\TracingProvider;
use Axyr\Langfuse\Prompt\PromptManager;
use Axyr\Langfuse\Testing\RecordingEventBatcher;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Providers\Provider;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Text\Request as TextRequest;
use Prism\Prism\Text\Response as TextResponse;
use Prism\Prism\Text\Step;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage as PrismUsage;

function makeTracingClient(): array
{
    $batcher = new RecordingEventBatcher();

    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');
    $promptApiClient = Mockery::mock(PromptApiClientInterface::class);
    $promptManager = new PromptManager(
        $promptApiClient,
        new PromptCache(),
    );

    $client = new LangfuseClient(
        $batcher,
        $config,
        $promptManager,
        Mockery::mock(ScoreApiClientInterface::class),
        $promptApiClient,
    );

    return [$client, $batcher];
}

function makeTextRequest(string $model = 'gpt-4', string $provider = 'openai'): TextRequest
{
    return new TextRequest(
        model: $model,
        providerKey: $provider,
        systemPrompts: [],
        prompt: 'Hello',
        messages: [],
        maxSteps: 1,
        maxTokens: 100,
        temperature: 0.7,
        topP: null,
        tools: [],
        clientOptions: [],
        clientRetry: [0],
        toolChoice: null,
    );
}

function makeTextResponse(string $text = 'Hello world'): TextResponse
{
    return new TextResponse(
        steps: collect([
            new Step(
                text: $text,
                finishReason: FinishReason::Stop,
                toolCalls: [],
                toolResults: [],
                providerToolCalls: [],
                usage: new PrismUsage(promptTokens: 10, completionTokens: 20),
                meta: new Meta(id: 'test', model: 'gpt-4', rateLimits: []),
                messages: [],
                systemPrompts: [],
                additionalContent: [],
                raw: [],
            ),
        ]),
        text: $text,
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new PrismUsage(promptTokens: 10, completionTokens: 20),
        meta: new Meta(id: 'test', model: 'gpt-4', rateLimits: []),
        messages: collect(),
        additionalContent: [],
        raw: [],
    );
}

it('traces text generation', function () {
    [$langfuse, $batcher] = makeTracingClient();

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('text')
        ->once()
        ->andReturn(makeTextResponse('Generated text'));

    $provider = new TracingProvider($innerProvider, $langfuse);
    $response = $provider->text(makeTextRequest());

    expect($response->text)->toBe('Generated text')
        ->and($batcher->events())->toHaveCount(3); // trace-create, generation-create, generation-update

    $types = array_map(fn(IngestionEvent $e) => $e->type->value, $batcher->events());
    expect($types)->toContain('trace-create')
        ->and($types)->toContain('generation-create')
        ->and($types)->toContain('generation-update');
});

it('captures usage data in generation', function () {
    [$langfuse, $batcher] = makeTracingClient();

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('text')
        ->once()
        ->andReturn(makeTextResponse());

    $provider = new TracingProvider($innerProvider, $langfuse);
    $provider->text(makeTextRequest());

    $updateEvent = collect($batcher->events())->first(fn(IngestionEvent $e) => $e->type->value === 'generation-update');
    $body = $updateEvent->body->toArray();

    expect($body)->toHaveKey('usage')
        ->and($body['usage']['input'])->toBe(10)
        ->and($body['usage']['output'])->toBe(20)
        ->and($body['usage']['total'])->toBe(30);
});

it('captures model name in generation', function () {
    [$langfuse, $batcher] = makeTracingClient();

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('text')
        ->once()
        ->andReturn(makeTextResponse());

    $provider = new TracingProvider($innerProvider, $langfuse);
    $provider->text(makeTextRequest('claude-3-opus', 'anthropic'));

    $createEvent = collect($batcher->events())->first(fn(IngestionEvent $e) => $e->type->value === 'generation-create');
    $body = $createEvent->body->toArray();

    expect($body['model'])->toBe('claude-3-opus');
});

it('records error on text generation failure', function () {
    [$langfuse, $batcher] = makeTracingClient();

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('text')
        ->once()
        ->andThrow(new RuntimeException('API Error'));

    $provider = new TracingProvider($innerProvider, $langfuse);

    try {
        $provider->text(makeTextRequest());
    } catch (RuntimeException) {
        // expected
    }

    expect($batcher->events())->toHaveCount(3);

    $updateEvent = collect($batcher->events())->first(fn(IngestionEvent $e) => $e->type->value === 'generation-update');
    $body = $updateEvent->body->toArray();

    expect($body['level'])->toBe('ERROR')
        ->and($body['statusMessage'])->toBe('API Error');
});

it('re-throws exceptions after recording', function () {
    [$langfuse] = makeTracingClient();

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('text')
        ->once()
        ->andThrow(new RuntimeException('API Error'));

    $provider = new TracingProvider($innerProvider, $langfuse);

    expect(fn() => $provider->text(makeTextRequest()))
        ->toThrow(RuntimeException::class, 'API Error');
});

it('traces streaming generation', function () {
    [$langfuse, $batcher] = makeTracingClient();

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('stream')
        ->once()
        ->andReturnUsing(function () {
            yield new TextDeltaEvent(id: '1', timestamp: time(), delta: 'Hello ', messageId: 'msg-1');
            yield new TextDeltaEvent(id: '2', timestamp: time(), delta: 'world', messageId: 'msg-1');
            yield new StreamEndEvent(
                id: '3',
                timestamp: time(),
                finishReason: FinishReason::Stop,
                usage: new PrismUsage(promptTokens: 5, completionTokens: 10),
            );
        });

    $provider = new TracingProvider($innerProvider, $langfuse);
    $generator = $provider->stream(makeTextRequest());

    $streamEvents = [];
    foreach ($generator as $event) {
        $streamEvents[] = $event;
    }

    expect($streamEvents)->toHaveCount(3)
        ->and($batcher->events())->toHaveCount(3);

    $updateEvent = collect($batcher->events())->first(fn(IngestionEvent $e) => $e->type->value === 'generation-update');
    $body = $updateEvent->body->toArray();

    expect($body['usage']['input'])->toBe(5)
        ->and($body['usage']['output'])->toBe(10);
});

it('captures model parameters', function () {
    [$langfuse, $batcher] = makeTracingClient();

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('text')
        ->once()
        ->andReturn(makeTextResponse());

    $request = new TextRequest(
        model: 'gpt-4',
        providerKey: 'openai',
        systemPrompts: [],
        prompt: 'test',
        messages: [],
        maxSteps: 1,
        maxTokens: 200,
        temperature: 0.5,
        topP: 0.9,
        tools: [],
        clientOptions: [],
        clientRetry: [0],
        toolChoice: null,
    );

    $provider = new TracingProvider($innerProvider, $langfuse);
    $provider->text($request);

    $createEvent = collect($batcher->events())->first(fn(IngestionEvent $e) => $e->type->value === 'generation-create');
    $body = $createEvent->body->toArray();

    expect($body['modelParameters'])->toBe([
        'temperature' => 0.5,
        'maxTokens' => 200,
        'topP' => 0.9,
    ]);
});

it('passes through stream events unmodified', function () {
    [$langfuse] = makeTracingClient();

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('stream')
        ->once()
        ->andReturnUsing(function () {
            yield new TextDeltaEvent(id: '1', timestamp: time(), delta: 'Hi', messageId: 'msg-1');
            yield new StreamEndEvent(
                id: '2',
                timestamp: time(),
                finishReason: FinishReason::Stop,
            );
        });

    $provider = new TracingProvider($innerProvider, $langfuse);
    $streamEvents = iterator_to_array($provider->stream(makeTextRequest()));

    expect($streamEvents[0])->toBeInstanceOf(TextDeltaEvent::class)
        ->and($streamEvents[0]->delta)->toBe('Hi')
        ->and($streamEvents[1])->toBeInstanceOf(StreamEndEvent::class);
});

it('reuses existing trace across multiple calls', function () {
    [$langfuse, $batcher] = makeTracingClient();

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('text')
        ->twice()
        ->andReturn(makeTextResponse());

    $provider = new TracingProvider($innerProvider, $langfuse);

    // First call creates a trace
    $provider->text(makeTextRequest());

    // Second call should reuse the same trace
    $provider->text(makeTextRequest());

    $traceEvents = collect($batcher->events())->filter(fn(IngestionEvent $e) => $e->type->value === 'trace-create');
    $generationEvents = collect($batcher->events())->filter(fn(IngestionEvent $e) => $e->type->value === 'generation-create');

    // Only 1 trace created, but 2 generations
    expect($traceEvents)->toHaveCount(1)
        ->and($generationEvents)->toHaveCount(2);

    // Both generations should reference the same trace
    $traceId = $traceEvents->first()->body->toArray()['id'];
    $genTraceIds = $generationEvents->map(fn(IngestionEvent $e) => $e->body->toArray()['traceId'])->all();

    expect($genTraceIds)->each->toBe($traceId);
});

it('creates new trace when no current trace exists', function () {
    [$langfuse, $batcher] = makeTracingClient();

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('text')
        ->once()
        ->andReturn(makeTextResponse());

    $provider = new TracingProvider($innerProvider, $langfuse);
    $provider->text(makeTextRequest());

    // Should have set current trace on the client
    expect($langfuse->currentTrace())->not->toBeNull();
});

it('delegates embeddings to inner provider', function () {
    [$langfuse, $batcher] = makeTracingClient();

    $embeddingsResponse = new \Prism\Prism\Embeddings\Response(
        embeddings: [],
        usage: new \Prism\Prism\ValueObjects\EmbeddingsUsage(tokens: 0),
        meta: new Meta(id: 'test', model: 'text-embedding-3-small', rateLimits: []),
    );

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('embeddings')
        ->once()
        ->andReturn($embeddingsResponse);

    $provider = new TracingProvider($innerProvider, $langfuse);
    $request = Mockery::mock(\Prism\Prism\Embeddings\Request::class);
    $response = $provider->embeddings($request);

    expect($response)->toBe($embeddingsResponse)
        ->and($batcher->events())->toBeEmpty();
});

it('delegates images to inner provider', function () {
    [$langfuse, $batcher] = makeTracingClient();

    $imagesResponse = new \Prism\Prism\Images\Response(
        images: [],
        usage: new PrismUsage(promptTokens: 0, completionTokens: 0),
        meta: new Meta(id: 'test', model: 'dall-e-3', rateLimits: []),
    );

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('images')
        ->once()
        ->andReturn($imagesResponse);

    $provider = new TracingProvider($innerProvider, $langfuse);
    $request = Mockery::mock(\Prism\Prism\Images\Request::class);
    $response = $provider->images($request);

    expect($response)->toBe($imagesResponse)
        ->and($batcher->events())->toBeEmpty();
});
