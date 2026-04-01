<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\ChatPrompt;
use Axyr\Langfuse\Dto\PromptFactory;
use Axyr\Langfuse\Dto\TextPrompt;

it('creates text prompt from api response', function () {
    $prompt = PromptFactory::fromApiResponse([
        'name' => 'movie-critic',
        'version' => 3,
        'type' => 'text',
        'prompt' => 'Review {{movie}}',
        'config' => ['model' => 'gpt-4'],
        'labels' => ['production'],
    ]);

    expect($prompt)->toBeInstanceOf(TextPrompt::class)
        ->and($prompt->getName())->toBe('movie-critic')
        ->and($prompt->getVersion())->toBe(3)
        ->and($prompt->compile(['movie' => 'Dune']))->toBe('Review Dune')
        ->and($prompt->getConfig())->toBe(['model' => 'gpt-4'])
        ->and($prompt->getLabels())->toBe(['production'])
        ->and($prompt->isFallback())->toBeFalse();
});

it('creates chat prompt from api response', function () {
    $prompt = PromptFactory::fromApiResponse([
        'name' => 'chat-bot',
        'version' => 1,
        'type' => 'chat',
        'prompt' => [
            ['role' => 'system', 'content' => 'You are {{role}}.'],
            ['role' => 'user', 'content' => '{{question}}'],
        ],
        'config' => ['temperature' => 0.5],
        'labels' => ['latest'],
    ]);

    expect($prompt)->toBeInstanceOf(ChatPrompt::class)
        ->and($prompt->getName())->toBe('chat-bot')
        ->and($prompt->getVersion())->toBe(1)
        ->and($prompt->compile(['role' => 'a helper', 'question' => 'Hi']))
        ->toBe([
            ['role' => 'system', 'content' => 'You are a helper.'],
            ['role' => 'user', 'content' => 'Hi'],
        ]);
});

it('defaults to text type when type is missing', function () {
    $prompt = PromptFactory::fromApiResponse([
        'name' => 'default',
        'version' => 1,
        'prompt' => 'Hello',
    ]);

    expect($prompt)->toBeInstanceOf(TextPrompt::class);
});

it('handles missing optional fields', function () {
    $prompt = PromptFactory::fromApiResponse([
        'name' => 'minimal',
        'version' => 1,
        'type' => 'text',
        'prompt' => 'test',
    ]);

    expect($prompt->getConfig())->toBe([])
        ->and($prompt->getLabels())->toBe([]);
});

it('creates text fallback', function () {
    $prompt = PromptFactory::fallbackText('test', 'fallback {{var}}');

    expect($prompt)->toBeInstanceOf(TextPrompt::class)
        ->and($prompt->isFallback())->toBeTrue()
        ->and($prompt->getName())->toBe('test')
        ->and($prompt->getVersion())->toBe(0)
        ->and($prompt->compile(['var' => 'value']))->toBe('fallback value');
});

it('creates chat fallback', function () {
    $messages = [['role' => 'user', 'content' => 'Hi {{name}}']];
    $prompt = PromptFactory::fallbackChat('test', $messages);

    expect($prompt)->toBeInstanceOf(ChatPrompt::class)
        ->and($prompt->isFallback())->toBeTrue()
        ->and($prompt->getName())->toBe('test')
        ->and($prompt->getVersion())->toBe(0);
});
