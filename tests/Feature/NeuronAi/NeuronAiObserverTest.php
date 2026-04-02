<?php

declare(strict_types=1);

use Axyr\Langfuse\NeuronAi\NeuronAiObserver;

it('does not register observer when disabled', function () {
    config(['langfuse.neuron_ai_enabled' => false]);

    expect($this->app->bound(NeuronAiObserver::class))->toBeFalse();
});

it('registers observer when enabled', function () {
    config(['langfuse.neuron_ai_enabled' => true]);

    // Re-bootstrap with neuron_ai enabled
    $this->app->forgetInstance(\Axyr\Langfuse\Config\LangfuseConfig::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Contracts\LangfuseClientInterface::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Contracts\EventBatcherInterface::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Prompt\PromptManager::class);

    $provider = new \Axyr\Langfuse\LangfuseServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    expect($this->app->bound(NeuronAiObserver::class))->toBeTrue();
});

it('produces correct ingestion events for full workflow', function () {
    config([
        'langfuse.neuron_ai_enabled' => true,
        'langfuse.public_key' => 'pk-test',
        'langfuse.secret_key' => 'sk-test',
    ]);

    // Re-bootstrap
    $this->app->forgetInstance(\Axyr\Langfuse\Config\LangfuseConfig::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Contracts\LangfuseClientInterface::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Contracts\EventBatcherInterface::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Prompt\PromptManager::class);

    $provider = new \Axyr\Langfuse\LangfuseServiceProvider($this->app);
    $provider->register();

    // Bind recording batcher AFTER register() so it overrides the scoped binding
    $batcher = new \Axyr\Langfuse\Testing\RecordingEventBatcher();
    $this->app->instance(\Axyr\Langfuse\Contracts\EventBatcherInterface::class, $batcher);

    $provider->boot();

    /** @var NeuronAiObserver $observer */
    $observer = $this->app->make(NeuronAiObserver::class);

    $agent = new class () {
        public function getName(): string
        {
            return 'TestAgent';
        }
    };

    $inputMessage = new \NeuronAI\Chat\Messages\Message('user', 'Hello AI');
    $responseMessage = new \NeuronAI\Chat\Messages\Message('assistant', 'Hello human');
    $responseMessage->setUsage(new \NeuronAI\Chat\Messages\Usage(inputTokens: 10, outputTokens: 20));

    $observer->onEvent('workflow-start', $agent, new \NeuronAI\Observability\Events\WorkflowStart());
    $observer->onEvent('inference-start', $agent, new \NeuronAI\Observability\Events\InferenceStart($inputMessage));
    $observer->onEvent('inference-stop', $agent, new \NeuronAI\Observability\Events\InferenceStop(
        message: $inputMessage,
        response: $responseMessage,
    ));
    $observer->onEvent('workflow-end', $agent, new \NeuronAI\Observability\Events\WorkflowEnd(
        new \NeuronAI\Workflow\WorkflowState(['result' => 'Hello human']),
    ));

    $types = array_map(
        fn(\Axyr\Langfuse\Dto\IngestionEvent $e) => $e->type->value,
        $batcher->events(),
    );

    expect($types)->toContain('trace-create')
        ->and($types)->toContain('generation-create')
        ->and($types)->toContain('generation-update');
});

it('produces correct ingestion events for tool workflow', function () {
    config([
        'langfuse.neuron_ai_enabled' => true,
        'langfuse.public_key' => 'pk-test',
        'langfuse.secret_key' => 'sk-test',
    ]);

    // Re-bootstrap
    $this->app->forgetInstance(\Axyr\Langfuse\Config\LangfuseConfig::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Contracts\LangfuseClientInterface::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Contracts\EventBatcherInterface::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Prompt\PromptManager::class);

    $provider = new \Axyr\Langfuse\LangfuseServiceProvider($this->app);
    $provider->register();

    $batcher = new \Axyr\Langfuse\Testing\RecordingEventBatcher();
    $this->app->instance(\Axyr\Langfuse\Contracts\EventBatcherInterface::class, $batcher);

    $provider->boot();

    /** @var NeuronAiObserver $observer */
    $observer = $this->app->make(NeuronAiObserver::class);

    $agent = new class () {};

    $tool = new class () implements \NeuronAI\Tools\ToolInterface {
        public function getName(): string
        {
            return 'search';
        }

        public function getDescription(): string
        {
            return 'Search tool';
        }

        public function getInputs(): array
        {
            return ['query' => 'test'];
        }

        public function getResult(): mixed
        {
            return 'Search results';
        }
    };

    $observer->onEvent('workflow-start', $agent, new \NeuronAI\Observability\Events\WorkflowStart());
    $observer->onEvent('tool-calling', $agent, new \NeuronAI\Observability\Events\ToolCalling($tool));
    $observer->onEvent('tool-called', $agent, new \NeuronAI\Observability\Events\ToolCalled($tool));

    $types = array_map(
        fn(\Axyr\Langfuse\Dto\IngestionEvent $e) => $e->type->value,
        $batcher->events(),
    );

    expect($types)->toContain('trace-create')
        ->and($types)->toContain('span-create')
        ->and($types)->toContain('span-update');
});
