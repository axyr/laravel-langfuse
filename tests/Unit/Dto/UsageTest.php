<?php

declare(strict_types=1);

use Langfuse\Dto\Usage;

it('can be constructed with no fields', function () {
    $usage = new Usage();

    expect($usage->input)->toBeNull()
        ->and($usage->output)->toBeNull()
        ->and($usage->total)->toBeNull();
});

it('can be constructed with all fields', function () {
    $usage = new Usage(
        input: 100,
        output: 200,
        total: 300,
        unit: 'TOKENS',
        inputCost: 10,
        outputCost: 20,
        totalCost: 30,
    );

    expect($usage->input)->toBe(100)
        ->and($usage->output)->toBe(200)
        ->and($usage->total)->toBe(300)
        ->and($usage->unit)->toBe('TOKENS')
        ->and($usage->inputCost)->toBe(10)
        ->and($usage->outputCost)->toBe(20)
        ->and($usage->totalCost)->toBe(30);
});

it('serializes to array excluding nulls', function () {
    $usage = new Usage(input: 100, output: 200, total: 300);

    $array = $usage->toArray();

    expect($array)->toBe(['input' => 100, 'output' => 200, 'total' => 300])
        ->and($array)->not->toHaveKey('unit')
        ->and($array)->not->toHaveKey('inputCost');
});

it('serializes to empty array when all null', function () {
    $usage = new Usage();

    expect($usage->toArray())->toBe([]);
});

it('uses camelCase keys', function () {
    $usage = new Usage(inputCost: 10, outputCost: 20, totalCost: 30);

    expect($usage->toArray())->toHaveKeys(['inputCost', 'outputCost', 'totalCost']);
});
