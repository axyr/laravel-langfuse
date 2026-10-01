<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\Usage;

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
        inputCost: 0.0005,
        outputCost: 0.0015,
        totalCost: 0.002,
        details: ['cache_read' => 40],
        costDetails: ['cache_read' => 0.0001],
    );

    expect($usage->input)->toBe(100)
        ->and($usage->output)->toBe(200)
        ->and($usage->total)->toBe(300)
        ->and($usage->inputCost)->toBe(0.0005)
        ->and($usage->outputCost)->toBe(0.0015)
        ->and($usage->totalCost)->toBe(0.002)
        ->and($usage->details)->toBe(['cache_read' => 40])
        ->and($usage->costDetails)->toBe(['cache_read' => 0.0001]);
});

it('has no usage unit, which v4 removed', function () {
    expect(new ReflectionClass(Usage::class))->not->toBeNull();

    $parameters = array_map(
        fn(ReflectionParameter $p): string => $p->getName(),
        (new ReflectionMethod(Usage::class, '__construct'))->getParameters(),
    );

    expect($parameters)->not->toContain('unit');
});

it('builds usage details with the conventional keys first', function () {
    $usage = new Usage(input: 100, output: 200, total: 300, details: ['cache_read' => 40]);

    expect($usage->usageDetails())->toBe([
        'input' => 100,
        'output' => 200,
        'total' => 300,
        'cache_read' => 40,
    ]);
});

it('builds cost details with the conventional keys first', function () {
    $usage = new Usage(inputCost: 0.001, outputCost: 0.002, totalCost: 0.003, costDetails: ['reasoning' => 0.004]);

    expect($usage->costDetails())->toBe([
        'input' => 0.001,
        'output' => 0.002,
        'total' => 0.003,
        'reasoning' => 0.004,
    ]);
});

it('returns empty details when nothing is set', function () {
    $usage = new Usage();

    expect($usage->usageDetails())->toBe([])
        ->and($usage->costDetails())->toBe([]);
});

it('serializes to array excluding nulls', function () {
    $usage = new Usage(input: 100, output: 200, total: 300);

    $array = $usage->toArray();

    expect($array)->toBe(['input' => 100, 'output' => 200, 'total' => 300])
        ->and($array)->not->toHaveKey('inputCost');
});

it('serializes to empty array when all null', function () {
    expect((new Usage())->toArray())->toBe([]);
});

it('uses camelCase keys', function () {
    $usage = new Usage(inputCost: 0.001, outputCost: 0.002, totalCost: 0.003);

    expect($usage->toArray())->toHaveKeys(['inputCost', 'outputCost', 'totalCost']);
});
