<?php

declare(strict_types=1);

use Axyr\Langfuse\Cache\PromptCache;
use Axyr\Langfuse\Contracts\PromptApiClientInterface;
use Axyr\Langfuse\Dto\TextPrompt;
use Axyr\Langfuse\Exceptions\PromptNotFoundException;
use Axyr\Langfuse\Prompt\PromptManager;

it('returns cached prompt when fresh', function () {
    $api = Mockery::mock(PromptApiClientInterface::class);
    $api->shouldNotReceive('get');

    $cache = new PromptCache();
    $prompt = new TextPrompt(name: 'test', version: 1, prompt: 'cached');
    $cache->put('prompt:test', $prompt, 60);

    $manager = new PromptManager($api, $cache, 60);

    expect($manager->get('test'))->toBe($prompt);
});

it('fetches from api on cache miss', function () {
    $api = Mockery::mock(PromptApiClientInterface::class);
    $api->shouldReceive('get')
        ->with('test', null, null)
        ->once()
        ->andReturn([
            'name' => 'test',
            'version' => 1,
            'type' => 'text',
            'prompt' => 'from api',
        ]);

    $cache = new PromptCache();
    $manager = new PromptManager($api, $cache, 60);

    $result = $manager->get('test');

    expect($result->getName())->toBe('test')
        ->and($result->compile())->toBe('from api');
});

it('caches prompt after api fetch', function () {
    $api = Mockery::mock(PromptApiClientInterface::class);
    $api->shouldReceive('get')
        ->once()
        ->andReturn([
            'name' => 'test',
            'version' => 1,
            'type' => 'text',
            'prompt' => 'from api',
        ]);

    $cache = new PromptCache();
    $manager = new PromptManager($api, $cache, 60);

    // First call fetches from API
    $manager->get('test');

    // Second call should use cache (api only called once)
    $result = $manager->get('test');

    expect($result->compile())->toBe('from api');
});

it('returns stale cache when api fails', function () {
    $api = Mockery::mock(PromptApiClientInterface::class);
    $api->shouldReceive('get')
        ->once()
        ->andReturn(null);

    $cache = new PromptCache();
    $stalePrompt = new TextPrompt(name: 'test', version: 1, prompt: 'stale');
    $cache->put('prompt:test', $stalePrompt, 0);
    usleep(1000);

    $manager = new PromptManager($api, $cache, 60);

    $result = $manager->get('test');

    expect($result)->toBe($stalePrompt);
});

it('refreshes stale cache when api succeeds', function () {
    $api = Mockery::mock(PromptApiClientInterface::class);
    $api->shouldReceive('get')
        ->once()
        ->andReturn([
            'name' => 'test',
            'version' => 2,
            'type' => 'text',
            'prompt' => 'fresh',
        ]);

    $cache = new PromptCache();
    $stalePrompt = new TextPrompt(name: 'test', version: 1, prompt: 'stale');
    $cache->put('prompt:test', $stalePrompt, 0);
    usleep(1000);

    $manager = new PromptManager($api, $cache, 60);

    $result = $manager->get('test');

    expect($result->compile())->toBe('fresh')
        ->and($result->getVersion())->toBe(2);
});

it('returns text fallback when cache empty and api fails', function () {
    $api = Mockery::mock(PromptApiClientInterface::class);
    $api->shouldReceive('get')->once()->andReturn(null);

    $cache = new PromptCache();
    $manager = new PromptManager($api, $cache, 60);

    $result = $manager->get('test', fallback: 'fallback {{var}}');

    expect($result->isFallback())->toBeTrue()
        ->and($result->compile(['var' => 'value']))->toBe('fallback value');
});

it('returns chat fallback when cache empty and api fails', function () {
    $api = Mockery::mock(PromptApiClientInterface::class);
    $api->shouldReceive('get')->once()->andReturn(null);

    $cache = new PromptCache();
    $manager = new PromptManager($api, $cache, 60);

    $result = $manager->get('test', fallback: [['role' => 'user', 'content' => 'hi']]);

    expect($result->isFallback())->toBeTrue()
        ->and($result->compile())->toBe([['role' => 'user', 'content' => 'hi']]);
});

it('throws when no cache, api fails, and no fallback', function () {
    $api = Mockery::mock(PromptApiClientInterface::class);
    $api->shouldReceive('get')->once()->andReturn(null);

    $cache = new PromptCache();
    $manager = new PromptManager($api, $cache, 60);

    $manager->get('nonexistent');
})->throws(PromptNotFoundException::class, "Prompt 'nonexistent' not found and no fallback provided.");

it('passes version and label to api client', function () {
    $api = Mockery::mock(PromptApiClientInterface::class);
    $api->shouldReceive('get')
        ->with('test', 3, 'staging')
        ->once()
        ->andReturn([
            'name' => 'test',
            'version' => 3,
            'type' => 'text',
            'prompt' => 'versioned',
        ]);

    $cache = new PromptCache();
    $manager = new PromptManager($api, $cache, 60);

    $result = $manager->get('test', version: 3, label: 'staging');

    expect($result->getVersion())->toBe(3);
});

it('uses different cache keys for version and label', function () {
    $api = Mockery::mock(PromptApiClientInterface::class);
    $api->shouldReceive('get')
        ->with('test', 1, null)
        ->once()
        ->andReturn([
            'name' => 'test',
            'version' => 1,
            'type' => 'text',
            'prompt' => 'v1',
        ]);
    $api->shouldReceive('get')
        ->with('test', 2, null)
        ->once()
        ->andReturn([
            'name' => 'test',
            'version' => 2,
            'type' => 'text',
            'prompt' => 'v2',
        ]);

    $cache = new PromptCache();
    $manager = new PromptManager($api, $cache, 60);

    $v1 = $manager->get('test', version: 1);
    $v2 = $manager->get('test', version: 2);

    expect($v1->compile())->toBe('v1')
        ->and($v2->compile())->toBe('v2');
});
