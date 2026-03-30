<?php

declare(strict_types=1);

use Langfuse\Config\LangfuseConfig;

it('can be constructed with all parameters', function () {
    $config = new LangfuseConfig(
        publicKey: 'pk-test',
        secretKey: 'sk-test',
        baseUrl: 'https://custom.langfuse.com',
        enabled: false,
        flushAt: 20,
        requestTimeout: 30,
    );

    expect($config->publicKey)->toBe('pk-test')
        ->and($config->secretKey)->toBe('sk-test')
        ->and($config->baseUrl)->toBe('https://custom.langfuse.com')
        ->and($config->enabled)->toBeFalse()
        ->and($config->flushAt)->toBe(20)
        ->and($config->requestTimeout)->toBe(30);
});

it('has sensible defaults', function () {
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');

    expect($config->baseUrl)->toBe('https://cloud.langfuse.com')
        ->and($config->enabled)->toBeTrue()
        ->and($config->flushAt)->toBe(10)
        ->and($config->requestTimeout)->toBe(15);
});

it('can be created from array', function () {
    $config = LangfuseConfig::fromArray([
        'public_key' => 'pk-arr',
        'secret_key' => 'sk-arr',
        'base_url' => 'https://arr.langfuse.com',
        'enabled' => false,
        'flush_at' => 25,
        'request_timeout' => 20,
    ]);

    expect($config->publicKey)->toBe('pk-arr')
        ->and($config->secretKey)->toBe('sk-arr')
        ->and($config->baseUrl)->toBe('https://arr.langfuse.com')
        ->and($config->enabled)->toBeFalse()
        ->and($config->flushAt)->toBe(25)
        ->and($config->requestTimeout)->toBe(20);
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
