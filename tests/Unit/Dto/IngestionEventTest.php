<?php

declare(strict_types=1);

use Axyr\Langfuse\Dto\IngestionEvent;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Enums\EventType;

it('can be constructed', function () {
    $body = new ScoreBody(name: 'accuracy', id: 'score-1', value: 1.0);
    $event = new IngestionEvent(
        id: 'evt-1',
        type: EventType::ScoreCreate,
        timestamp: '2024-01-01T00:00:00Z',
        body: $body,
    );

    expect($event->id)->toBe('evt-1')
        ->and($event->type)->toBe(EventType::ScoreCreate)
        ->and($event->timestamp)->toBe('2024-01-01T00:00:00Z')
        ->and($event->body)->toBe($body);
});

it('serializes to array with type as string', function () {
    $body = new ScoreBody(name: 'accuracy', id: 'score-1', value: 1.0);
    $event = new IngestionEvent(
        id: 'evt-1',
        type: EventType::ScoreCreate,
        timestamp: '2024-01-01T00:00:00Z',
        body: $body,
    );

    $array = $event->toArray();

    expect($array)->toBe([
        'id' => 'evt-1',
        'type' => 'score-create',
        'timestamp' => '2024-01-01T00:00:00Z',
        'body' => ['id' => 'score-1', 'name' => 'accuracy', 'value' => 1.0],
    ]);
});

it('serializes body via its toArray method', function () {
    $body = new ScoreBody(name: 'accuracy', id: 'score-1');
    $event = new IngestionEvent(
        id: 'evt-1',
        type: EventType::ScoreCreate,
        timestamp: '2024-01-01T00:00:00Z',
        body: $body,
    );

    expect($event->toArray()['body'])->toBe(['id' => 'score-1', 'name' => 'accuracy']);
});
