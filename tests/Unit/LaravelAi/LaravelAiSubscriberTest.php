<?php

declare(strict_types=1);

use Axyr\Langfuse\Cache\PromptCache;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\HasSessionIdInterface;
use Axyr\Langfuse\Contracts\LangfuseClientInterface;
use Axyr\Langfuse\Contracts\PromptApiClientInterface;
use Axyr\Langfuse\Contracts\ScoreApiClientInterface;
use Axyr\Langfuse\Dto\IngestionEvent;
use Axyr\Langfuse\LangfuseClient;
use Axyr\Langfuse\LaravelAi\LaravelAiSubscriber;
use Axyr\Langfuse\Objects\NullLangfuseTrace;
use Axyr\Langfuse\Prompt\PromptManager;
use Axyr\Langfuse\Testing\RecordingEventBatcher;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Contracts\RemembersConversations;
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

function makeLangfuseConfig(
    bool $laravelAiSessionTracingEnabled = true,
    bool $laravelAiUserTracingEnabled = true,
): LangfuseConfig {
    return new LangfuseConfig(
        publicKey: 'pk',
        secretKey: 'sk',
        laravelAiSessionTracingEnabled: $laravelAiSessionTracingEnabled,
        laravelAiUserTracingEnabled: $laravelAiUserTracingEnabled,
    );
}

function makeSubscriber(
    LangfuseClientInterface $client,
    ?LangfuseConfig $config = null,
    ?AuthFactory $auth = null,
): LaravelAiSubscriber {
    return new LaravelAiSubscriber($client, $config ?? makeLangfuseConfig(), $auth ?? makeAuthFactory());
}

function makeConversationalAgent(?string $conversationId = null): Agent&RemembersConversations
{
    return new class ($conversationId) implements Agent, RemembersConversations {
        public function __construct(
            private readonly ?string $conversationId,
        ) {}

        public function forUser(object $user): static
        {
            return $this;
        }

        public function continue(string $conversationId, object $as): static
        {
            return $this;
        }

        public function continueLastConversation(object $as): static
        {
            return $this;
        }

        public function currentConversation(): ?string
        {
            return $this->conversationId;
        }

        public function hasConversationParticipant(): bool
        {
            return false;
        }

        public function conversationParticipant(): ?object
        {
            return null;
        }

        public function messages(): iterable
        {
            return [];
        }
    };
}

function makeSessionIdAgent(?string $sessionId): Agent&HasSessionIdInterface
{
    return new class ($sessionId) implements Agent, HasSessionIdInterface {
        public function __construct(private readonly ?string $sessionId) {}

        public function getSessionId(): ?string
        {
            return $this->sessionId;
        }
    };
}

function makeAgentWithBothSessionSources(?string $sessionId, ?string $conversationId): Agent&HasSessionIdInterface&RemembersConversations
{
    return new class ($sessionId, $conversationId) implements Agent, HasSessionIdInterface, RemembersConversations {
        public function __construct(
            private readonly ?string $sessionId,
            private readonly ?string $conversationId,
        ) {}

        public function getSessionId(): ?string
        {
            return $this->sessionId;
        }

        public function forUser(object $user): static
        {
            return $this;
        }

        public function continue(string $conversationId, object $as): static
        {
            return $this;
        }

        public function continueLastConversation(object $as): static
        {
            return $this;
        }

        public function currentConversation(): ?string
        {
            return $this->conversationId;
        }

        public function hasConversationParticipant(): bool
        {
            return false;
        }

        public function conversationParticipant(): ?object
        {
            return null;
        }

        public function messages(): iterable
        {
            return [];
        }
    };
}

function makeAuthenticatable(int|string $id): Authenticatable
{
    return new class ($id) implements Authenticatable {
        public function __construct(private readonly int|string $id) {}

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return $this->id;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }
    };
}

function makeAuthFactory(?Authenticatable $user = null): AuthFactory
{
    $guard = Mockery::mock(Guard::class);
    $guard->shouldReceive('user')->andReturn($user);

    $factory = Mockery::mock(AuthFactory::class);
    $factory->shouldReceive('guard')->andReturn($guard);

    return $factory;
}

it('registers correct event mappings in subscribe', function () {
    [$client] = makeLangfuseClient();
    $subscriber = makeSubscriber($client);

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
    $subscriber = makeSubscriber($client);

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

    expect($batcher->events())->toHaveCount(4); // trace-create, generation-create, generation-update, trace-create (output update)

    $types = array_map(fn(IngestionEvent $e) => $e->type->value, $batcher->events());
    expect($types)->toContain('trace-create')
        ->and($types)->toContain('generation-create')
        ->and($types)->toContain('generation-update');
});

it('sets the trace output to the agent response text', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = makeSubscriber($client);

    $prompt = makeAgentPrompt();

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    $subscriber->handleAgentPrompted(new AgentPrompted(
        invocationId: 'inv-1',
        prompt: $prompt,
        response: makeAgentResponse(text: 'Final answer'),
    ));

    $traceEvents = collect($batcher->events())->filter(
        fn(IngestionEvent $e) => $e->type->value === 'trace-create',
    );
    $traceId = $traceEvents->first()->body->toArray()['id'];
    $updateEvent = $traceEvents->last();
    $body = $updateEvent->body->toArray();

    expect($traceEvents)->toHaveCount(2)
        ->and($body['id'])->toBe($traceId)
        ->and($body['output'])->toBe('Final answer');
});

it('captures usage data in generation', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = makeSubscriber($client);

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
    $subscriber = makeSubscriber($client);

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
    $subscriber = makeSubscriber($client);

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
    $subscriber = makeSubscriber($client);

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
    $subscriber = makeSubscriber($client);

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
    $subscriber = makeSubscriber($client);

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

    // 1 trace created plus 2 output updates (also sent as trace-create events), but 2 generations
    expect($traceEvents)->toHaveCount(3)
        ->and($generationEvents)->toHaveCount(2);

    // Both generations reference the same trace
    $traceId = $traceEvents->first()->body->toArray()['id'];
    $genTraceIds = $generationEvents->map(fn(IngestionEvent $e) => $e->body->toArray()['traceId'])->all();

    expect($genTraceIds)->each->toBe($traceId);
});

it('sets current trace on langfuse client', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = makeSubscriber($client);

    $prompt = makeAgentPrompt();

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    expect($client->currentTrace())->not->toBeInstanceOf(NullLangfuseTrace::class);
});

it('handles streaming events same as non-streaming', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = makeSubscriber($client);

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

    expect($batcher->events())->toHaveCount(4);

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
    $subscriber = makeSubscriber($client);

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
    $subscriber = makeSubscriber($client);

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
    $subscriber = makeSubscriber($client);

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
    $subscriber = makeSubscriber($client);

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

it('sets trace sessionId from an agent that remembers conversations', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = makeSubscriber($client);

    $agent = makeConversationalAgent(conversationId: 'conversation-42');
    $prompt = makeAgentPrompt(agent: $agent);

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    $traceEvent = collect($batcher->events())->first(
        fn(IngestionEvent $e) => $e->type->value === 'trace-create',
    );
    $body = $traceEvent->body->toArray();

    expect($body['sessionId'])->toBe('conversation-42');
});

it('sets trace sessionId from an agent implementing HasSessionIdInterface', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = makeSubscriber($client);

    $agent = makeSessionIdAgent('custom-session-42');
    $prompt = makeAgentPrompt(agent: $agent);

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    $traceEvent = collect($batcher->events())->first(
        fn(IngestionEvent $e) => $e->type->value === 'trace-create',
    );
    $body = $traceEvent->body->toArray();

    expect($body['sessionId'])->toBe('custom-session-42');
});

it('prefers HasSessionIdInterface over RemembersConversations for sessionId', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = makeSubscriber($client);

    $agent = makeAgentWithBothSessionSources(sessionId: 'explicit-session', conversationId: 'conversation-42');
    $prompt = makeAgentPrompt(agent: $agent);

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    $traceEvent = collect($batcher->events())->first(
        fn(IngestionEvent $e) => $e->type->value === 'trace-create',
    );
    $body = $traceEvent->body->toArray();

    expect($body['sessionId'])->toBe('explicit-session');
});

it('leaves trace sessionId unset for an agent with no session source', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = makeSubscriber($client);

    $prompt = makeAgentPrompt(agent: makeTestAgent());

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    $traceEvent = collect($batcher->events())->first(
        fn(IngestionEvent $e) => $e->type->value === 'trace-create',
    );
    $body = $traceEvent->body->toArray();

    expect($body)->not->toHaveKey('sessionId');
});

it('does not set sessionId when laravel ai session tracing is disabled', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = makeSubscriber($client, makeLangfuseConfig(laravelAiSessionTracingEnabled: false));

    $agent = makeConversationalAgent(conversationId: 'conversation-42');
    $prompt = makeAgentPrompt(agent: $agent);

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    $traceEvent = collect($batcher->events())->first(
        fn(IngestionEvent $e) => $e->type->value === 'trace-create',
    );
    $body = $traceEvent->body->toArray();

    expect($body)->not->toHaveKey('sessionId');
});

it('sets trace userId from the currently authenticated user, regardless of agent type', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = makeSubscriber($client, auth: makeAuthFactory(makeAuthenticatable('user-42')));

    $prompt = makeAgentPrompt(agent: makeTestAgent());

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    $traceEvent = collect($batcher->events())->first(
        fn(IngestionEvent $e) => $e->type->value === 'trace-create',
    );
    $body = $traceEvent->body->toArray();

    expect($body['userId'])->toBe('user-42');
});

it('leaves trace userId unset when there is no authenticated user', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = makeSubscriber($client, auth: makeAuthFactory(null));

    $agent = makeConversationalAgent(conversationId: 'conversation-42');
    $prompt = makeAgentPrompt(agent: $agent);

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    $traceEvent = collect($batcher->events())->first(
        fn(IngestionEvent $e) => $e->type->value === 'trace-create',
    );
    $body = $traceEvent->body->toArray();

    expect($body)->not->toHaveKey('userId');
});

it('does not set userId when laravel ai user tracing is disabled', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = makeSubscriber(
        $client,
        makeLangfuseConfig(laravelAiUserTracingEnabled: false),
        makeAuthFactory(makeAuthenticatable('user-42')),
    );

    $prompt = makeAgentPrompt(agent: makeTestAgent());

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    $traceEvent = collect($batcher->events())->first(
        fn(IngestionEvent $e) => $e->type->value === 'trace-create',
    );
    $body = $traceEvent->body->toArray();

    expect($body)->not->toHaveKey('userId');
});

it('sets sessionId and userId on the tool span trace as well', function () {
    [$client, $batcher] = makeLangfuseClient();
    $subscriber = makeSubscriber($client, auth: makeAuthFactory(makeAuthenticatable('user-42')));

    $agent = makeConversationalAgent(conversationId: 'conversation-42');
    $tool = makeTestTool();

    $subscriber->handleInvokingTool(new InvokingTool(
        invocationId: 'inv-1',
        toolInvocationId: 'tool-inv-1',
        agent: $agent,
        tool: $tool,
        arguments: [],
    ));

    $traceEvent = collect($batcher->events())->first(
        fn(IngestionEvent $e) => $e->type->value === 'trace-create',
    );
    $body = $traceEvent->body->toArray();

    expect($body['sessionId'])->toBe('conversation-42')
        ->and($body['userId'])->toBe('user-42');
});
