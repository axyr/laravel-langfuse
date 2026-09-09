<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\PromptFactory;
use Axyr\Langfuse\Dto\TextPrompt;
use Axyr\Langfuse\Prompt\CurrentPromptRegistry;

it('holds the registered prompt until consumed', function () {
    $registry = new CurrentPromptRegistry();
    $prompt = new TextPrompt(name: 'movie-critic', version: 3, prompt: 'text');

    $registry->set($prompt);

    expect($registry->current())->toBe($prompt)
        ->and($registry->consume())->toBe($prompt)
        ->and($registry->current())->toBeNull()
        ->and($registry->consume())->toBeNull();
});

it('ignores fallback prompts', function () {
    $registry = new CurrentPromptRegistry();

    $registry->set(PromptFactory::fallbackText('movie-critic', 'fallback text'));

    expect($registry->current())->toBeNull();
});

it('replaces a previously registered prompt', function () {
    $registry = new CurrentPromptRegistry();

    $registry->set(new TextPrompt(name: 'first', version: 1, prompt: 'a'));
    $registry->set(new TextPrompt(name: 'second', version: 2, prompt: 'b'));

    expect($registry->consume()?->getName())->toBe('second');
});
