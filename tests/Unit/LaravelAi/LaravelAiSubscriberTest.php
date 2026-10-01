<?php

declare(strict_types=1);

use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\LangfuseClientInterface;
use Axyr\Langfuse\Dto\TextPrompt;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Enums\ObservationType;
use Axyr\Langfuse\LaravelAi\LaravelAiSubscriber;
use Axyr\Langfuse\Objects\LangfuseGeneration;
use Axyr\Langfuse\Objects\LangfuseSpan;
use Axyr\Langfuse\Objects\LangfuseTrace;
use Axyr\Langfuse\Objects\NullLangfuseTrace;
use Axyr\Langfuse\Prompt\CurrentPromptRegistry;
use Axyr\Langfuse\Testing\LangfuseFake;
use Illuminate\Auth\GenericUser;
use Laravel\Ai\Concerns\RemembersConversations as RemembersConversationsTrait;
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

function makeLangfuseClient(): LangfuseFake
{
    return new LangfuseFake(
        new CurrentPromptRegistry(),
        new LangfuseConfig(publicKey: 'pk', secretKey: 'sk'),
    );
}

function makeSubscriber(
    LangfuseClientInterface $client,
    ?LangfuseConfig $config = null,
    ?CurrentPromptRegistry $registry = null,
): LaravelAiSubscriber {
    return new LaravelAiSubscriber(
        $client,
        $config ?? new LangfuseConfig(publicKey: 'pk', secretKey: 'sk'),
        $registry ?? new CurrentPromptRegistry(),
    );
}

/**
 * @return array<int, array<string, mixed>>
 */
function traceBodies(LangfuseFake $fake): array
{
    return array_map(fn(LangfuseTrace $trace): array => $trace->getBody()->toArray(), $fake->traces());
}

/**
 * @return array<int, array<string, mixed>>
 */
function generationBodies(LangfuseFake $fake): array
{
    return array_map(fn(LangfuseGeneration $g): array => $g->getBody()->toArray(), $fake->generations());
}

/**
 * @return array<int, array<string, mixed>>
 */
function spanBodies(LangfuseFake $fake): array
{
    return array_map(fn(LangfuseSpan $s): array => $s->getBody()->toArray(), $fake->spans());
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

it('registers correct event mappings in subscribe', function () {
    $subscriber = makeSubscriber(makeLangfuseClient());

    $dispatcher = Mockery::mock(\Illuminate\Events\Dispatcher::class);

    expect($subscriber->subscribe($dispatcher))->toBe([
        PromptingAgent::class => 'handlePromptingAgent',
        StreamingAgent::class => 'handlePromptingAgent',
        AgentPrompted::class => 'handleAgentPrompted',
        AgentStreamed::class => 'handleAgentPrompted',
        InvokingTool::class => 'handleInvokingTool',
        ToolInvoked::class => 'handleToolInvoked',
    ]);
});

it('creates a trace and a generation, and ends both, on agent prompt', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);
    $prompt = makeAgentPrompt();

    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: 'inv-1', prompt: $prompt));
    $subscriber->handleAgentPrompted(new AgentPrompted(
        invocationId: 'inv-1',
        prompt: $prompt,
        response: makeAgentResponse(),
    ));

    $fake->assertTraceCreated()
        ->assertTraceEnded()
        ->assertGenerationCreated()
        ->assertGenerationEnded()
        ->assertEventCount(2);
});

it('nests the generation under the root observation of the trace', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);

    runAgentInvocation($subscriber, 'inv-1');

    $trace = $fake->traces()[0];

    expect($fake->generations()[0]->getBody()->parentObservationId)->toBe($trace->getRootObservationId())
        ->and($fake->generations()[0]->getBody()->traceId)->toBe($trace->getId());
});

it('captures usage data in generation', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);
    $prompt = makeAgentPrompt();

    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: 'inv-1', prompt: $prompt));
    $subscriber->handleAgentPrompted(new AgentPrompted(
        invocationId: 'inv-1',
        prompt: $prompt,
        response: makeAgentResponse(promptTokens: 15, completionTokens: 25),
    ));

    $body = generationBodies($fake)[0];

    expect($body)->toHaveKey('usage')
        ->and($body['usage']['input'])->toBe(15)
        ->and($body['usage']['output'])->toBe(25)
        ->and($body['usage']['total'])->toBe(40);
});

it('captures model name from response meta', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);
    $prompt = makeAgentPrompt('gpt-4');

    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: 'inv-1', prompt: $prompt));
    $subscriber->handleAgentPrompted(new AgentPrompted(
        invocationId: 'inv-1',
        prompt: $prompt,
        response: makeAgentResponse(model: 'gpt-4-turbo'),
    ));

    expect(generationBodies($fake)[0]['model'])->toBe('gpt-4-turbo');
});

it('creates trace with agent class name', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);

    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: 'inv-1', prompt: makeAgentPrompt()));

    expect(traceBodies($fake)[0]['name'])->toStartWith('laravel-ai-');
});

it('creates trace with correct metadata', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: makeAgentPrompt('claude-3-opus'),
    ));

    $body = traceBodies($fake)[0];

    expect($body['metadata']['model'])->toBe('claude-3-opus')
        ->and($body['metadata']['source'])->toBe('laravel-ai-auto-instrumentation');
});

it('creates and ends a span for a tool invocation', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);

    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: 'inv-1', prompt: makeAgentPrompt()));

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

    expect($fake->spans())->toHaveCount(1);

    $body = spanBodies($fake)[0];

    expect($body['name'])->toStartWith('tool-')
        ->and($body['input'])->toBe(['query' => 'test'])
        ->and($body['output'])->toBe('Tool result')
        ->and($body['type'])->toBe('tool')
        ->and($fake->spans()[0]->hasEnded())->toBeTrue();
});

it('ends the owned trace, so the next invocation starts a new one', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);

    runAgentInvocation($subscriber, 'inv-1');
    runAgentInvocation($subscriber, 'inv-2');

    expect($fake->traces())->toHaveCount(2)
        ->and($fake->generations())->toHaveCount(2)
        ->and($fake->traces()[0]->hasEnded())->toBeTrue()
        ->and($fake->traces()[1]->hasEnded())->toBeTrue()
        ->and($fake->generations()[0]->getBody()->traceId)->toBe($fake->traces()[0]->getId())
        ->and($fake->generations()[1]->getBody()->traceId)->toBe($fake->traces()[1]->getId());
});

it('resets the current trace once the owned invocation is done', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);

    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: 'inv-1', prompt: makeAgentPrompt()));

    expect($fake->currentTrace())->not->toBeInstanceOf(NullLangfuseTrace::class);

    runAgentInvocation($subscriber, 'inv-1');

    expect($fake->currentTrace())->toBeInstanceOf(NullLangfuseTrace::class);
});

it('sets current trace on langfuse client', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);

    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: 'inv-1', prompt: makeAgentPrompt()));

    expect($fake->currentTrace())->not->toBeInstanceOf(NullLangfuseTrace::class);
});

it('handles streaming events same as non-streaming', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);
    $prompt = makeAgentPrompt();

    $subscriber->handlePromptingAgent(new StreamingAgent(invocationId: 'inv-1', prompt: $prompt));
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

    $fake->assertEventCount(2);

    $body = generationBodies($fake)[0];

    expect($body['output'])->toBe('Streamed response')
        ->and($body['usage']['input'])->toBe(5)
        ->and($body['usage']['output'])->toBe(10);
});

it('handles tool invoked without prior invoking tool gracefully', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);

    $subscriber->handleToolInvoked(new ToolInvoked(
        invocationId: 'inv-1',
        toolInvocationId: 'tool-inv-1',
        agent: makeTestAgent(),
        tool: makeTestTool(),
        arguments: [],
        result: 'result',
    ));

    expect($fake->spans())->toBeEmpty();
});

it('falls back to prompt model when response meta model is null', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);
    $prompt = makeAgentPrompt('claude-3-sonnet');

    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: 'inv-1', prompt: $prompt));
    $subscriber->handleAgentPrompted(new AgentPrompted(
        invocationId: 'inv-1',
        prompt: $prompt,
        response: makeAgentResponse(model: null),
    ));

    expect(generationBodies($fake)[0]['model'])->toBe('claude-3-sonnet');
});

it('captures prompt text as generation input', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);

    runAgentInvocation($subscriber, 'inv-1');

    expect(generationBodies($fake)[0]['input'])->toBe('Tell me a joke');
});

it('captures response text as generation output', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);
    $prompt = makeAgentPrompt();

    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: 'inv-1', prompt: $prompt));
    $subscriber->handleAgentPrompted(new AgentPrompted(
        invocationId: 'inv-1',
        prompt: $prompt,
        response: makeAgentResponse(text: 'Why did the chicken cross the road?'),
    ));

    expect(generationBodies($fake)[0]['output'])->toBe('Why did the chicken cross the road?');
});

it('links the registered managed prompt to the generation and consumes it', function () {
    $fake = makeLangfuseClient();
    $registry = new CurrentPromptRegistry();
    $subscriber = makeSubscriber($fake, registry: $registry);

    $registry->set(new TextPrompt(name: 'movie-critic', version: 7, prompt: 'text'));

    runAgentInvocation($subscriber, 'inv-1');
    runAgentInvocation($subscriber, 'inv-2');

    [$first, $second] = generationBodies($fake);

    expect($first['promptName'])->toBe('movie-critic')
        ->and($first['promptVersion'])->toBe(7)
        ->and($registry->current())->toBeNull()
        ->and($second)->not->toHaveKey('promptName');
});

it('keeps the prompt captured at invocation start when a nested prompt is resolved during the run', function () {
    $fake = makeLangfuseClient();
    $registry = new CurrentPromptRegistry();
    $subscriber = makeSubscriber($fake, registry: $registry);
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

    [$nested, $outer] = generationBodies($fake);

    expect($nested['promptName'])->toBe('summarizer')
        ->and($nested['promptVersion'])->toBe(2)
        ->and($outer['promptName'])->toBe('movie-critic')
        ->and($outer['promptVersion'])->toBe(7);
});

it('does not leak a prompt from a failed invocation into the next generation', function () {
    $fake = makeLangfuseClient();
    $registry = new CurrentPromptRegistry();
    $subscriber = makeSubscriber($fake, registry: $registry);

    $registry->set(new TextPrompt(name: 'movie-critic', version: 7, prompt: 'text'));
    // The provider throws after PromptingAgent, so AgentPrompted never fires for inv-1.
    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: 'inv-1', prompt: makeAgentPrompt()));

    runAgentInvocation($subscriber, 'inv-2');

    [$body] = generationBodies($fake);

    expect($body)->not->toHaveKey('promptName')
        ->and($registry->current())->toBeNull();
});

function makeRememberingAgent(): Agent
{
    return new class () implements Agent {
        use RemembersConversationsTrait;
    };
}

function makeContractAgent(?string $conversationId, ?object $participant = null): Agent
{
    return new class ($conversationId, $participant) implements Agent, RemembersConversations {
        public function __construct(private ?string $conversationId, private ?object $participant) {}

        public function forParticipant(object $participant): static
        {
            return $this;
        }

        public function forUser(object $user): static
        {
            return $this;
        }

        public function continue(string $conversationId, ?object $as = null): static
        {
            return $this;
        }

        public function continueLastConversation(object $as): static
        {
            return $this;
        }

        public function messages(): iterable
        {
            return [];
        }

        public function currentConversation(): ?string
        {
            return $this->conversationId;
        }

        public function hasConversationParticipant(): bool
        {
            return $this->participant !== null;
        }

        public function conversationParticipant(): ?object
        {
            return $this->participant;
        }
    };
}

it('sets the trace output to the agent response text', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);
    $prompt = makeAgentPrompt();

    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: 'inv-1', prompt: $prompt));
    $timestamp = $fake->traces()[0]->getBody()->timestamp;

    $subscriber->handleAgentPrompted(new AgentPrompted(
        invocationId: 'inv-1',
        prompt: $prompt,
        response: makeAgentResponse(text: 'Final answer'),
    ));

    $body = traceBodies($fake)[0];

    expect($body['output'])->toBe('Final answer')
        ->and($body['timestamp'])->toBe($timestamp)
        ->and($fake->traces()[0]->hasEnded())->toBeTrue();
});

it('does not overwrite the output of a trace it did not create', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);

    $fake->setCurrentTrace($fake->trace(new TraceBody(id: 'workflow', output: ['status' => 'ok'])));

    runAgentInvocation($subscriber, 'inv-1');

    $bodies = traceBodies($fake);

    expect($bodies)->toHaveCount(1)
        ->and($bodies[0]['output'])->toBe(['status' => 'ok']);
});

it('leaves an adopted trace open for its owner', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);

    $trace = $fake->trace(new TraceBody(id: 'workflow', name: 'GET /chat'));
    $fake->setCurrentTrace($trace);

    runAgentInvocation($subscriber, 'inv-1');

    expect($trace->hasEnded())->toBeFalse()
        ->and($fake->currentTrace())->toBe($trace);
});

it('lets only the invocation that created the trace set its output', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);
    $prompt = makeAgentPrompt();

    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: 'outer', prompt: $prompt));

    // A tool runs a nested agent, which adopts the outer trace.
    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: 'nested', prompt: $prompt));
    $subscriber->handleAgentPrompted(new AgentPrompted(
        invocationId: 'nested',
        prompt: $prompt,
        response: makeAgentResponse(invocationId: 'nested', text: 'nested answer'),
    ));

    $subscriber->handleAgentPrompted(new AgentPrompted(
        invocationId: 'outer',
        prompt: $prompt,
        response: makeAgentResponse(invocationId: 'outer', text: 'outer answer'),
    ));

    $bodies = traceBodies($fake);

    expect($bodies)->toHaveCount(1)
        ->and($bodies[0]['output'])->toBe('outer answer');
});

it('sets sessionId and userId from an agent using the RemembersConversations trait', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);

    $agent = makeRememberingAgent()->continue('conversation-42', new GenericUser(['id' => 7]));

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: makeAgentPrompt(agent: $agent),
    ));

    $body = traceBodies($fake)[0];

    expect($body['sessionId'])->toBe('conversation-42')
        ->and($body['userId'])->toBe('7');
});

it('sets sessionId from an agent implementing the RemembersConversations contract', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: makeAgentPrompt(agent: makeContractAgent('conversation-42')),
    ));

    $body = traceBodies($fake)[0];

    expect($body['sessionId'])->toBe('conversation-42')
        ->and($body)->not->toHaveKey('userId');
});

it('tags the first turn of a new conversation from the response', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);

    $agent = makeRememberingAgent();
    $prompt = makeAgentPrompt(agent: $agent);

    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: 'inv-1', prompt: $prompt));

    expect(traceBodies($fake)[0])->not->toHaveKey('sessionId');

    // Laravel AI creates the conversation after generation and stamps it on the response.
    $user = new GenericUser(['id' => 7]);
    $agent->continue('conversation-new', $user);
    $response = makeAgentResponse(text: 'Hi')->withinConversation('conversation-new', $user);

    $subscriber->handleAgentPrompted(new AgentPrompted(invocationId: 'inv-1', prompt: $prompt, response: $response));

    $body = traceBodies($fake)[0];

    expect($body['sessionId'])->toBe('conversation-new')
        ->and($body['userId'])->toBe('7')
        ->and($body['output'])->toBe('Hi');
});

it('adds session and user to an adopted trace without touching its output', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);

    $fake->setCurrentTrace($fake->trace(new TraceBody(id: 'request', name: 'GET /chat')));

    $agent = makeRememberingAgent()->continue('conversation-42', new GenericUser(['id' => 7]));
    $prompt = makeAgentPrompt(agent: $agent);

    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: 'inv-1', prompt: $prompt));
    $subscriber->handleAgentPrompted(new AgentPrompted(invocationId: 'inv-1', prompt: $prompt, response: makeAgentResponse()));

    $body = traceBodies($fake)[0];

    expect($body['name'])->toBe('GET /chat')
        ->and($body['sessionId'])->toBe('conversation-42')
        ->and($body['userId'])->toBe('7')
        ->and($body)->not->toHaveKey('output');
});

it('leaves sessionId and userId unset for an agent without conversation state', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);

    runAgentInvocation($subscriber, 'inv-1');

    foreach (traceBodies($fake) as $body) {
        expect($body)->not->toHaveKey('sessionId')
            ->and($body)->not->toHaveKey('userId');
    }
});

it('does not send sessionId when session tracing is disabled', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake, new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', sessionTracingEnabled: false));

    $agent = makeRememberingAgent()->continue('conversation-42', new GenericUser(['id' => 7]));
    $prompt = makeAgentPrompt(agent: $agent);

    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: 'inv-1', prompt: $prompt));
    $subscriber->handleAgentPrompted(new AgentPrompted(invocationId: 'inv-1', prompt: $prompt, response: makeAgentResponse()));

    foreach (traceBodies($fake) as $body) {
        expect($body)->not->toHaveKey('sessionId')
            ->and($body['userId'])->toBe('7');
    }
});

it('does not send userId when user tracing is disabled', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake, new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', userTracingEnabled: false));

    $agent = makeRememberingAgent()->continue('conversation-42', new GenericUser(['id' => 7]));
    $prompt = makeAgentPrompt(agent: $agent);

    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: 'inv-1', prompt: $prompt));
    $subscriber->handleAgentPrompted(new AgentPrompted(invocationId: 'inv-1', prompt: $prompt, response: makeAgentResponse()));

    foreach (traceBodies($fake) as $body) {
        expect($body)->not->toHaveKey('userId')
            ->and($body['sessionId'])->toBe('conversation-42');
    }
});

it('derives the userId from Eloquent-style and plain participants', function (object $participant, string $expected) {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);

    $subscriber->handlePromptingAgent(new PromptingAgent(
        invocationId: 'inv-1',
        prompt: makeAgentPrompt(agent: makeContractAgent('conversation-42', $participant)),
    ));

    expect(traceBodies($fake)[0]['userId'])->toBe($expected);
})->with([
    'getKey()' => [new class () {
        public function getKey(): int
        {
            return 42;
        }
    }, '42'],
    'id property' => [(object) ['id' => 'usr_9'], 'usr_9'],
]);

it('exports the user and session on every span of the trace', function () {
    $fake = makeLangfuseClient();
    $subscriber = makeSubscriber($fake);

    $agent = makeRememberingAgent()->continue('conversation-42', new GenericUser(['id' => 7]));
    $prompt = makeAgentPrompt(agent: $agent);

    $subscriber->handlePromptingAgent(new PromptingAgent(invocationId: 'inv-1', prompt: $prompt));
    $subscriber->handleAgentPrompted(new AgentPrompted(invocationId: 'inv-1', prompt: $prompt, response: makeAgentResponse()));

    $generation = $fake->recorder()->observationsOfType(ObservationType::Generation)[0];

    expect($generation->context->body()->userId)->toBe('7')
        ->and($generation->context->body()->sessionId)->toBe('conversation-42');
});
