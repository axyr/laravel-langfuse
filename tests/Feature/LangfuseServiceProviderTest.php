<?php

declare(strict_types=1);

use Axyr\Langfuse\Batch\NullEventBatcher;
use Axyr\Langfuse\Batch\QueuedEventBatcher;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\EventBatcherInterface;
use Axyr\Langfuse\Contracts\ExperimentApiClientInterface;
use Axyr\Langfuse\Contracts\IngestionApiClientInterface;
use Axyr\Langfuse\Contracts\LangfuseClientInterface;
use Axyr\Langfuse\Contracts\MetricsApiClientInterface;
use Axyr\Langfuse\Contracts\ObservationApiClientInterface;
use Axyr\Langfuse\Contracts\OtelTraceApiClientInterface;
use Axyr\Langfuse\Contracts\PromptApiClientInterface;
use Axyr\Langfuse\Contracts\PromptCacheInterface;
use Axyr\Langfuse\Contracts\ScoreApiClientInterface;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\LangfuseClient;
use Axyr\Langfuse\LangfuseServiceProvider;
use Axyr\Langfuse\Objects\OpenObservationRegistry;
use Axyr\Langfuse\Prompt\CurrentPromptRegistry;
use Axyr\Langfuse\Prompt\PromptManager;

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

it('binds EventBatcherInterface as scoped', function () {
    $batcher1 = $this->app->make(EventBatcherInterface::class);
    $batcher2 = $this->app->make(EventBatcherInterface::class);

    expect($batcher1)->toBe($batcher2);
});

it('binds LangfuseClientInterface as scoped', function () {
    $client1 = $this->app->make(LangfuseClientInterface::class);
    $client2 = $this->app->make(LangfuseClientInterface::class);

    expect($client1)->toBeInstanceOf(LangfuseClient::class)
        ->and($client1)->toBe($client2);
});

it('uses NullEventBatcher when disabled', function () {
    config(['langfuse.enabled' => false]);

    // Clear the singleton so it re-resolves with new config
    $this->app->forgetInstance(LangfuseConfig::class);
    $this->app->forgetInstance(EventBatcherInterface::class);

    $batcher = $this->app->make(EventBatcherInterface::class);

    expect($batcher)->toBeInstanceOf(NullEventBatcher::class);
});

it('uses QueuedEventBatcher when queue is configured', function () {
    config(['langfuse.queue' => 'langfuse']);

    $this->app->forgetInstance(LangfuseConfig::class);
    $this->app->forgetInstance(EventBatcherInterface::class);

    $batcher = $this->app->make(EventBatcherInterface::class);

    expect($batcher)->toBeInstanceOf(QueuedEventBatcher::class);
});

it('binds ScoreApiClientInterface as singleton', function () {
    $client1 = $this->app->make(ScoreApiClientInterface::class);
    $client2 = $this->app->make(ScoreApiClientInterface::class);

    expect($client1)->toBe($client2);
});

it('binds PromptApiClientInterface as singleton', function () {
    $client1 = $this->app->make(PromptApiClientInterface::class);
    $client2 = $this->app->make(PromptApiClientInterface::class);

    expect($client1)->toBe($client2);
});

it('binds PromptCacheInterface as singleton', function () {
    $cache1 = $this->app->make(PromptCacheInterface::class);
    $cache2 = $this->app->make(PromptCacheInterface::class);

    expect($cache1)->toBe($cache2);
});

it('binds PromptManager as singleton', function () {
    $manager1 = $this->app->make(PromptManager::class);
    $manager2 = $this->app->make(PromptManager::class);

    expect($manager1)->toBe($manager2);
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

it('binds CurrentPromptRegistry as scoped and forgets it between scopes', function () {
    $registry1 = $this->app->make(CurrentPromptRegistry::class);
    $registry2 = $this->app->make(CurrentPromptRegistry::class);

    expect($registry1)->toBe($registry2);

    $this->app->forgetScopedInstances();

    expect($this->app->make(CurrentPromptRegistry::class))->not->toBe($registry1);
});

it('binds the read api clients as singletons', function (string $interface) {
    expect($this->app->make($interface))->toBe($this->app->make($interface));
})->with([
    OtelTraceApiClientInterface::class,
    ObservationApiClientInterface::class,
    MetricsApiClientInterface::class,
    ExperimentApiClientInterface::class,
]);

it('binds the observation registry as scoped', function () {
    $registry = $this->app->make(OpenObservationRegistry::class);

    expect($registry)->toBeInstanceOf(OpenObservationRegistry::class)
        ->and($this->app->make(OpenObservationRegistry::class))->toBe($registry);

    $this->app->forgetScopedInstances();

    expect($this->app->make(OpenObservationRegistry::class))->not->toBe($registry);
});

it('no longer binds the removed dataset run client', function () {
    expect(interface_exists('Axyr\\Langfuse\\Contracts\\DatasetRunApiClientInterface'))->toBeFalse();
});

it('ends the open observations and flushes when the application terminates', function () {
    config([
        'langfuse.public_key' => 'pk-test',
        'langfuse.secret_key' => 'sk-test',
        'langfuse.flush_at' => 100,
    ]);

    $this->app->forgetInstance(LangfuseConfig::class);
    $this->app->forgetScopedInstances();

    $batcher = new \Axyr\Langfuse\Testing\RecordingEventBatcher();
    $this->app->instance(EventBatcherInterface::class, $batcher);

    /** @var LangfuseClientInterface $langfuse */
    $langfuse = $this->app->make(LangfuseClientInterface::class);

    $trace = $langfuse->trace(new TraceBody(name: 'request'));
    $trace->span(new SpanBody(name: 'work'));

    expect($batcher->observations())->toBeEmpty();

    $this->app->terminate();

    expect($batcher->observations())->toHaveCount(2)
        ->and($trace->hasEnded())->toBeTrue();
});
