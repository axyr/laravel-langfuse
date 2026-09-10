<?php

declare(strict_types=1);

use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Dto\TextPrompt;
use Axyr\Langfuse\NeuronAi\NeuronAiObserver;
use Axyr\Langfuse\Objects\LangfuseGeneration;
use Axyr\Langfuse\Objects\LangfuseSpan;
use Axyr\Langfuse\Objects\LangfuseTrace;
use Axyr\Langfuse\Objects\NullLangfuseTrace;
use Axyr\Langfuse\Prompt\CurrentPromptRegistry;
use Axyr\Langfuse\Testing\LangfuseFake;
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

function makeNeuronLangfuseClient(): LangfuseFake
{
    return new LangfuseFake(
        new CurrentPromptRegistry(),
        new LangfuseConfig(publicKey: 'pk', secretKey: 'sk'),
    );
}

/**
 * @return array<int, array<string, mixed>>
 */
function neuronTraceBodies(LangfuseFake $fake): array
{
    return array_map(fn(LangfuseTrace $trace): array => $trace->getBody()->toArray(), $fake->traces());
}

/**
 * @return array<int, array<string, mixed>>
 */
function neuronGenerationBodies(LangfuseFake $fake): array
{
    return array_map(fn(LangfuseGeneration $g): array => $g->getBody()->toArray(), $fake->generations());
}

/**
 * @return array<int, array<string, mixed>>
 */
function neuronSpanBodies(LangfuseFake $fake): array
{
    return array_map(fn(LangfuseSpan $s): array => $s->getBody()->toArray(), $fake->spans());
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
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);
    $agent = makeTestNeuronAgent();

    $observer->onEvent('workflow-start', $agent, new WorkflowStart());
    expect($fake->traces())->toHaveCount(1);

    // unknown event should be silently ignored
    $observer->onEvent('unknown-event', $agent);
    expect($fake->traces())->toHaveCount(1);
});

it('creates trace on workflow-start without sending anything', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);

    $observer->onEvent('workflow-start', makeTestNeuronAgent(), new WorkflowStart());

    $body = neuronTraceBodies($fake)[0];

    expect($fake->traces())->toHaveCount(1)
        ->and($body['name'])->toStartWith('neuron-ai-')
        ->and($body['metadata']['source'])->toBe('neuron-ai-auto-instrumentation')
        ->and($fake->recorder()->observations())->toBeEmpty();
});

it('creates and ends a generation on inference events', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);
    $agent = makeTestNeuronAgent();

    $inputMessage = makeNeuronMessage('What is PHP?');
    $responseMessage = makeNeuronMessage('PHP is a programming language');

    $observer->onEvent('workflow-start', $agent, new WorkflowStart());
    $observer->onEvent('inference-start', $agent, new InferenceStart($inputMessage));
    $observer->onEvent('inference-stop', $agent, new InferenceStop(
        message: $inputMessage,
        response: $responseMessage,
    ));

    $body = neuronGenerationBodies($fake)[0];

    expect($fake->generations())->toHaveCount(1)
        ->and($fake->generations()[0]->hasEnded())->toBeTrue()
        ->and($body['name'])->toBe('inference')
        ->and($body['input'])->toBe('What is PHP?')
        ->and($body['output'])->toBe('PHP is a programming language');
});

it('captures token usage in generation', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);
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

    $body = neuronGenerationBodies($fake)[0];

    expect($body)->toHaveKey('usage')
        ->and($body['usage']['input'])->toBe(10)
        ->and($body['usage']['output'])->toBe(20)
        ->and($body['usage']['total'])->toBe(30);
});

it('creates and ends a span for tool events', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);
    $agent = makeTestNeuronAgent();

    $tool = makeTestNeuronTool('web-search', ['query' => 'PHP'], 'Search results');

    $observer->onEvent('workflow-start', $agent, new WorkflowStart());
    $observer->onEvent('tool-calling', $agent, new ToolCalling($tool));
    $observer->onEvent('tool-called', $agent, new ToolCalled($tool));

    $body = neuronSpanBodies($fake)[0];

    expect($fake->spans())->toHaveCount(1)
        ->and($fake->spans()[0]->hasEnded())->toBeTrue()
        ->and($body['name'])->toBe('tool-web-search')
        ->and($body['type'])->toBe('tool')
        ->and($body['output'])->toBe('Search results');
});

it('handles tool-called without prior tool-calling gracefully', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);
    $agent = makeTestNeuronAgent();

    $observer->onEvent('workflow-start', $agent, new WorkflowStart());
    $observer->onEvent('tool-called', $agent, new ToolCalled(makeTestNeuronTool('search')));

    expect($fake->spans())->toBeEmpty();
});

it('creates and ends a span for RAG retrieval events', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);
    $agent = makeTestNeuronAgent();

    $question = makeNeuronMessage('What is Laravel?');

    $observer->onEvent('workflow-start', $agent, new WorkflowStart());
    $observer->onEvent('rag-retrieving', $agent, new Retrieving($question));
    $observer->onEvent('rag-retrieved', $agent, new Retrieved($question, ['doc1', 'doc2']));

    $body = neuronSpanBodies($fake)[0];

    expect($fake->spans())->toHaveCount(1)
        ->and($body['name'])->toBe('rag-retrieval')
        ->and($body['type'])->toBe('retriever')
        ->and($body['input'])->toBe('What is Laravel?')
        ->and($body['output'])->toBe([
            'question' => 'What is Laravel?',
            'documents' => 2,
        ]);
});

it('records the error on the trace and ends it', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);
    $agent = makeTestNeuronAgent();

    $observer->onEvent('workflow-start', $agent, new WorkflowStart());
    $observer->onEvent('error', $agent, new AgentError(new RuntimeException('Something went wrong')));

    $body = neuronTraceBodies($fake)[0];

    expect($fake->traces())->toHaveCount(1)
        ->and($body['metadata']['error'])->toBe('Something went wrong')
        ->and($body['metadata'])->toHaveKey('error_trace')
        ->and($body['metadata']['source'])->toBe('neuron-ai-auto-instrumentation')
        ->and($fake->traces()[0]->hasEnded())->toBeTrue();
});

it('reuses existing trace from langfuse client', function () {
    $fake = makeNeuronLangfuseClient();
    $agent = makeTestNeuronAgent();

    (new NeuronAiObserver($fake))->onEvent('workflow-start', $agent, new WorkflowStart());
    (new NeuronAiObserver($fake))->onEvent('workflow-start', $agent, new WorkflowStart());

    expect($fake->traces())->toHaveCount(1);
});

it('leaves an adopted trace open for its owner', function () {
    $fake = makeNeuronLangfuseClient();
    $agent = makeTestNeuronAgent();

    $trace = $fake->trace(new \Axyr\Langfuse\Dto\TraceBody(name: 'workflow'));
    $fake->setCurrentTrace($trace);

    $observer = new NeuronAiObserver($fake);
    $observer->onEvent('workflow-start', $agent, new WorkflowStart());
    $observer->onEvent('workflow-end', $agent, new WorkflowEnd(new WorkflowState(['result' => 'success'])));

    expect($trace->hasEnded())->toBeFalse();
});

it('sets current trace on langfuse client', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);

    $observer->onEvent('workflow-start', makeTestNeuronAgent(), new WorkflowStart());

    expect($fake->currentTrace())->not->toBeInstanceOf(NullLangfuseTrace::class);
});

it('sets the trace output and ends the owned trace on workflow-end', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);
    $agent = makeTestNeuronAgent();

    $observer->onEvent('workflow-start', $agent, new WorkflowStart());
    $observer->onEvent('workflow-end', $agent, new WorkflowEnd(new WorkflowState(['result' => 'success'])));

    $body = neuronTraceBodies($fake)[0];

    expect($body['output'])->toBe(['result' => 'success'])
        ->and($fake->traces()[0]->hasEnded())->toBeTrue()
        ->and($fake->currentTrace())->toBeInstanceOf(NullLangfuseTrace::class);
});

it('handles inference-stop with false message', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);
    $agent = makeTestNeuronAgent();

    $observer->onEvent('workflow-start', $agent, new WorkflowStart());
    $observer->onEvent('inference-start', $agent, new InferenceStart(makeNeuronMessage('input')));
    $observer->onEvent('inference-stop', $agent, new InferenceStop(
        message: false,
        response: makeNeuronMessage('Response text'),
    ));

    expect(neuronGenerationBodies($fake)[0])->not->toHaveKey('input');
});

it('handles full workflow with inference and tools', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);
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

    $fake->assertTraceCreated()
        ->assertTraceEnded()
        ->assertGenerationEnded('inference')
        ->assertSpanEnded('tool-web-search')
        ->assertEventCount(3);
});

it('nests the inference generation under the root observation', function () {
    $fake = makeNeuronLangfuseClient();
    $observer = new NeuronAiObserver($fake);
    $agent = makeTestNeuronAgent();
    $message = makeNeuronMessage('What is PHP?');

    $observer->onEvent('workflow-start', $agent, new WorkflowStart());
    $observer->onEvent('inference-start', $agent, new InferenceStart($message));
    $observer->onEvent('inference-stop', $agent, new InferenceStop(message: $message, response: $message));

    expect($fake->generations()[0]->getBody()->parentObservationId)
        ->toBe($fake->traces()[0]->getRootObservationId());
});

it('links the registered managed prompt to the inference generation', function () {
    $fake = makeNeuronLangfuseClient();
    $registry = new CurrentPromptRegistry();
    $registry->set(new TextPrompt(name: 'movie-critic', version: 7, prompt: 'text'));
    $observer = new NeuronAiObserver($fake, $registry);
    $agent = makeTestNeuronAgent();
    $message = makeNeuronMessage('What is PHP?');

    foreach ([1, 2] as $_) {
        $observer->onEvent('inference-start', $agent, new InferenceStart($message));
        $observer->onEvent('inference-stop', $agent, new InferenceStop(message: $message, response: $message));
    }

    $bodies = neuronGenerationBodies($fake);

    expect($bodies[0]['promptName'])->toBe('movie-critic')
        ->and($bodies[0]['promptVersion'])->toBe(7)
        ->and($bodies[1])->not->toHaveKey('promptName')
        ->and($registry->current())->toBeNull();
});
