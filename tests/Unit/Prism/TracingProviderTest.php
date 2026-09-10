<?php

declare(strict_types=1);

use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Dto\TextPrompt;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Objects\LangfuseGeneration;
use Axyr\Langfuse\Objects\LangfuseTrace;
use Axyr\Langfuse\Objects\NullLangfuseTrace;
use Axyr\Langfuse\Prism\TracingProvider;
use Axyr\Langfuse\Prompt\CurrentPromptRegistry;
use Axyr\Langfuse\Testing\LangfuseFake;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Providers\Provider;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Text\Request as TextRequest;
use Prism\Prism\Text\Response as TextResponse;
use Prism\Prism\Text\Step;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage as PrismUsage;

function makeTracingClient(): LangfuseFake
{
    return new LangfuseFake(
        new CurrentPromptRegistry(),
        new LangfuseConfig(publicKey: 'pk', secretKey: 'sk'),
    );
}

/**
 * @return array<int, array<string, mixed>>
 */
function prismTraceBodies(LangfuseFake $fake): array
{
    return array_map(fn(LangfuseTrace $trace): array => $trace->getBody()->toArray(), $fake->traces());
}

/**
 * @return array<int, array<string, mixed>>
 */
function prismGenerationBodies(LangfuseFake $fake): array
{
    return array_map(fn(LangfuseGeneration $g): array => $g->getBody()->toArray(), $fake->generations());
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

it('traces text generation and ends the trace it owns', function () {
    $fake = makeTracingClient();

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('text')
        ->once()
        ->andReturn(makeTextResponse('Generated text'));

    $provider = new TracingProvider($innerProvider, $fake);
    $response = $provider->text(makeTextRequest());

    expect($response->text)->toBe('Generated text');

    $fake->assertTraceCreated()
        ->assertTraceEnded()
        ->assertGenerationEnded()
        ->assertEventCount(2);

    expect(prismTraceBodies($fake)[0]['output'])->toBe('Generated text')
        ->and($fake->currentTrace())->toBeInstanceOf(NullLangfuseTrace::class);
});

it('captures usage data in generation', function () {
    $fake = makeTracingClient();

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('text')->once()->andReturn(makeTextResponse());

    (new TracingProvider($innerProvider, $fake))->text(makeTextRequest());

    $body = prismGenerationBodies($fake)[0];

    expect($body)->toHaveKey('usage')
        ->and($body['usage']['input'])->toBe(10)
        ->and($body['usage']['output'])->toBe(20)
        ->and($body['usage']['total'])->toBe(30);
});

it('captures model name in generation', function () {
    $fake = makeTracingClient();

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('text')->once()->andReturn(makeTextResponse());

    (new TracingProvider($innerProvider, $fake))->text(makeTextRequest('claude-3-opus', 'anthropic'));

    expect(prismGenerationBodies($fake)[0]['model'])->toBe('claude-3-opus');
});

it('records error on text generation failure', function () {
    $fake = makeTracingClient();

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('text')->once()->andThrow(new RuntimeException('API Error'));

    $provider = new TracingProvider($innerProvider, $fake);

    try {
        $provider->text(makeTextRequest());
    } catch (RuntimeException) {
        // expected
    }

    $fake->assertEventCount(2);

    $body = prismGenerationBodies($fake)[0];

    expect($body['level'])->toBe('ERROR')
        ->and($body['statusMessage'])->toBe('API Error')
        ->and(prismTraceBodies($fake)[0]['metadata']['error'])->toBe('API Error')
        ->and($fake->traces()[0]->hasEnded())->toBeTrue();
});

it('re-throws exceptions after recording', function () {
    $fake = makeTracingClient();

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('text')->once()->andThrow(new RuntimeException('API Error'));

    $provider = new TracingProvider($innerProvider, $fake);

    expect(fn() => $provider->text(makeTextRequest()))
        ->toThrow(RuntimeException::class, 'API Error');
});

it('traces streaming generation', function () {
    $fake = makeTracingClient();

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

    $provider = new TracingProvider($innerProvider, $fake);

    $streamEvents = [];
    foreach ($provider->stream(makeTextRequest()) as $event) {
        $streamEvents[] = $event;
    }

    expect($streamEvents)->toHaveCount(3);

    $fake->assertEventCount(2);

    $body = prismGenerationBodies($fake)[0];

    expect($body['usage']['input'])->toBe(5)
        ->and($body['usage']['output'])->toBe(10)
        ->and($body['output'])->toBe('Hello world');
});

it('captures model parameters', function () {
    $fake = makeTracingClient();

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('text')->once()->andReturn(makeTextResponse());

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

    (new TracingProvider($innerProvider, $fake))->text($request);

    expect(prismGenerationBodies($fake)[0]['modelParameters'])->toBe([
        'temperature' => 0.5,
        'maxTokens' => 200,
        'topP' => 0.9,
    ]);
});

it('passes through stream events unmodified', function () {
    $fake = makeTracingClient();

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

    $provider = new TracingProvider($innerProvider, $fake);
    $streamEvents = iterator_to_array($provider->stream(makeTextRequest()));

    expect($streamEvents[0])->toBeInstanceOf(TextDeltaEvent::class)
        ->and($streamEvents[0]->delta)->toBe('Hi')
        ->and($streamEvents[1])->toBeInstanceOf(StreamEndEvent::class);
});

it('reuses an adopted trace across multiple calls and leaves it open', function () {
    $fake = makeTracingClient();

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('text')->twice()->andReturn(makeTextResponse());

    $trace = $fake->trace(new TraceBody(name: 'workflow'));
    $fake->setCurrentTrace($trace);

    $provider = new TracingProvider($innerProvider, $fake);
    $provider->text(makeTextRequest());
    $provider->text(makeTextRequest());

    expect($fake->traces())->toHaveCount(1)
        ->and($fake->generations())->toHaveCount(2)
        ->and($trace->hasEnded())->toBeFalse();

    foreach (prismGenerationBodies($fake) as $body) {
        expect($body['traceId'])->toBe($trace->getId());
    }
});

it('owns one trace per call when there is no current trace', function () {
    $fake = makeTracingClient();

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('text')->twice()->andReturn(makeTextResponse());

    $provider = new TracingProvider($innerProvider, $fake);
    $provider->text(makeTextRequest());
    $provider->text(makeTextRequest());

    expect($fake->traces())->toHaveCount(2)
        ->and($fake->traces()[0]->hasEnded())->toBeTrue()
        ->and($fake->traces()[1]->hasEnded())->toBeTrue();
});

it('sets the current trace while the call is in flight', function () {
    $fake = makeTracingClient();

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('text')->once()->andReturn(makeTextResponse());

    (new TracingProvider($innerProvider, $fake))->text(makeTextRequest());

    expect($fake->traces())->toHaveCount(1)
        ->and(prismTraceBodies($fake)[0]['name'])->toStartWith('prism-');
});

it('delegates embeddings to inner provider', function () {
    $fake = makeTracingClient();

    $embeddingsResponse = new \Prism\Prism\Embeddings\Response(
        embeddings: [],
        usage: new \Prism\Prism\ValueObjects\EmbeddingsUsage(tokens: 0),
        meta: new Meta(id: 'test', model: 'text-embedding-3-small', rateLimits: []),
    );

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('embeddings')->once()->andReturn($embeddingsResponse);

    $provider = new TracingProvider($innerProvider, $fake);
    $response = $provider->embeddings(Mockery::mock(\Prism\Prism\Embeddings\Request::class));

    expect($response)->toBe($embeddingsResponse);

    $fake->assertNothingSent();
});

it('delegates images to inner provider', function () {
    $fake = makeTracingClient();

    $imagesResponse = new \Prism\Prism\Images\Response(
        images: [],
        usage: new PrismUsage(promptTokens: 0, completionTokens: 0),
        meta: new Meta(id: 'test', model: 'dall-e-3', rateLimits: []),
    );

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('images')->once()->andReturn($imagesResponse);

    $provider = new TracingProvider($innerProvider, $fake);
    $response = $provider->images(Mockery::mock(\Prism\Prism\Images\Request::class));

    expect($response)->toBe($imagesResponse);

    $fake->assertNothingSent();
});

it('links the registered managed prompt to the generation', function () {
    $fake = makeTracingClient();
    $registry = new CurrentPromptRegistry();
    $registry->set(new TextPrompt(name: 'movie-critic', version: 7, prompt: 'text'));

    $innerProvider = Mockery::mock(Provider::class);
    $innerProvider->shouldReceive('text')->twice()->andReturn(makeTextResponse());

    $provider = new TracingProvider($innerProvider, $fake, $registry);
    $provider->text(makeTextRequest());
    $provider->text(makeTextRequest());

    $bodies = prismGenerationBodies($fake);

    expect($bodies[0]['promptName'])->toBe('movie-critic')
        ->and($bodies[0]['promptVersion'])->toBe(7)
        ->and($bodies[1])->not->toHaveKey('promptName')
        ->and($registry->current())->toBeNull();
});
