<?php

declare(strict_types=1);

use Langfuse\Enums\ObservationLevel;

it('has 4 cases', function () {
    expect(ObservationLevel::cases())->toHaveCount(4);
});

it('has correct values', function (ObservationLevel $case, string $expected) {
    expect($case->value)->toBe($expected);
})->with([
    [ObservationLevel::DEBUG, 'DEBUG'],
    [ObservationLevel::DEFAULT, 'DEFAULT'],
    [ObservationLevel::WARNING, 'WARNING'],
    [ObservationLevel::ERROR, 'ERROR'],
]);
