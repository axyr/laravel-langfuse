<?php

declare(strict_types=1);

use Axyr\Langfuse\LaravelAi\LaravelAiSubscriber;

it('does not register subscriber when disabled', function () {
    config(['langfuse.laravel_ai_enabled' => false]);

    expect($this->app->bound(LaravelAiSubscriber::class))->toBeFalse();
});

it('registers subscriber when enabled', function () {
    config(['langfuse.laravel_ai_enabled' => true]);

    // Re-bootstrap with laravel_ai enabled
    $this->app->forgetInstance(\Axyr\Langfuse\Config\LangfuseConfig::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Contracts\LangfuseClientInterface::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Contracts\EventBatcherInterface::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Prompt\PromptManager::class);

    $provider = new \Axyr\Langfuse\LangfuseServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    expect($this->app->bound(LaravelAiSubscriber::class))->toBeTrue();
});

it('dispatches events through subscriber when enabled', function () {
    config([
        'langfuse.laravel_ai_enabled' => true,
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

    // Create test event objects
    $agent = new class () implements \Laravel\Ai\Contracts\Agent {};
    $textProvider = new class () implements \Laravel\Ai\Contracts\Providers\TextProvider {};
    $prompt = new \Laravel\Ai\Prompts\AgentPrompt(
        agent: $agent,
        prompt: 'Hello AI',
        provider: $textProvider,
        model: 'gpt-4',
    );

    // Dispatch events
    event(new \Laravel\Ai\Events\PromptingAgent(
        invocationId: 'inv-1',
        prompt: $prompt,
    ));

    event(new \Laravel\Ai\Events\AgentPrompted(
        invocationId: 'inv-1',
        prompt: $prompt,
        response: new \Laravel\Ai\Responses\AgentResponse(
            invocationId: 'inv-1',
            text: 'Hello human',
            usage: new \Laravel\Ai\Responses\Data\Usage(promptTokens: 10, completionTokens: 20),
            meta: new \Laravel\Ai\Responses\Data\Meta(provider: 'openai', model: 'gpt-4'),
        ),
    ));

    // Should have trace-create, generation-create, generation-update
    $types = array_map(
        fn(\Axyr\Langfuse\Dto\IngestionEvent $e) => $e->type->value,
        $batcher->events(),
    );

    expect($types)->toContain('trace-create')
        ->and($types)->toContain('generation-create')
        ->and($types)->toContain('generation-update');
});

it('dispatches tool events through subscriber', function () {
    config([
        'langfuse.laravel_ai_enabled' => true,
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

    // Bind recording batcher AFTER register()
    $batcher = new \Axyr\Langfuse\Testing\RecordingEventBatcher();
    $this->app->instance(\Axyr\Langfuse\Contracts\EventBatcherInterface::class, $batcher);

    $provider->boot();

    $agent = new class () implements \Laravel\Ai\Contracts\Agent {};
    $textProvider = new class () implements \Laravel\Ai\Contracts\Providers\TextProvider {};
    $tool = new class () implements \Laravel\Ai\Contracts\Tool {};
    $prompt = new \Laravel\Ai\Prompts\AgentPrompt(
        agent: $agent,
        prompt: 'Search for something',
        provider: $textProvider,
        model: 'gpt-4',
    );

    // Agent prompt first (creates trace)
    event(new \Laravel\Ai\Events\PromptingAgent(invocationId: 'inv-1', prompt: $prompt));

    // Tool invocation
    event(new \Laravel\Ai\Events\InvokingTool(
        invocationId: 'inv-1',
        toolInvocationId: 'tool-1',
        agent: $agent,
        tool: $tool,
        arguments: ['query' => 'test'],
    ));

    event(new \Laravel\Ai\Events\ToolInvoked(
        invocationId: 'inv-1',
        toolInvocationId: 'tool-1',
        agent: $agent,
        tool: $tool,
        arguments: ['query' => 'test'],
        result: 'Search results here',
    ));

    $types = array_map(
        fn(\Axyr\Langfuse\Dto\IngestionEvent $e) => $e->type->value,
        $batcher->events(),
    );

    expect($types)->toContain('trace-create')
        ->and($types)->toContain('span-create')
        ->and($types)->toContain('span-update');
});
