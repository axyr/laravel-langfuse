<?php

declare(strict_types=1);

use Axyr\Langfuse\Config\LangfuseConfig;

it('can be constructed with all parameters', function () {
    $config = new LangfuseConfig(
        publicKey: 'pk-test',
        secretKey: 'sk-test',
        baseUrl: 'https://custom.langfuse.com',
        enabled: false,
        flushAt: 20,
        requestTimeout: 30,
        promptCacheTtl: 120,
        prismEnabled: true,
        queue: 'langfuse',
    );

    expect($config->publicKey)->toBe('pk-test')
        ->and($config->secretKey)->toBe('sk-test')
        ->and($config->baseUrl)->toBe('https://custom.langfuse.com')
        ->and($config->enabled)->toBeFalse()
        ->and($config->flushAt)->toBe(20)
        ->and($config->requestTimeout)->toBe(30)
        ->and($config->promptCacheTtl)->toBe(120)
        ->and($config->prismEnabled)->toBeTrue()
        ->and($config->queue)->toBe('langfuse');
});

it('has sensible defaults', function () {
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');

    expect($config->baseUrl)->toBe('https://cloud.langfuse.com')
        ->and($config->enabled)->toBeTrue()
        ->and($config->flushAt)->toBe(10)
        ->and($config->requestTimeout)->toBe(15)
        ->and($config->promptCacheTtl)->toBe(60)
        ->and($config->prismEnabled)->toBeFalse()
        ->and($config->queue)->toBeNull();
});

it('can be created from array', function () {
    $config = LangfuseConfig::fromArray([
        'public_key' => 'pk-arr',
        'secret_key' => 'sk-arr',
        'base_url' => 'https://arr.langfuse.com',
        'enabled' => false,
        'flush_at' => 25,
        'request_timeout' => 20,
        'prompt_cache_ttl' => 90,
        'prism_enabled' => true,
        'queue' => 'langfuse',
    ]);

    expect($config->publicKey)->toBe('pk-arr')
        ->and($config->secretKey)->toBe('sk-arr')
        ->and($config->baseUrl)->toBe('https://arr.langfuse.com')
        ->and($config->enabled)->toBeFalse()
        ->and($config->flushAt)->toBe(25)
        ->and($config->requestTimeout)->toBe(20)
        ->and($config->promptCacheTtl)->toBe(90)
        ->and($config->prismEnabled)->toBeTrue()
        ->and($config->queue)->toBe('langfuse');
});

it('uses defaults for missing array keys', function () {
    $config = LangfuseConfig::fromArray([]);

    expect($config->publicKey)->toBe('')
        ->and($config->secretKey)->toBe('')
        ->and($config->baseUrl)->toBe('https://cloud.langfuse.com')
        ->and($config->enabled)->toBeTrue()
        ->and($config->flushAt)->toBe(10);
});

it('generates correct auth header', function () {
    $config = new LangfuseConfig(publicKey: 'pk-test', secretKey: 'sk-test');
    $expected = 'Basic ' . base64_encode('pk-test:sk-test');

    expect($config->authHeader())->toBe($expected);
});

it('generates correct ingestion url', function () {
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', baseUrl: 'https://cloud.langfuse.com');

    expect($config->ingestionUrl())->toBe('https://cloud.langfuse.com/api/public/ingestion');
});

it('strips trailing slash from base url in ingestion url', function () {
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', baseUrl: 'https://cloud.langfuse.com/');

    expect($config->ingestionUrl())->toBe('https://cloud.langfuse.com/api/public/ingestion');
});

it('coerces string values from env in fromArray', function () {
    $config = LangfuseConfig::fromArray([
        'public_key' => 'pk-env',
        'secret_key' => 'sk-env',
        'flush_at' => '20',
        'request_timeout' => '30',
        'enabled' => 'true',
    ]);

    expect($config->flushAt)->toBe(20)
        ->and($config->requestTimeout)->toBe(30)
        ->and($config->enabled)->toBeTrue();
});

it('parses string false as disabled in fromArray', function () {
    $config = LangfuseConfig::fromArray([
        'enabled' => 'false',
    ]);

    expect($config->enabled)->toBeFalse();
});

it('parses string zero as disabled in fromArray', function () {
    $config = LangfuseConfig::fromArray([
        'enabled' => '0',
    ]);

    expect($config->enabled)->toBeFalse();
});

it('generates correct prompts url', function () {
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', baseUrl: 'https://cloud.langfuse.com');

    expect($config->promptsUrl('movie-critic'))->toBe('https://cloud.langfuse.com/api/public/v2/prompts/movie-critic');
});

it('encodes prompt name in url', function () {
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');

    expect($config->promptsUrl('my prompt'))->toBe('https://cloud.langfuse.com/api/public/v2/prompts/my+prompt');
});

it('parses prompt_cache_ttl from string in fromArray', function () {
    $config = LangfuseConfig::fromArray([
        'prompt_cache_ttl' => '120',
    ]);

    expect($config->promptCacheTtl)->toBe(120);
});

it('parses prism_enabled from string in fromArray', function () {
    $config = LangfuseConfig::fromArray([
        'prism_enabled' => 'true',
    ]);

    expect($config->prismEnabled)->toBeTrue();
});

it('defaults prompt_cache_ttl and prism_enabled for missing array keys', function () {
    $config = LangfuseConfig::fromArray([]);

    expect($config->promptCacheTtl)->toBe(60)
        ->and($config->prismEnabled)->toBeFalse();
});

it('generates correct scores url', function () {
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');

    expect($config->scoresUrl())->toBe('https://cloud.langfuse.com/api/public/scores');
});

it('generates correct scores url with id', function () {
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');

    expect($config->scoresUrl('score-123'))->toBe('https://cloud.langfuse.com/api/public/scores/score-123');
});

it('parses queue from fromArray', function () {
    $config = LangfuseConfig::fromArray([
        'queue' => 'langfuse',
    ]);

    expect($config->queue)->toBe('langfuse');
});

it('defaults queue to null for missing array key', function () {
    $config = LangfuseConfig::fromArray([]);

    expect($config->queue)->toBeNull();
});

it('defaults queue to null for empty string', function () {
    $config = LangfuseConfig::fromArray([
        'queue' => '',
    ]);

    expect($config->queue)->toBeNull();
});

it('generates correct prompts list url', function () {
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');

    expect($config->promptsUrl())->toBe('https://cloud.langfuse.com/api/public/v2/prompts');
});

it('should enable prism when explicitly enabled', function () {
    $config = new LangfuseConfig(
        publicKey: 'pk',
        secretKey: 'sk',
        prismEnabled: true,
        laravelAiEnabled: false,
    );

    expect($config->shouldEnablePrism())->toBeTrue();
});

it('should enable prism when laravel ai is enabled', function () {
    $config = new LangfuseConfig(
        publicKey: 'pk',
        secretKey: 'sk',
        prismEnabled: false,
        laravelAiEnabled: true,
    );

    expect($config->shouldEnablePrism())->toBeTrue();
});

it('should enable prism when both are enabled', function () {
    $config = new LangfuseConfig(
        publicKey: 'pk',
        secretKey: 'sk',
        prismEnabled: true,
        laravelAiEnabled: true,
    );

    expect($config->shouldEnablePrism())->toBeTrue();
});

it('should not enable prism when both are disabled', function () {
    $config = new LangfuseConfig(
        publicKey: 'pk',
        secretKey: 'sk',
        prismEnabled: false,
        laravelAiEnabled: false,
    );

    expect($config->shouldEnablePrism())->toBeFalse();
});

it('parses the environment from config', function () {
    $config = LangfuseConfig::fromArray(['environment' => 'production']);

    expect($config->environment)->toBe('production');
});

it('defaults the environment to null', function () {
    expect(LangfuseConfig::fromArray([])->environment)->toBeNull()
        ->and(LangfuseConfig::fromArray(['environment' => ''])->environment)->toBeNull();
});

it('accepts well-formed environments', function (string $environment) {
    expect(LangfuseConfig::fromArray(['environment' => $environment])->environment)->toBe($environment);
})->with(['production', 'staging-eu_1', str_repeat('a', 40)]);

it('rejects malformed environments', function (string $environment) {
    LangfuseConfig::fromArray(['environment' => $environment]);
})->with(['Production', 'staging.eu', 'langfuse-test', str_repeat('a', 41)])
    ->throws(InvalidArgumentException::class, 'Invalid Langfuse environment');

it('defaults user and session tracing to enabled', function () {
    $config = LangfuseConfig::fromArray([]);

    expect($config->userTracingEnabled)->toBeTrue()
        ->and($config->sessionTracingEnabled)->toBeTrue();
});

it('parses user_tracing and session_tracing from array', function () {
    $config = LangfuseConfig::fromArray(['user_tracing' => 'false', 'session_tracing' => false]);

    expect($config->userTracingEnabled)->toBeFalse()
        ->and($config->sessionTracingEnabled)->toBeFalse();
});

it('generates the otlp traces url', function () {
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');

    expect($config->otelTracesUrl())->toBe('https://cloud.langfuse.com/api/public/otel/v1/traces');
});

it('strips a trailing slash from the base url in the otlp traces url', function () {
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', baseUrl: 'https://cloud.langfuse.com/');

    expect($config->otelTracesUrl())->toBe('https://cloud.langfuse.com/api/public/otel/v1/traces');
});

it('generates the v3 scores url', function () {
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');

    expect($config->scoresV3Url())->toBe('https://cloud.langfuse.com/api/public/v3/scores');
});

it('generates the v2 observations url', function () {
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');

    expect($config->observationsV2Url())->toBe('https://cloud.langfuse.com/api/public/v2/observations');
});

it('generates the v2 metrics url', function () {
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');

    expect($config->metricsV2Url())->toBe('https://cloud.langfuse.com/api/public/v2/metrics');
});

it('generates the experiment urls', function () {
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');

    expect($config->experimentsUrl())->toBe('https://cloud.langfuse.com/api/public/experiments')
        ->and($config->experimentItemsUrl())->toBe('https://cloud.langfuse.com/api/public/experiment-items');
});

it('generates the dataset urls', function () {
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');

    expect($config->datasetsUrl())->toBe('https://cloud.langfuse.com/api/public/v2/datasets')
        ->and($config->datasetsUrl('my set'))->toBe('https://cloud.langfuse.com/api/public/v2/datasets/my+set')
        ->and($config->datasetItemsUrl())->toBe('https://cloud.langfuse.com/api/public/dataset-items')
        ->and($config->datasetItemsUrl('item-1'))->toBe('https://cloud.langfuse.com/api/public/dataset-items/item-1');
});

it('no longer builds the deprecated v1 and v2 read urls', function () {
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');

    expect(method_exists($config, 'observationsUrl'))->toBeFalse()
        ->and(method_exists($config, 'metricsUrl'))->toBeFalse()
        ->and(method_exists($config, 'scoresV2Url'))->toBeFalse()
        ->and(method_exists($config, 'datasetRunsUrl'))->toBeFalse()
        ->and(method_exists($config, 'datasetRunItemsUrl'))->toBeFalse()
        ->and(method_exists($config, 'batchMetadata'))->toBeFalse();
});

it('has defaults for the new v4 keys', function () {
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');

    expect($config->serviceName)->toBe('laravel')
        ->and($config->compression)->toBeFalse()
        ->and($config->release)->toBeNull();
});

it('reads the new v4 keys from array', function () {
    $config = LangfuseConfig::fromArray([
        'service_name' => 'checkout-api',
        'compression' => 'true',
        'release' => '1.2.3',
    ]);

    expect($config->serviceName)->toBe('checkout-api')
        ->and($config->compression)->toBeTrue()
        ->and($config->release)->toBe('1.2.3');
});

it('defaults the release to null for an empty string', function () {
    expect(LangfuseConfig::fromArray(['release' => ''])->release)->toBeNull();
});
