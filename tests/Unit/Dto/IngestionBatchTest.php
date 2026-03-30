<?php

declare(strict_types=1);

use Langfuse\Dto\IngestionBatch;
use Langfuse\Dto\IngestionEvent;
use Langfuse\Dto\TraceBody;
use Langfuse\Enums\EventType;

it('can be constructed with events', function () {
    $event = new IngestionEvent(
        id: 'evt-1',
        type: EventType::TraceCreate,
        timestamp: '2024-01-01T00:00:00Z',
        body: new TraceBody(id: 'trace-1'),
    );

    $batch = new IngestionBatch(batch: [$event]);

    expect($batch->batch)->toHaveCount(1)
        ->and($batch->metadata)->toBe([]);
});

it('serializes to array with batch and metadata', function () {
    $event = new IngestionEvent(
        id: 'evt-1',
        type: EventType::TraceCreate,
        timestamp: '2024-01-01T00:00:00Z',
        body: new TraceBody(id: 'trace-1', name: 'test'),
    );

    $batch = new IngestionBatch(
        batch: [$event],
        metadata: ['sdk_version' => '1.0.0'],
    );

    $array = $batch->toArray();

    expect($array['batch'])->toHaveCount(1)
        ->and($array['batch'][0])->toBe([
            'id' => 'evt-1',
            'type' => 'trace-create',
            'timestamp' => '2024-01-01T00:00:00Z',
            'body' => ['id' => 'trace-1', 'name' => 'test'],
        ])
        ->and($array['metadata'])->toBeObject();
});

it('serializes empty metadata as empty object', function () {
    $batch = new IngestionBatch(batch: []);

    $array = $batch->toArray();

    expect($array['metadata'])->toBeObject()
        ->and($array['batch'])->toBe([]);
});
