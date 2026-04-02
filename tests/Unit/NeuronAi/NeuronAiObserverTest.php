<?php

declare(strict_types=1);

use Axyr\Langfuse\Cache\PromptCache;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\PromptApiClientInterface;
use Axyr\Langfuse\Contracts\ScoreApiClientInterface;
use Axyr\Langfuse\Dto\IngestionEvent;
use Axyr\Langfuse\LangfuseClient;
use Axyr\Langfuse\NeuronAi\NeuronAiObserver;
use Axyr\Langfuse\Objects\NullLangfuseTrace;
use Axyr\Langfuse\Prompt\PromptManager;
use Axyr\Langfuse\Testing\RecordingEventBatcher;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Observability\Events\AgentError;
use NeuronAI\Observability\Events\InferenceStart;
use NeuronAI\Observability\Events\InferenceStop;
use NeuronAI\Observability\Events\Retrieved;
use NeuronAI\Observability\Events\Retrieving;
use NeuronAI\Observability\Events\ToolCalled;
use NeuronAI\Observability\Events\ToolCalling;
use NeuronAI\Observability\Events\WorkflowEnd;
use NeuronAI\Observability\Events\WorkflowStart;
use NeuronAI\Tools\ToolInterface;
use NeuronAI\Workflow\WorkflowState;

function makeNeuronLangfuseClient(): array
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

function makeTestNeuronAgent(): object
{
    return new class () {
        public function getName(): string
        {
            return 'TestAgent';
        }
    };
}

function makeTestNeuronTool(string $name = 'search', array $inputs = [], mixed $result = 'tool result'): ToolInterface
{
    return new class ($name, $inputs, $result) implements ToolInterface {
        public function __construct(
            private readonly string $name,
            private readonly array $inputs,
            private readonly mixed $result,
        ) {}

        public function getName(): string
        {
            return $this->name;
        }

        public function getDescription(): string
        {
            return 'A test tool';
        }

        public function getInputs(): array
        {
            return $this->inputs;
        }

        public function getResult(): mixed
        {
            return $this->result;
        }
    };
}

function makeNeuronMessage(string $content = 'Hello', ?Usage $usage = null): Message
{
    $message = new Message('user', $content);

    if ($usage !== null) {
        $message->setUsage($usage);
    }

    return $message;
}

it('dispatches known events to handler methods', function () {
    [$client, $batcher] = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($client);
    $agent = makeTestNeuronAgent();

    // workflow-start should create a trace
    $observer->onEvent('workflow-start', $agent, new WorkflowStart());
    expect($batcher->events())->toHaveCount(1);

    // unknown event should be silently ignored
    $observer->onEvent('unknown-event', $agent);
    expect($batcher->events())->toHaveCount(1);
});

it('creates trace on workflow-start', function () {
    [$client, $batcher] = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($client);
    $agent = makeTestNeuronAgent();

    $observer->onEvent('workflow-start', $agent, new WorkflowStart());

    $traceEvents = collect($batcher->events())->filter(
        fn(IngestionEvent $e) => $e->type->value === 'trace-create',
    );

    expect($traceEvents)->toHaveCount(1);

    $body = $traceEvents->first()->body->toArray();
    expect($body['name'])->toStartWith('neuron-ai-')
        ->and($body['metadata']['source'])->toBe('neuron-ai-auto-instrumentation');
});

it('creates generation on inference events', function () {
    [$client, $batcher] = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($client);
    $agent = makeTestNeuronAgent();

    $inputMessage = makeNeuronMessage('What is PHP?');
    $responseMessage = makeNeuronMessage('PHP is a programming language');

    $observer->onEvent('workflow-start', $agent, new WorkflowStart());
    $observer->onEvent('inference-start', $agent, new InferenceStart($inputMessage));
    $observer->onEvent('inference-stop', $agent, new InferenceStop(
        message: $inputMessage,
        response: $responseMessage,
    ));

    $types = array_map(fn(IngestionEvent $e) => $e->type->value, $batcher->events());
    expect($types)->toContain('generation-create')
        ->and($types)->toContain('generation-update');

    $createEvent = collect($batcher->events())->first(
        fn(IngestionEvent $e) => $e->type->value === 'generation-create',
    );
    $body = $createEvent->body->toArray();
    expect($body['name'])->toBe('inference')
        ->and($body['input'])->toBe('What is PHP?');

    $updateEvent = collect($batcher->events())->first(
        fn(IngestionEvent $e) => $e->type->value === 'generation-update',
    );
    $body = $updateEvent->body->toArray();
    expect($body['output'])->toBe('PHP is a programming language');
});

it('captures token usage in generation', function () {
    [$client, $batcher] = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($client);
    $agent = makeTestNeuronAgent();

    $inputMessage = makeNeuronMessage('Hello');
    $responseMessage = makeNeuronMessage('Hi there');
    $responseMessage->setUsage(new Usage(inputTokens: 10, outputTokens: 20));

    $observer->onEvent('workflow-start', $agent, new WorkflowStart());
    $observer->onEvent('inference-start', $agent, new InferenceStart($inputMessage));
    $observer->onEvent('inference-stop', $agent, new InferenceStop(
        message: $inputMessage,
        response: $responseMessage,
    ));

    $updateEvent = collect($batcher->events())->first(
        fn(IngestionEvent $e) => $e->type->value === 'generation-update',
    );
    $body = $updateEvent->body->toArray();

    expect($body)->toHaveKey('usage')
        ->and($body['usage']['input'])->toBe(10)
        ->and($body['usage']['output'])->toBe(20)
        ->and($body['usage']['total'])->toBe(30);
});

it('creates span for tool events', function () {
    [$client, $batcher] = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($client);
    $agent = makeTestNeuronAgent();

    $tool = makeTestNeuronTool('web-search', ['query' => 'PHP'], 'Search results');

    $observer->onEvent('workflow-start', $agent, new WorkflowStart());
    $observer->onEvent('tool-calling', $agent, new ToolCalling($tool));
    $observer->onEvent('tool-called', $agent, new ToolCalled($tool));

    $spanCreateEvents = collect($batcher->events())->filter(
        fn(IngestionEvent $e) => $e->type->value === 'span-create',
    );
    $spanUpdateEvents = collect($batcher->events())->filter(
        fn(IngestionEvent $e) => $e->type->value === 'span-update',
    );

    expect($spanCreateEvents)->toHaveCount(1)
        ->and($spanUpdateEvents)->toHaveCount(1);

    $spanBody = $spanCreateEvents->first()->body->toArray();
    expect($spanBody['name'])->toBe('tool-web-search');

    $spanUpdateBody = $spanUpdateEvents->first()->body->toArray();
    expect($spanUpdateBody['output'])->toBe('Search results');
});

it('handles tool-called without prior tool-calling gracefully', function () {
    [$client, $batcher] = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($client);
    $agent = makeTestNeuronAgent();

    $tool = makeTestNeuronTool('search');

    $observer->onEvent('workflow-start', $agent, new WorkflowStart());
    $observer->onEvent('tool-called', $agent, new ToolCalled($tool));

    $spanEvents = collect($batcher->events())->filter(
        fn(IngestionEvent $e) => str_contains($e->type->value, 'span'),
    );

    expect($spanEvents)->toBeEmpty();
});

it('creates span for RAG retrieval events', function () {
    [$client, $batcher] = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($client);
    $agent = makeTestNeuronAgent();

    $question = makeNeuronMessage('What is Laravel?');

    $observer->onEvent('workflow-start', $agent, new WorkflowStart());
    $observer->onEvent('rag-retrieving', $agent, new Retrieving($question));
    $observer->onEvent('rag-retrieved', $agent, new Retrieved($question, ['doc1', 'doc2']));

    $spanCreateEvents = collect($batcher->events())->filter(
        fn(IngestionEvent $e) => $e->type->value === 'span-create',
    );
    $spanUpdateEvents = collect($batcher->events())->filter(
        fn(IngestionEvent $e) => $e->type->value === 'span-update',
    );

    expect($spanCreateEvents)->toHaveCount(1)
        ->and($spanUpdateEvents)->toHaveCount(1);

    $spanBody = $spanCreateEvents->first()->body->toArray();
    expect($spanBody['name'])->toBe('rag-retrieval')
        ->and($spanBody['input'])->toBe('What is Laravel?');

    $spanUpdateBody = $spanUpdateEvents->first()->body->toArray();
    expect($spanUpdateBody['output'])->toBe([
        'question' => 'What is Laravel?',
        'documents' => 2,
    ]);
});

it('updates trace with error on error event', function () {
    [$client, $batcher] = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($client);
    $agent = makeTestNeuronAgent();

    $observer->onEvent('workflow-start', $agent, new WorkflowStart());
    $observer->onEvent('error', $agent, new AgentError(new RuntimeException('Something went wrong')));

    $traceCreateEvents = collect($batcher->events())->filter(
        fn(IngestionEvent $e) => $e->type->value === 'trace-create',
    );

    // Should have 2 trace-create events (initial + update with error)
    expect($traceCreateEvents)->toHaveCount(2);

    $updateBody = $traceCreateEvents->last()->body->toArray();
    expect($updateBody['metadata']['error'])->toBe('Something went wrong')
        ->and($updateBody['metadata'])->toHaveKey('error_trace');
});

it('reuses existing trace from langfuse client', function () {
    [$client, $batcher] = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($client);
    $agent = makeTestNeuronAgent();

    // First observer creates a trace
    $observer->onEvent('workflow-start', $agent, new WorkflowStart());

    // Second observer should reuse the current trace
    $observer2 = new NeuronAiObserver($client);
    $observer2->onEvent('workflow-start', $agent, new WorkflowStart());

    $traceEvents = collect($batcher->events())->filter(
        fn(IngestionEvent $e) => $e->type->value === 'trace-create',
    );

    // Only 1 trace should be created
    expect($traceEvents)->toHaveCount(1);
});

it('sets current trace on langfuse client', function () {
    [$client] = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($client);
    $agent = makeTestNeuronAgent();

    $observer->onEvent('workflow-start', $agent, new WorkflowStart());

    expect($client->currentTrace())->not->toBeInstanceOf(NullLangfuseTrace::class);
});

it('updates trace output on workflow-end', function () {
    [$client, $batcher] = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($client);
    $agent = makeTestNeuronAgent();

    $state = new WorkflowState(['result' => 'success']);

    $observer->onEvent('workflow-start', $agent, new WorkflowStart());
    $observer->onEvent('workflow-end', $agent, new WorkflowEnd($state));

    $traceCreateEvents = collect($batcher->events())->filter(
        fn(IngestionEvent $e) => $e->type->value === 'trace-create',
    );

    expect($traceCreateEvents)->toHaveCount(2);

    $updateBody = $traceCreateEvents->last()->body->toArray();
    expect($updateBody['output'])->toBe(['result' => 'success']);
});

it('handles inference-stop with false message', function () {
    [$client, $batcher] = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($client);
    $agent = makeTestNeuronAgent();

    $responseMessage = makeNeuronMessage('Response text');

    $observer->onEvent('workflow-start', $agent, new WorkflowStart());
    $observer->onEvent('inference-start', $agent, new InferenceStart(makeNeuronMessage('input')));
    $observer->onEvent('inference-stop', $agent, new InferenceStop(
        message: false,
        response: $responseMessage,
    ));

    $createEvent = collect($batcher->events())->first(
        fn(IngestionEvent $e) => $e->type->value === 'generation-create',
    );
    $body = $createEvent->body->toArray();

    // Input should be null when message is false
    expect($body)->not->toHaveKey('input');
});

it('handles full workflow with inference and tools', function () {
    [$client, $batcher] = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($client);
    $agent = makeTestNeuronAgent();

    $inputMessage = makeNeuronMessage('Search for PHP info');
    $responseMessage = makeNeuronMessage('Here is the info about PHP');
    $responseMessage->setUsage(new Usage(inputTokens: 50, outputTokens: 100));
    $tool = makeTestNeuronTool('web-search', ['query' => 'PHP'], 'PHP info');

    $observer->onEvent('workflow-start', $agent, new WorkflowStart());
    $observer->onEvent('inference-start', $agent, new InferenceStart($inputMessage));
    $observer->onEvent('inference-stop', $agent, new InferenceStop($inputMessage, $responseMessage));
    $observer->onEvent('tool-calling', $agent, new ToolCalling($tool));
    $observer->onEvent('tool-called', $agent, new ToolCalled($tool));
    $observer->onEvent('workflow-end', $agent, new WorkflowEnd(new WorkflowState(['done' => true])));

    $types = array_map(fn(IngestionEvent $e) => $e->type->value, $batcher->events());

    expect($types)->toContain('trace-create')
        ->and($types)->toContain('generation-create')
        ->and($types)->toContain('generation-update')
        ->and($types)->toContain('span-create')
        ->and($types)->toContain('span-update');
});
