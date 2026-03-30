<?php

declare(strict_types=1);

namespace Langfuse;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use Langfuse\Api\IngestionApiClient;
use Langfuse\Batch\EventBatcher;
use Langfuse\Batch\NullEventBatcher;
use Langfuse\Config\LangfuseConfig;
use Langfuse\Contracts\EventBatcherInterface;
use Langfuse\Contracts\IngestionApiClientInterface;
use Langfuse\Contracts\LangfuseClientInterface;

class LangfuseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/langfuse.php', 'langfuse');

        $this->app->singleton(LangfuseConfig::class, function () {
            /** @var Repository $repository */
            $repository = $this->app->make(Repository::class);

            /** @var array<string, mixed> $config */
            $config = $repository->get('langfuse', []);

            return LangfuseConfig::fromArray($config);
        });

        $this->app->singleton(IngestionApiClientInterface::class, IngestionApiClient::class);

        $this->app->singleton(EventBatcherInterface::class, function () {
            /** @var LangfuseConfig $config */
            $config = $this->app->make(LangfuseConfig::class);

            if (! $config->enabled) {
                return new NullEventBatcher();
            }

            return $this->app->make(EventBatcher::class);
        });

        $this->app->singleton(LangfuseClientInterface::class, LangfuseClient::class);
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
    }
}
