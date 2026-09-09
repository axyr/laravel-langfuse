<?php

declare(strict_types=1);

use Axyr\Langfuse\Cache\PromptCache;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\PromptApiClientInterface;
use Axyr\Langfuse\Contracts\ScoreApiClientInterface;
use Axyr\Langfuse\Dto\IngestionEvent;
use Axyr\Langfuse\Dto\TextPrompt;
use Axyr\Langfuse\LangfuseClient;
use Axyr\Langfuse\LaravelAi\LaravelAiSubscriber;
use Axyr\Langfuse\Objects\NullLangfuseTrace;
use Axyr\Langfuse\Prompt\CurrentPromptRegistry;
use Axyr\Langfuse\Prompt\PromptManager;
use Axyr\Langfuse\Testing\RecordingEventBatcher;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StreamingAgent;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StreamedAgentResponse;

function makeLangfuseClient(): array
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
        Mockery::mock(\Axyr\Langfuse\Contracts\ObservationApiClientInterface::class),
        Mockery::mock(\Axyr\Langfuse\Contracts\MetricsApiClientInterface::class),
        Mockery::mock(\Axyr\Langfuse\Contracts\DatasetApiClientInterface::class),
        Mockery::mock(\Axyr\Langfuse\Contracts\DatasetItemApiClientInterface::class),
        Mockery::mock(\Axyr\Langfuse\Contracts\DatasetRunApiClientInterface::class),
    );

    return [$client, $batcher];
}

function makeAgentPrompt(string $model = 'gpt-4', ?Agent $agent = null): AgentPrompt
{
    return new AgentPrompt(
        agent: $agent ?? makeTestAgent(),
        prompt: 'Tell me a joke',
        provider: makeTestProvider(),
        model: $model,
    );
}

function makeAgentResponse(
    string $invocationId = 'inv-1',
    string $text = 'Hello world',
    int $promptTokens = 10,
    int $completionTokens = 20,
    ?string $model = 'gpt-4',
    ?string $provider = 'openai',
): AgentResponse {
    return new AgentResponse(
        invocationId: $invocationId,
        text: $text,
        usage: new Usage(promptTokens: $promptTokens, completionTokens: $completionTokens),
        meta: new Meta(provider: $provider, model: $model),
    );
}

function makeTestAgent(): Agent
{
    return new class () implements Agent {};
}

function makeTestProvider(): TextProvider
{
    return new class () implements TextProvider {};
}

function makeTestTool(): Tool
{
    return new class () implements Tool {};
}

it('registers correct event mappings in subscribe', function () {
    [$client] = makeLangfuseClient();
    $subscriber = new LaravelAiSubscriber($client);

    $dispatcher = Mockery::mock(\Illuminate\Events\Dispatcher::class);

    $result = $subscriber->subscribe($dispatcher);

    expect($result)->toBe([
        PromptingAgent::class => 'handlePromptingAgent',
        StreamingAgent::class => 'handlePromptingAgent',
        AgentPrompted::class => 'handleAgentPrompted',
        AgentStreamed::class => 'handleAgentPrompted',
        InvokingTool::class => 'handleInvokingTool',
        ToolInvoked::class => 'handleToolInvoked',
    ]);
});

it('creates trace and generation on agent prompt', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = new LaravelAiSubscriber($client);

    $prompt = makeAgentPrompt();

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    $subscriber->handleAgentPrompted(new AgentPrompted(
        invocationId: 'inv-1',
        prompt: $prompt,
        response: makeAgentResponse(),
    ));

    expect($batcher->events())->toHaveCount(3); // trace-create, generation-create, generation-update

    $types = array_map(fn(IngestionEvent $e) => $e->type->value, $batcher->events());
    expect($types)->toContain('trace-create')
        ->and($types)->toContain('generation-create')
        ->and($types)->toContain('generation-update');
});

it('captures usage data in generation', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = new LaravelAiSubscriber($client);

    $prompt = makeAgentPrompt();

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    $subscriber->handleAgentPrompted(new AgentPrompted(
        invocationId: 'inv-1',
        prompt: $prompt,
        response: makeAgentResponse(promptTokens: 15, completionTokens: 25),
    ));

    $updateEvent = collect($batcher->events())->first(
        fn(IngestionEvent $e) => $e->type->value === 'generation-update',
    );
    $body = $updateEvent->body->toArray();

    expect($body)->toHaveKey('usage')
        ->and($body['usage']['input'])->toBe(15)
        ->and($body['usage']['output'])->toBe(25)
        ->and($body['usage']['total'])->toBe(40);
});

it('captures model name from response meta', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = new LaravelAiSubscriber($client);

    $prompt = makeAgentPrompt('gpt-4');

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    $subscriber->handleAgentPrompted(new AgentPrompted(
        invocationId: 'inv-1',
        prompt: $prompt,
        response: makeAgentResponse(model: 'gpt-4-turbo'),
    ));

    $createEvent = collect($batcher->events())->first(
        fn(IngestionEvent $e) => $e->type->value === 'generation-create',
    );
    $body = $createEvent->body->toArray();

    expect($body['model'])->toBe('gpt-4-turbo');
});

it('creates trace with agent class name', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = new LaravelAiSubscriber($client);

    $prompt = makeAgentPrompt();

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    $traceEvent = collect($batcher->events())->first(
        fn(IngestionEvent $e) => $e->type->value === 'trace-create',
    );
    $body = $traceEvent->body->toArray();

    // Anonymous class gets a generated name, but it should start with 'laravel-ai-'
    expect($body['name'])->toStartWith('laravel-ai-');
});

it('creates trace with correct metadata', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = new LaravelAiSubscriber($client);

    $prompt = makeAgentPrompt('claude-3-opus');

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    $traceEvent = collect($batcher->events())->first(
        fn(IngestionEvent $e) => $e->type->value === 'trace-create',
    );
    $body = $traceEvent->body->toArray();

    expect($body['metadata']['model'])->toBe('claude-3-opus')
        ->and($body['metadata']['source'])->toBe('laravel-ai-auto-instrumentation');
});

it('creates span for tool invocation', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = new LaravelAiSubscriber($client);

    // First create a trace via agent prompt
    $prompt = makeAgentPrompt();
    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    $agent = makeTestAgent();
    $tool = makeTestTool();

    $subscriber->handleInvokingTool(new InvokingTool(
        invocationId: 'inv-1',
        toolInvocationId: 'tool-inv-1',
        agent: $agent,
        tool: $tool,
        arguments: ['query' => 'test'],
    ));

    $subscriber->handleToolInvoked(new ToolInvoked(
        invocationId: 'inv-1',
        toolInvocationId: 'tool-inv-1',
        agent: $agent,
        tool: $tool,
        arguments: ['query' => 'test'],
        result: 'Tool result',
    ));

    $spanCreateEvents = collect($batcher->events())->filter(
        fn(IngestionEvent $e) => $e->type->value === 'span-create',
    );
    $spanUpdateEvents = collect($batcher->events())->filter(
        fn(IngestionEvent $e) => $e->type->value === 'span-update',
    );

    expect($spanCreateEvents)->toHaveCount(1)
        ->and($spanUpdateEvents)->toHaveCount(1);

    $spanBody = $spanCreateEvents->first()->body->toArray();
    expect($spanBody['name'])->toStartWith('tool-')
        ->and($spanBody['input'])->toBe(['query' => 'test']);

    $spanUpdateBody = $spanUpdateEvents->first()->body->toArray();
    expect($spanUpdateBody['output'])->toBe('Tool result');
});

it('reuses existing trace across multiple prompts', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = new LaravelAiSubscriber($client);

    $prompt = makeAgentPrompt();

    // First prompt
    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: 'inv-1', prompt: $prompt));
    $subscriber->handleAgentPrompted(new AgentPrompted(
        invocationId: 'inv-1',
        prompt: $prompt,
        response: makeAgentResponse(invocationId: 'inv-1'),
    ));

    // Second prompt (same invocation ID pattern, but the trace is set as current)
    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: 'inv-2', prompt: $prompt));
    $subscriber->handleAgentPrompted(new AgentPrompted(
        invocationId: 'inv-2',
        prompt: $prompt,
        response: makeAgentResponse(invocationId: 'inv-2'),
    ));

    $traceEvents = collect($batcher->events())->filter(
        fn(IngestionEvent $e) => $e->type->value === 'trace-create',
    );
    $generationEvents = collect($batcher->events())->filter(
        fn(IngestionEvent $e) => $e->type->value === 'generation-create',
    );

    // Only 1 trace created, but 2 generations
    expect($traceEvents)->toHaveCount(1)
        ->and($generationEvents)->toHaveCount(2);

    // Both generations reference the same trace
    $traceId = $traceEvents->first()->body->toArray()['id'];
    $genTraceIds = $generationEvents->map(fn(IngestionEvent $e) => $e->body->toArray()['traceId'])->all();

    expect($genTraceIds)->each->toBe($traceId);
});

it('sets current trace on langfuse client', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = new LaravelAiSubscriber($client);

    $prompt = makeAgentPrompt();

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    expect($client->currentTrace())->not->toBeInstanceOf(NullLangfuseTrace::class);
});

it('handles streaming events same as non-streaming', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = new LaravelAiSubscriber($client);

    $prompt = makeAgentPrompt();

    $subscriber->handlePromptingAgent(new StreamingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    $subscriber->handleAgentPrompted(new AgentStreamed(
        invocationId: 'inv-1',
        prompt: $prompt,
        response: new StreamedAgentResponse(
            invocationId: 'inv-1',
            text: 'Streamed response',
            usage: new Usage(promptTokens: 5, completionTokens: 10),
            meta: new Meta(provider: 'openai', model: 'gpt-4'),
        ),
    ));

    expect($batcher->events())->toHaveCount(3);

    $updateEvent = collect($batcher->events())->first(
        fn(IngestionEvent $e) => $e->type->value === 'generation-update',
    );
    $body = $updateEvent->body->toArray();

    expect($body['output'])->toBe('Streamed response')
        ->and($body['usage']['input'])->toBe(5)
        ->and($body['usage']['output'])->toBe(10);
});

it('handles tool invoked without prior invoking tool gracefully', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = new LaravelAiSubscriber($client);

    $subscriber->handleToolInvoked(new ToolInvoked(
        invocationId: 'inv-1',
        toolInvocationId: 'tool-inv-1',
        agent: makeTestAgent(),
        tool: makeTestTool(),
        arguments: [],
        result: 'result',
    ));

    // No span events should be created since we never called handleInvokingTool
    $spanEvents = collect($batcher->events())->filter(
        fn(IngestionEvent $e) => str_contains($e->type->value, 'span'),
    );

    expect($spanEvents)->toBeEmpty();
});

it('falls back to prompt model when response meta model is null', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = new LaravelAiSubscriber($client);

    $prompt = makeAgentPrompt('claude-3-sonnet');

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    $subscriber->handleAgentPrompted(new AgentPrompted(
        invocationId: 'inv-1',
        prompt: $prompt,
        response: makeAgentResponse(model: null),
    ));

    $createEvent = collect($batcher->events())->first(
        fn(IngestionEvent $e) => $e->type->value === 'generation-create',
    );
    $body = $createEvent->body->toArray();

    expect($body['model'])->toBe('claude-3-sonnet');
});

it('captures prompt text as generation input', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = new LaravelAiSubscriber($client);

    $prompt = makeAgentPrompt();

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    $subscriber->handleAgentPrompted(new AgentPrompted(
        invocationId: 'inv-1',
        prompt: $prompt,
        response: makeAgentResponse(),
    ));

    $createEvent = collect($batcher->events())->first(
        fn(IngestionEvent $e) => $e->type->value === 'generation-create',
    );
    $body = $createEvent->body->toArray();

    expect($body['input'])->toBe('Tell me a joke');
});

it('captures response text as generation output', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = new LaravelAiSubscriber($client);

    $prompt = makeAgentPrompt();

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    $subscriber->handleAgentPrompted(new AgentPrompted(
        invocationId: 'inv-1',
        prompt: $prompt,
        response: makeAgentResponse(text: 'Why did the chicken cross the road?'),
    ));

    $updateEvent = collect($batcher->events())->first(
        fn(IngestionEvent $e) => $e->type->value === 'generation-update',
    );
    $body = $updateEvent->body->toArray();

    expect($body['output'])->toBe('Why did the chicken cross the road?');
});

function generationCreateBodies(RecordingEventBatcher $batcher): array
{
    return array_map(
        fn(IngestionEvent $event): array => $event->toArray()['body'],
        $batcher->eventsOfType('generation-create'),
    );
}

function runAgentInvocation(LaravelAiSubscriber $subscriber, string $invocationId): void
{
    $prompt = makeAgentPrompt();
    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: $invocationId, prompt: $prompt));
    $subscriber->handleAgentPrompted(new AgentPrompted(
        invocationId: $invocationId,
        prompt: $prompt,
        response: makeAgentResponse(invocationId: $invocationId),
    ));
}

it('links the registered managed prompt to the generation and consumes it', function () {
    [$client, $batcher] = makeLangfuseClient();
    $registry = new CurrentPromptRegistry();
    $subscriber = new LaravelAiSubscriber($client, $registry);

    $registry->set(new TextPrompt(name: 'movie-critic', version: 7, prompt: 'text'));

    runAgentInvocation($subscriber, 'inv-1');
    runAgentInvocation($subscriber, 'inv-2');

    [$first, $second] = generationCreateBodies($batcher);

    expect($first['promptName'])->toBe('movie-critic')
        ->and($first['promptVersion'])->toBe(7)
        ->and($registry->current())->toBeNull()
        ->and($second)->not->toHaveKey('promptName');
});

it('keeps the prompt captured at invocation start when a nested prompt is resolved during the run', function () {
    [$client, $batcher] = makeLangfuseClient();
    $registry = new CurrentPromptRegistry();
    $subscriber = new LaravelAiSubscriber($client, $registry);
    $prompt = makeAgentPrompt();

    $registry->set(new TextPrompt(name: 'movie-critic', version: 7, prompt: 'text'));
    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: 'outer', prompt: $prompt));

    // A tool resolves another prompt and runs a nested agent while the outer agent is in flight.
    $registry->set(new TextPrompt(name: 'summarizer', version: 2, prompt: 'text'));
    runAgentInvocation($subscriber, 'nested');

    $subscriber->handleAgentPrompted(new AgentPrompted(
        invocationId: 'outer',
        prompt: $prompt,
        response: makeAgentResponse(invocationId: 'outer'),
    ));

    [$nested, $outer] = generationCreateBodies($batcher);

    expect($nested['promptName'])->toBe('summarizer')
        ->and($nested['promptVersion'])->toBe(2)
        ->and($outer['promptName'])->toBe('movie-critic')
        ->and($outer['promptVersion'])->toBe(7);
});

it('does not leak a prompt from a failed invocation into the next generation', function () {
    [$client, $batcher] = makeLangfuseClient();
    $registry = new CurrentPromptRegistry();
    $subscriber = new LaravelAiSubscriber($client, $registry);

    $registry->set(new TextPrompt(name: 'movie-critic', version: 7, prompt: 'text'));
    // The provider throws after PromptingAgent, so AgentPrompted never fires for inv-1.
    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: 'inv-1', prompt: makeAgentPrompt()));

    runAgentInvocation($subscriber, 'inv-2');

    [$body] = generationCreateBodies($batcher);

    expect($body)->not->toHaveKey('promptName')
        ->and($registry->current())->toBeNull();
});
