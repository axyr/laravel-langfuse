<?php

declare(strict_types=1);

use Langfuse\Enums\ScoreDataType;

it('has 3 cases', function () {
    expect(ScoreDataType::cases())->toHaveCount(3);
});

it('has correct values', function (ScoreDataType $case, string $expected) {
    expect($case->value)->toBe($expected);
})->with([
    [ScoreDataType::NUMERIC, 'NUMERIC'],
    [ScoreDataType::BOOLEAN, 'BOOLEAN'],
    [ScoreDataType::CATEGORICAL, 'CATEGORICAL'],
]);
