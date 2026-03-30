<?php

declare(strict_types=1);

use Langfuse\Enums\EventType;

it('has 7 cases', function () {
    expect(EventType::cases())->toHaveCount(7);
});

it('has correct values', function (EventType $case, string $expected) {
    expect($case->value)->toBe($expected);
})->with([
    [EventType::TraceCreate, 'trace-create'],
    [EventType::SpanCreate, 'span-create'],
    [EventType::SpanUpdate, 'span-update'],
    [EventType::GenerationCreate, 'generation-create'],
    [EventType::GenerationUpdate, 'generation-update'],
    [EventType::EventCreate, 'event-create'],
    [EventType::ScoreCreate, 'score-create'],
]);

it('can be created from string value', function () {
    expect(EventType::from('trace-create'))->toBe(EventType::TraceCreate);
    expect(EventType::from('generation-create'))->toBe(EventType::GenerationCreate);
});
