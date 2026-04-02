<?php

declare(strict_types=1);

use Axyr\Langfuse\Batch\QueuedEventBatcher;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Dto\IngestionEvent;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Enums\EventType;
use Axyr\Langfuse\Jobs\SendIngestionBatchJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

function makeQueuedEvent(string $id = 'evt-1'): IngestionEvent
{
    return new IngestionEvent(
        id: $id,
        type: EventType::TraceCreate,
        timestamp: '2024-01-01T00:00:00Z',
        body: new TraceBody(id: 'trace-1'),
    );
}

it('enqueues events and tracks count', function () {
    Queue::fake();

    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', flushAt: 10, queue: 'langfuse');
    $batcher = new QueuedEventBatcher($config);

    $batcher->enqueue(makeQueuedEvent('evt-1'));
    $batcher->enqueue(makeQueuedEvent('evt-2'));

    expect($batcher->count())->toBe(2);

    Queue::assertNothingPushed();
});

it('dispatches job on flush', function () {
    Queue::fake();

    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', flushAt: 100, queue: 'langfuse');
    $batcher = new QueuedEventBatcher($config);

    $batcher->enqueue(makeQueuedEvent());
    $batcher->flush();

    expect($batcher->count())->toBe(0);

    Queue::assertPushed(SendIngestionBatchJob::class, function (SendIngestionBatchJob $job) {
        return $job->queue === 'langfuse'
            && isset($job->payload['batch'])
            && count($job->payload['batch']) === 1;
    });
});

it('auto flushes when threshold is reached', function () {
    Queue::fake();

    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', flushAt: 3, queue: 'langfuse');
    $batcher = new QueuedEventBatcher($config);

    $batcher->enqueue(makeQueuedEvent('evt-1'));
    $batcher->enqueue(makeQueuedEvent('evt-2'));

    expect($batcher->count())->toBe(2);
    Queue::assertNothingPushed();

    $batcher->enqueue(makeQueuedEvent('evt-3'));

    expect($batcher->count())->toBe(0);

    Queue::assertPushed(SendIngestionBatchJob::class, function (SendIngestionBatchJob $job) {
        return count($job->payload['batch']) === 3;
    });
});

it('does not dispatch when queue is empty', function () {
    Queue::fake();

    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', queue: 'langfuse');
    $batcher = new QueuedEventBatcher($config);

    $batcher->flush();

    Queue::assertNothingPushed();
});

it('dispatches to default queue when queue config is null', function () {
    Queue::fake();

    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', queue: null);
    $batcher = new QueuedEventBatcher($config);

    $batcher->enqueue(makeQueuedEvent());
    $batcher->flush();

    Queue::assertPushed(SendIngestionBatchJob::class, function (SendIngestionBatchJob $job) {
        return $job->queue === null;
    });
});

it('resets queue after flush even on dispatch error', function () {
    $dispatcher = Mockery::mock(\Illuminate\Contracts\Bus\Dispatcher::class);
    $dispatcher->shouldReceive('dispatch')
        ->once()
        ->andThrow(new RuntimeException('Queue connection failed'));
    $this->app->instance(\Illuminate\Contracts\Bus\Dispatcher::class, $dispatcher);

    Log::shouldReceive('warning')
        ->once()
        ->with('Langfuse flush error', Mockery::on(function (array $context) {
            return $context['message'] === 'Queue connection failed';
        }));

    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', queue: 'langfuse');
    $batcher = new QueuedEventBatcher($config);

    $batcher->enqueue(makeQueuedEvent());
    $batcher->flush();

    expect($batcher->count())->toBe(0);
});

it('includes metadata in the dispatched payload', function () {
    Queue::fake();

    $config = new LangfuseConfig(publicKey: 'pk-test', secretKey: 'sk', queue: 'langfuse');
    $batcher = new QueuedEventBatcher($config);

    $batcher->enqueue(makeQueuedEvent());
    $batcher->flush();

    Queue::assertPushed(SendIngestionBatchJob::class, function (SendIngestionBatchJob $job) {
        $metadata = (array) $job->payload['metadata'];

        return $metadata['batch_size'] === 1
            && $metadata['sdk_name'] === 'langfuse-php'
            && $metadata['public_key'] === 'pk-test';
    });
});
