<?php

declare(strict_types=1);

use Axyr\Langfuse\Cache\PromptCache;
use Axyr\Langfuse\Dto\TextPrompt;

it('stores and retrieves a prompt', function () {
    $cache = new PromptCache();
    $prompt = new TextPrompt(name: 'test', version: 1, prompt: 'hello');

    $cache->put('key', $prompt, 60);

    expect($cache->get('key'))->toBe($prompt);
});

it('returns null for missing key', function () {
    $cache = new PromptCache();

    expect($cache->get('missing'))->toBeNull();
});

it('reports has correctly', function () {
    $cache = new PromptCache();
    $prompt = new TextPrompt(name: 'test', version: 1, prompt: 'hello');

    expect($cache->has('key'))->toBeFalse();

    $cache->put('key', $prompt, 60);

    expect($cache->has('key'))->toBeTrue();
});

it('reports expired correctly for missing key', function () {
    $cache = new PromptCache();

    expect($cache->isExpired('missing'))->toBeTrue();
});

it('reports not expired for fresh entry', function () {
    $cache = new PromptCache();
    $prompt = new TextPrompt(name: 'test', version: 1, prompt: 'hello');

    $cache->put('key', $prompt, 60);

    expect($cache->isExpired('key'))->toBeFalse();
});

it('reports expired after ttl passes', function () {
    $cache = new PromptCache();
    $prompt = new TextPrompt(name: 'test', version: 1, prompt: 'hello');

    // Use a TTL of 0 to immediately expire
    $cache->put('key', $prompt, 0);

    // Allow microtime to advance
    usleep(1000);

    expect($cache->isExpired('key'))->toBeTrue();
});

it('still returns prompt after expiration for stale-while-revalidate', function () {
    $cache = new PromptCache();
    $prompt = new TextPrompt(name: 'test', version: 1, prompt: 'hello');

    $cache->put('key', $prompt, 0);
    usleep(1000);

    // Expired but still available
    expect($cache->isExpired('key'))->toBeTrue()
        ->and($cache->has('key'))->toBeTrue()
        ->and($cache->get('key'))->toBe($prompt);
});

it('overwrites existing entry', function () {
    $cache = new PromptCache();
    $prompt1 = new TextPrompt(name: 'test', version: 1, prompt: 'hello');
    $prompt2 = new TextPrompt(name: 'test', version: 2, prompt: 'world');

    $cache->put('key', $prompt1, 60);
    $cache->put('key', $prompt2, 60);

    expect($cache->get('key'))->toBe($prompt2);
});
