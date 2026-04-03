<?php

declare(strict_types=1);

namespace Axyr\Langfuse;

use Axyr\Langfuse\Api\IngestionApiClient;
use Axyr\Langfuse\Api\PromptApiClient;
use Axyr\Langfuse\Api\ScoreApiClient;
use Axyr\Langfuse\Batch\EventBatcher;
use Axyr\Langfuse\Batch\NullEventBatcher;
use Axyr\Langfuse\Batch\QueuedEventBatcher;
use Axyr\Langfuse\Cache\PromptCache;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\EventBatcherInterface;
use Axyr\Langfuse\Contracts\IngestionApiClientInterface;
use Axyr\Langfuse\Contracts\LangfuseClientInterface;
use Axyr\Langfuse\Contracts\PromptApiClientInterface;
use Axyr\Langfuse\Contracts\PromptCacheInterface;
use Axyr\Langfuse\Contracts\ScoreApiClientInterface;
use Axyr\Langfuse\Prompt\PromptManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class LangfuseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/langfuse.php', 'langfuse');

        $this->registerConfig();
        $this->registerIngestion();
        $this->registerPrompts();

        $this->app->scoped(LangfuseClientInterface::class, LangfuseClient::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/langfuse.php' => $this->app->configPath('langfuse.php'),
        ], 'langfuse-config');

        $this->app->terminating(function () {
            /** @var EventBatcherInterface $batcher */
            $batcher = $this->app->make(EventBatcherInterface::class);
            $batcher->flush();
        });

        $this->registerPrismIntegration();
        $this->registerLaravelAiIntegration();
        $this->registerNeuronAiIntegration();
    }

    private function registerConfig(): void
    {
        $this->app->singleton(LangfuseConfig::class, function () {
            /** @var Repository $repository */
            $repository = $this->app->make(Repository::class);

            /** @var array<string, mixed> $config */
            $config = $repository->get('langfuse', []);

            return LangfuseConfig::fromArray($config);
        });
    }

    private function registerIngestion(): void
    {
        $this->app->singleton(IngestionApiClientInterface::class, IngestionApiClient::class);
        $this->app->singleton(ScoreApiClientInterface::class, ScoreApiClient::class);

        $this->app->scoped(EventBatcherInterface::class, function () {
            /** @var LangfuseConfig $config */
            $config = $this->app->make(LangfuseConfig::class);

            if (! $config->enabled) {
                return new NullEventBatcher();
            }

            if ($config->queue !== null) {
                return $this->app->make(QueuedEventBatcher::class);
            }

            return $this->app->make(EventBatcher::class);
        });
    }

    private function registerPrompts(): void
    {
        $this->app->singleton(PromptApiClientInterface::class, PromptApiClient::class);
        $this->app->singleton(PromptCacheInterface::class, PromptCache::class);

        $this->app->singleton(PromptManager::class, function () {
            /** @var LangfuseConfig $config */
            $config = $this->app->make(LangfuseConfig::class);

            return new PromptManager(
                apiClient: $this->app->make(PromptApiClientInterface::class),
                cache: $this->app->make(PromptCacheInterface::class),
                cacheTtl: $config->promptCacheTtl,
            );
        });
    }

    private function registerLaravelAiIntegration(): void
    {
        if (! class_exists(\Laravel\Ai\Events\PromptingAgent::class)) {
            return;
        }

        /** @var LangfuseConfig $config */
        $config = $this->app->make(LangfuseConfig::class);

        if (! $config->laravelAiEnabled) {
            return;
        }

        $this->app->scoped(LaravelAi\LaravelAiSubscriber::class);

        Event::subscribe(LaravelAi\LaravelAiSubscriber::class);
    }

    private function registerNeuronAiIntegration(): void
    {
        if (! interface_exists(\NeuronAI\Observability\ObserverInterface::class)) {
            return;
        }

        /** @var LangfuseConfig $config */
        $config = $this->app->make(LangfuseConfig::class);

        if (! $config->neuronAiEnabled) {
            return;
        }

        $this->app->scoped(NeuronAi\NeuronAiObserver::class);
    }

    private function registerPrismIntegration(): void
    {
        if (! class_exists(\Prism\Prism\PrismManager::class)) {
            return;
        }

        /** @var LangfuseConfig $config */
        $config = $this->app->make(LangfuseConfig::class);

        if (! $config->shouldEnablePrism()) {
            return;
        }

        $this->app->extend(\Prism\Prism\PrismManager::class, function (\Prism\Prism\PrismManager $manager) {
            return new Prism\TracingPrismManager(
                app: $this->app,
                inner: $manager,
                langfuse: $this->app->make(LangfuseClientInterface::class),
            );
        });
    }
}
