<?php

declare(strict_types=1);

use Axyr\Langfuse\Enums\EventType;

it('only carries the score-create event, the one type v4 still ingests', function () {
    expect(EventType::cases())->toBe([EventType::ScoreCreate]);
});

it('has correct values', function () {
    expect(EventType::ScoreCreate->value)->toBe('score-create');
});

it('can be created from string value', function () {
    expect(EventType::from('score-create'))->toBe(EventType::ScoreCreate);
});

it('no longer resolves the observation event types', function () {
    expect(EventType::tryFrom('trace-create'))->toBeNull()
        ->and(EventType::tryFrom('generation-create'))->toBeNull()
        ->and(EventType::tryFrom('span-update'))->toBeNull();
});
