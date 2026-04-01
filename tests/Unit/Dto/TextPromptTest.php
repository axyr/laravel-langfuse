<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\TextPrompt;

it('compiles template with variables', function () {
    $prompt = new TextPrompt(
        name: 'test',
        version: 1,
        prompt: 'Review the movie {{movie}} by {{director}}.',
    );

    $result = $prompt->compile(['movie' => 'Dune 2', 'director' => 'Villeneuve']);

    expect($result)->toBe('Review the movie Dune 2 by Villeneuve.');
});

it('compiles template without variables', function () {
    $prompt = new TextPrompt(
        name: 'test',
        version: 1,
        prompt: 'Hello, world!',
    );

    expect($prompt->compile())->toBe('Hello, world!');
});

it('leaves missing variables as placeholders', function () {
    $prompt = new TextPrompt(
        name: 'test',
        version: 1,
        prompt: 'Hello {{name}}, welcome to {{place}}!',
    );

    $result = $prompt->compile(['name' => 'Alice']);

    expect($result)->toBe('Hello Alice, welcome to {{place}}!');
});

it('handles variables with spaces in template tags', function () {
    $prompt = new TextPrompt(
        name: 'test',
        version: 1,
        prompt: 'Hello {{ name }}!',
    );

    expect($prompt->compile(['name' => 'Bob']))->toBe('Hello Bob!');
});

it('returns name', function () {
    $prompt = new TextPrompt(name: 'movie-critic', version: 1, prompt: '');

    expect($prompt->getName())->toBe('movie-critic');
});

it('returns version', function () {
    $prompt = new TextPrompt(name: 'test', version: 3, prompt: '');

    expect($prompt->getVersion())->toBe(3);
});

it('returns config', function () {
    $prompt = new TextPrompt(
        name: 'test',
        version: 1,
        prompt: '',
        config: ['model' => 'gpt-4', 'temperature' => 0.7],
    );

    expect($prompt->getConfig())->toBe(['model' => 'gpt-4', 'temperature' => 0.7]);
});

it('returns labels', function () {
    $prompt = new TextPrompt(
        name: 'test',
        version: 1,
        prompt: '',
        labels: ['production', 'latest'],
    );

    expect($prompt->getLabels())->toBe(['production', 'latest']);
});

it('returns fallback status', function () {
    $regular = new TextPrompt(name: 'test', version: 1, prompt: '');
    $fallback = new TextPrompt(name: 'test', version: 0, prompt: '', fallback: true);

    expect($regular->isFallback())->toBeFalse()
        ->and($fallback->isFallback())->toBeTrue();
});

it('returns link metadata', function () {
    $prompt = new TextPrompt(name: 'movie-critic', version: 3, prompt: '');

    expect($prompt->toLinkMetadata())->toBe([
        'promptName' => 'movie-critic',
        'promptVersion' => 3,
    ]);
});

it('compiles empty string when no prompt content', function () {
    $prompt = new TextPrompt(name: 'test', version: 1, prompt: '');

    expect($prompt->compile(['key' => 'value']))->toBe('');
});

it('handles multiple occurrences of the same variable', function () {
    $prompt = new TextPrompt(
        name: 'test',
        version: 1,
        prompt: '{{name}} said hello to {{name}}',
    );

    expect($prompt->compile(['name' => 'Alice']))->toBe('Alice said hello to Alice');
});
