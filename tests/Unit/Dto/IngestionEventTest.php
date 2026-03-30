<?php

declare(strict_types=1);

use Langfuse\Dto\IngestionEvent;
use Langfuse\Dto\TraceBody;
use Langfuse\Enums\EventType;

it('can be constructed', function () {
    $body = new TraceBody(id: 'trace-1', name: 'test');
    $event = new IngestionEvent(
        id: 'evt-1',
        type: EventType::TraceCreate,
        timestamp: '2024-01-01T00:00:00Z',
        body: $body,
    );

    expect($event->id)->toBe('evt-1')
        ->and($event->type)->toBe(EventType::TraceCreate)
        ->and($event->timestamp)->toBe('2024-01-01T00:00:00Z')
        ->and($event->body)->toBe($body);
});

it('serializes to array with type as string', function () {
    $body = new TraceBody(id: 'trace-1', name: 'test');
    $event = new IngestionEvent(
        id: 'evt-1',
        type: EventType::TraceCreate,
        timestamp: '2024-01-01T00:00:00Z',
        body: $body,
    );

    $array = $event->toArray();

    expect($array)->toBe([
        'id' => 'evt-1',
        'type' => 'trace-create',
        'timestamp' => '2024-01-01T00:00:00Z',
        'body' => ['id' => 'trace-1', 'name' => 'test'],
    ]);
});

it('serializes body via its toArray method', function () {
    $body = new TraceBody(id: 'trace-1');
    $event = new IngestionEvent(
        id: 'evt-1',
        type: EventType::TraceCreate,
        timestamp: '2024-01-01T00:00:00Z',
        body: $body,
    );

    expect($event->toArray()['body'])->toBe(['id' => 'trace-1']);
});
