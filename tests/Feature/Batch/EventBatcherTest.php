<?php

declare(strict_types=1);

use Langfuse\Batch\EventBatcher;
use Langfuse\Batch\NullEventBatcher;
use Langfuse\Config\LangfuseConfig;
use Langfuse\Contracts\IngestionApiClientInterface;
use Langfuse\Dto\IngestionBatch;
use Langfuse\Dto\IngestionEvent;
use Langfuse\Dto\IngestionResponse;
use Langfuse\Dto\TraceBody;
use Langfuse\Enums\EventType;

function makeEvent(string $id = 'evt-1'): IngestionEvent
{
    return new IngestionEvent(
        id: $id,
        type: EventType::TraceCreate,
        timestamp: '2024-01-01T00:00:00Z',
        body: new TraceBody(id: 'trace-1'),
    );
}

it('enqueues events and tracks count', function () {
    $apiClient = Mockery::mock(IngestionApiClientInterface::class);
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', flushAt: 10);
    $batcher = new EventBatcher($apiClient, $config);

    $batcher->enqueue(makeEvent('evt-1'));
    $batcher->enqueue(makeEvent('evt-2'));

    expect($batcher->count())->toBe(2);
});

it('auto flushes when threshold is reached', function () {
    $apiClient = Mockery::mock(IngestionApiClientInterface::class);
    $apiClient->shouldReceive('send')
        ->once()
        ->with(Mockery::on(function (IngestionBatch $batch) {
            return count($batch->batch) === 3;
        }))
        ->andReturn(IngestionResponse::fromArray(['successes' => [], 'errors' => []]));

    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', flushAt: 3);
    $batcher = new EventBatcher($apiClient, $config);

    $batcher->enqueue(makeEvent('evt-1'));
    $batcher->enqueue(makeEvent('evt-2'));

    expect($batcher->count())->toBe(2);

    $batcher->enqueue(makeEvent('evt-3'));

    expect($batcher->count())->toBe(0);
});

it('flushes manually', function () {
    $apiClient = Mockery::mock(IngestionApiClientInterface::class);
    $apiClient->shouldReceive('send')
        ->once()
        ->andReturn(IngestionResponse::fromArray(['successes' => [], 'errors' => []]));

    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', flushAt: 100);
    $batcher = new EventBatcher($apiClient, $config);

    $batcher->enqueue(makeEvent());
    $batcher->flush();

    expect($batcher->count())->toBe(0);
});

it('does not send when queue is empty', function () {
    $apiClient = Mockery::mock(IngestionApiClientInterface::class);
    $apiClient->shouldNotReceive('send');

    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');
    $batcher = new EventBatcher($apiClient, $config);

    $batcher->flush();
});

it('resets queue after flush even on error', function () {
    $apiClient = Mockery::mock(IngestionApiClientInterface::class);
    $apiClient->shouldReceive('send')
        ->once()
        ->andThrow(new RuntimeException('Network error'));

    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');
    $batcher = new EventBatcher($apiClient, $config);

    $batcher->enqueue(makeEvent());
    $batcher->flush();

    expect($batcher->count())->toBe(0);
});

describe('NullEventBatcher', function () {
    it('does nothing on enqueue', function () {
        $batcher = new NullEventBatcher();
        $batcher->enqueue(makeEvent());

        expect($batcher->count())->toBe(0);
    });

    it('does nothing on flush', function () {
        $batcher = new NullEventBatcher();
        $batcher->flush();

        expect($batcher->count())->toBe(0);
    });
});
