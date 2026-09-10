<?php

declare(strict_types=1);

use Axyr\Langfuse\Enums\ScoreDataType;

it('has 5 cases', function () {
    expect(ScoreDataType::cases())->toHaveCount(5);
});

it('has correct values', function (ScoreDataType $case, string $expected) {
    expect($case->value)->toBe($expected);
})->with([
    [ScoreDataType::NUMERIC, 'NUMERIC'],
    [ScoreDataType::BOOLEAN, 'BOOLEAN'],
    [ScoreDataType::CATEGORICAL, 'CATEGORICAL'],
    [ScoreDataType::TEXT, 'TEXT'],
    [ScoreDataType::CORRECTION, 'CORRECTION'],
]);
