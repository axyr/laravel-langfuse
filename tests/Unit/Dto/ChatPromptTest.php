<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\ChatPrompt;

it('compiles chat messages with variables', function () {
    $prompt = new ChatPrompt(
        name: 'test',
        version: 1,
        messages: [
            ['role' => 'system', 'content' => 'You are a {{role}}.'],
            ['role' => 'user', 'content' => 'Tell me about {{topic}}.'],
        ],
    );

    $result = $prompt->compile(['role' => 'historian', 'topic' => 'Rome']);

    expect($result)->toBe([
        ['role' => 'system', 'content' => 'You are a historian.'],
        ['role' => 'user', 'content' => 'Tell me about Rome.'],
    ]);
});

it('compiles without variables', function () {
    $prompt = new ChatPrompt(
        name: 'test',
        version: 1,
        messages: [
            ['role' => 'user', 'content' => 'Hello'],
        ],
    );

    expect($prompt->compile())->toBe([
        ['role' => 'user', 'content' => 'Hello'],
    ]);
});

it('leaves missing variables as placeholders', function () {
    $prompt = new ChatPrompt(
        name: 'test',
        version: 1,
        messages: [
            ['role' => 'user', 'content' => '{{greeting}} {{name}}'],
        ],
    );

    $result = $prompt->compile(['greeting' => 'Hi']);

    expect($result)->toBe([
        ['role' => 'user', 'content' => 'Hi {{name}}'],
    ]);
});

it('returns array from compile', function () {
    $prompt = new ChatPrompt(
        name: 'test',
        version: 1,
        messages: [['role' => 'user', 'content' => 'test']],
    );

    expect($prompt->compile())->toBeArray();
});

it('returns name', function () {
    $prompt = new ChatPrompt(name: 'chat-prompt', version: 1, messages: []);

    expect($prompt->getName())->toBe('chat-prompt');
});

it('returns version', function () {
    $prompt = new ChatPrompt(name: 'test', version: 5, messages: []);

    expect($prompt->getVersion())->toBe(5);
});

it('returns config', function () {
    $prompt = new ChatPrompt(
        name: 'test',
        version: 1,
        messages: [],
        config: ['model' => 'claude-3'],
    );

    expect($prompt->getConfig())->toBe(['model' => 'claude-3']);
});

it('returns labels', function () {
    $prompt = new ChatPrompt(
        name: 'test',
        version: 1,
        messages: [],
        labels: ['staging'],
    );

    expect($prompt->getLabels())->toBe(['staging']);
});

it('returns fallback status', function () {
    $regular = new ChatPrompt(name: 'test', version: 1, messages: []);
    $fallback = new ChatPrompt(name: 'test', version: 0, messages: [], fallback: true);

    expect($regular->isFallback())->toBeFalse()
        ->and($fallback->isFallback())->toBeTrue();
});

it('returns link metadata', function () {
    $prompt = new ChatPrompt(name: 'chat-critic', version: 2, messages: []);

    expect($prompt->toLinkMetadata())->toBe([
        'promptName' => 'chat-critic',
        'promptVersion' => 2,
    ]);
});

it('handles empty messages array', function () {
    $prompt = new ChatPrompt(name: 'test', version: 1, messages: []);

    expect($prompt->compile(['key' => 'value']))->toBe([]);
});
