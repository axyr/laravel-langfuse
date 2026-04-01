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
    );

    expect($config->publicKey)->toBe('pk-test')
        ->and($config->secretKey)->toBe('sk-test')
        ->and($config->baseUrl)->toBe('https://custom.langfuse.com')
        ->and($config->enabled)->toBeFalse()
        ->and($config->flushAt)->toBe(20)
        ->and($config->requestTimeout)->toBe(30)
        ->and($config->promptCacheTtl)->toBe(120)
        ->and($config->prismEnabled)->toBeTrue();
});

it('has sensible defaults', function () {
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');

    expect($config->baseUrl)->toBe('https://cloud.langfuse.com')
        ->and($config->enabled)->toBeTrue()
        ->and($config->flushAt)->toBe(10)
        ->and($config->requestTimeout)->toBe(15)
        ->and($config->promptCacheTtl)->toBe(60)
        ->and($config->prismEnabled)->toBeFalse();
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
    ]);

    expect($config->publicKey)->toBe('pk-arr')
        ->and($config->secretKey)->toBe('sk-arr')
        ->and($config->baseUrl)->toBe('https://arr.langfuse.com')
        ->and($config->enabled)->toBeFalse()
        ->and($config->flushAt)->toBe(25)
        ->and($config->requestTimeout)->toBe(20)
        ->and($config->promptCacheTtl)->toBe(90)
        ->and($config->prismEnabled)->toBeTrue();
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

it('generates correct prompts list url', function () {
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');

    expect($config->promptsUrl())->toBe('https://cloud.langfuse.com/api/public/v2/prompts');
});
