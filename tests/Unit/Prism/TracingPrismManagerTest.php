<?php

declare(strict_types=1);

use Axyr\Langfuse\Contracts\LangfuseClientInterface;
use Axyr\Langfuse\Prism\TracingPrismManager;
use Axyr\Langfuse\Prism\TracingProvider;
use Illuminate\Contracts\Foundation\Application;
use Prism\Prism\PrismManager;
use Prism\Prism\Providers\Provider;

it('wraps resolved provider with TracingProvider', function () {
    $innerProvider = Mockery::mock(Provider::class);

    $innerManager = Mockery::mock(PrismManager::class);
    $innerManager->shouldReceive('resolve')
        ->with('openai', [])
        ->once()
        ->andReturn($innerProvider);

    $langfuse = Mockery::mock(LangfuseClientInterface::class);
    $app = Mockery::mock(Application::class);

    $manager = new TracingPrismManager(
        app: $app,
        inner: $innerManager,
        langfuse: $langfuse,
    );

    $provider = $manager->resolve('openai');

    expect($provider)->toBeInstanceOf(TracingProvider::class);
});

it('delegates extend to inner manager', function () {
    $innerManager = Mockery::mock(PrismManager::class);
    $innerManager->shouldReceive('extend')
        ->with('custom', Mockery::type(Closure::class))
        ->once()
        ->andReturnSelf();

    $langfuse = Mockery::mock(LangfuseClientInterface::class);
    $app = Mockery::mock(Application::class);

    $manager = new TracingPrismManager(
        app: $app,
        inner: $innerManager,
        langfuse: $langfuse,
    );

    $result = $manager->extend('custom', fn() => Mockery::mock(Provider::class));

    expect($result)->toBe($manager);
});

it('passes provider config to inner resolve', function () {
    $innerProvider = Mockery::mock(Provider::class);
    $config = ['api_key' => 'test-key'];

    $innerManager = Mockery::mock(PrismManager::class);
    $innerManager->shouldReceive('resolve')
        ->with('openai', $config)
        ->once()
        ->andReturn($innerProvider);

    $langfuse = Mockery::mock(LangfuseClientInterface::class);
    $app = Mockery::mock(Application::class);

    $manager = new TracingPrismManager(
        app: $app,
        inner: $innerManager,
        langfuse: $langfuse,
    );

    $provider = $manager->resolve('openai', $config);

    expect($provider)->toBeInstanceOf(TracingProvider::class);
});
