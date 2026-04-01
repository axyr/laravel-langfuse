<?php

declare(strict_types=1);

use Axyr\Langfuse\Contracts\SerializableInterface;
use Axyr\Langfuse\Dto\CreatePromptBody;

it('can be constructed with required fields', function () {
    $body = new CreatePromptBody(
        name: 'test-prompt',
        type: 'text',
        prompt: 'Hello {{name}}',
    );

    expect($body->name)->toBe('test-prompt')
        ->and($body->type)->toBe('text')
        ->and($body->prompt)->toBe('Hello {{name}}')
        ->and($body->config)->toBeNull()
        ->and($body->labels)->toBeNull();
});

it('can be constructed with all fields', function () {
    $body = new CreatePromptBody(
        name: 'chat-prompt',
        type: 'chat',
        prompt: [['role' => 'system', 'content' => 'You are helpful.']],
        config: ['temperature' => 0.7],
        labels: ['production', 'v2'],
    );

    expect($body->name)->toBe('chat-prompt')
        ->and($body->type)->toBe('chat')
        ->and($body->prompt)->toBe([['role' => 'system', 'content' => 'You are helpful.']])
        ->and($body->config)->toBe(['temperature' => 0.7])
        ->and($body->labels)->toBe(['production', 'v2']);
});

it('serializes to array excluding nulls', function () {
    $body = new CreatePromptBody(
        name: 'test',
        type: 'text',
        prompt: 'Hello',
    );

    $array = $body->toArray();

    expect($array)->toBe([
        'name' => 'test',
        'type' => 'text',
        'prompt' => 'Hello',
    ])
        ->and($array)->not->toHaveKey('config')
        ->and($array)->not->toHaveKey('labels');
});

it('serializes to array with all fields', function () {
    $body = new CreatePromptBody(
        name: 'test',
        type: 'chat',
        prompt: [['role' => 'user', 'content' => 'Hi']],
        config: ['model' => 'gpt-4'],
        labels: ['staging'],
    );

    expect($body->toArray())->toBe([
        'name' => 'test',
        'type' => 'chat',
        'prompt' => [['role' => 'user', 'content' => 'Hi']],
        'config' => ['model' => 'gpt-4'],
        'labels' => ['staging'],
    ]);
});

it('implements SerializableInterface', function () {
    $body = new CreatePromptBody(name: 'test', type: 'text', prompt: 'Hello');

    expect($body)->toBeInstanceOf(SerializableInterface::class);
});
