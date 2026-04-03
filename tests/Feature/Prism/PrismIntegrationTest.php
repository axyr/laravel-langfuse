<?php

declare(strict_types=1);

use Axyr\Langfuse\Prism\TracingPrismManager;
use Prism\Prism\PrismManager;

it('does not wrap PrismManager when disabled', function () {
    config(['langfuse.prism_enabled' => false]);

    $manager = $this->app->make(PrismManager::class);

    expect($manager)->not->toBeInstanceOf(TracingPrismManager::class);
});

it('wraps PrismManager when enabled', function () {
    config(['langfuse.prism_enabled' => true]);

    // Re-bootstrap with prism enabled
    $this->app->forgetInstance(\Axyr\Langfuse\Config\LangfuseConfig::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Contracts\LangfuseClientInterface::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Contracts\EventBatcherInterface::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Prompt\PromptManager::class);

    // Manually call the prism integration registration
    $provider = new \Axyr\Langfuse\LangfuseServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    $manager = $this->app->make(PrismManager::class);

    expect($manager)->toBeInstanceOf(TracingPrismManager::class);
});

it('wraps PrismManager when Laravel AI is enabled', function () {
    config([
        'langfuse.prism_enabled' => false,
        'langfuse.laravel_ai_enabled' => true,
    ]);

    // Re-bootstrap with Laravel AI enabled
    $this->app->forgetInstance(\Axyr\Langfuse\Config\LangfuseConfig::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Contracts\LangfuseClientInterface::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Contracts\EventBatcherInterface::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Prompt\PromptManager::class);

    // Manually call the prism integration registration
    $provider = new \Axyr\Langfuse\LangfuseServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    $manager = $this->app->make(PrismManager::class);

    expect($manager)->toBeInstanceOf(TracingPrismManager::class);
});
