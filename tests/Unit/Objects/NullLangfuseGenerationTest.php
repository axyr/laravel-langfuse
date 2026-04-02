<?php

declare(strict_types=1);

use Axyr\Langfuse\Objects\NullLangfuseGeneration;

it('does not enqueue events on construction', function () {
    $generation = new NullLangfuseGeneration();

    expect($generation)->toBeInstanceOf(NullLangfuseGeneration::class);
});

it('returns empty string for getId', function () {
    $generation = new NullLangfuseGeneration();

    expect($generation->getId())->toBe('');
});

it('returns null for getTraceId', function () {
    $generation = new NullLangfuseGeneration();

    expect($generation->getTraceId())->toBeNull();
});

it('end is a no-op', function () {
    $generation = new NullLangfuseGeneration();

    $generation->end(output: 'test');

    expect($generation->getId())->toBe('');
});
