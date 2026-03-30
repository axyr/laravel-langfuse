<?php

declare(strict_types=1);

use Langfuse\Batch\NullEventBatcher;
use Langfuse\Config\LangfuseConfig;
use Langfuse\Contracts\EventBatcherInterface;
use Langfuse\Contracts\IngestionApiClientInterface;
use Langfuse\Contracts\LangfuseClientInterface;
use Langfuse\LangfuseClient;
use Langfuse\LangfuseServiceProvider;

it('registers the service provider', function () {
    expect($this->app->getProviders(LangfuseServiceProvider::class))->not->toBeEmpty();
});

it('merges config', function () {
    expect(config('langfuse'))->toBeArray()
        ->and(config('langfuse.base_url'))->toBe('https://cloud.langfuse.com')
        ->and(config('langfuse.flush_at'))->toBe(10);
});

it('binds LangfuseConfig as singleton', function () {
    $config1 = $this->app->make(LangfuseConfig::class);
    $config2 = $this->app->make(LangfuseConfig::class);

    expect($config1)->toBeInstanceOf(LangfuseConfig::class)
        ->and($config1)->toBe($config2);
});

it('binds IngestionApiClientInterface as singleton', function () {
    $client1 = $this->app->make(IngestionApiClientInterface::class);
    $client2 = $this->app->make(IngestionApiClientInterface::class);

    expect($client1)->toBe($client2);
});

it('binds EventBatcherInterface as singleton', function () {
    $batcher1 = $this->app->make(EventBatcherInterface::class);
    $batcher2 = $this->app->make(EventBatcherInterface::class);

    expect($batcher1)->toBe($batcher2);
});

it('binds LangfuseClientInterface to LangfuseClient', function () {
    $client = $this->app->make(LangfuseClientInterface::class);

    expect($client)->toBeInstanceOf(LangfuseClient::class);
});

it('uses NullEventBatcher when disabled', function () {
    config(['langfuse.enabled' => false]);

    // Clear the singleton so it re-resolves with new config
    $this->app->forgetInstance(LangfuseConfig::class);
    $this->app->forgetInstance(EventBatcherInterface::class);

    $batcher = $this->app->make(EventBatcherInterface::class);

    expect($batcher)->toBeInstanceOf(NullEventBatcher::class);
});

it('reads config from environment', function () {
    config([
        'langfuse.public_key' => 'pk-test-env',
        'langfuse.secret_key' => 'sk-test-env',
    ]);

    $this->app->forgetInstance(LangfuseConfig::class);
    $config = $this->app->make(LangfuseConfig::class);

    expect($config->publicKey)->toBe('pk-test-env')
        ->and($config->secretKey)->toBe('sk-test-env');
});
