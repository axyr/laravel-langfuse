<?php

declare(strict_types=1);

use Axyr\Langfuse\Batch\QueuedEventBatcher;
use Axyr\Langfuse\Batch\ScoreBatchFactory;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Jobs\SendIngestionBatchJob;
use Axyr\Langfuse\Jobs\SendOtelBatchJob;
use Axyr\Langfuse\Otlp\OtlpRequestFactory;
use Axyr\Langfuse\Tracing\CompletedObservation;
use Axyr\Langfuse\Tracing\TraceContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

function queuedObservation(string $name = 'work', mixed $input = null): CompletedObservation
{
    $context = new TraceContext(new TraceBody(id: 'trace-1'));

    $body = (new SpanBody(name: $name, input: $input, startTime: '2024-01-01T00:00:00Z', endTime: '2024-01-01T00:00:01Z'))
        ->withContext($context->traceId(), $context->rootObservationId());

    return CompletedObservation::span($body, $context);
}

function queuedBatcher(LangfuseConfig $config): QueuedEventBatcher
{
    return new QueuedEventBatcher(
        config: $config,
        requestFactory: new OtlpRequestFactory($config),
        scoreBatchFactory: new ScoreBatchFactory($config),
    );
}

it('queues items and tracks count without dispatching', function () {
    Queue::fake();

    $batcher = queuedBatcher(new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', flushAt: 10, queue: 'langfuse'));

    $batcher->enqueue(queuedObservation('one'));
    $batcher->enqueue(queuedObservation('two'));

    expect($batcher->count())->toBe(2);

    Queue::assertNothingPushed();
});

it('dispatches an otlp job with the serialised payload on flush', function () {
    Queue::fake();

    $batcher = queuedBatcher(new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', flushAt: 100, queue: 'langfuse'));

    $batcher->enqueue(queuedObservation());
    $batcher->flush();

    expect($batcher->count())->toBe(0);

    Queue::assertPushed(SendOtelBatchJob::class, function (SendOtelBatchJob $job) {
        $spans = $job->payload['resourceSpans'][0]['scopeSpans'][0]['spans'];

        return $job->queue === 'langfuse'
            && $job->spanCount === 1
            && count($spans) === 1
            && $spans[0]['name'] === 'work';
    });

    Queue::assertNotPushed(SendIngestionBatchJob::class);
});

it('dispatches the score job only when scores are queued', function () {
    Queue::fake();

    $batcher = queuedBatcher(new LangfuseConfig(publicKey: 'pk-test', secretKey: 'sk', flushAt: 100, queue: 'langfuse'));

    $batcher->enqueueScore(new ScoreBody(name: 'accuracy', value: 1.0));
    $batcher->flush();

    Queue::assertNotPushed(SendOtelBatchJob::class);

    Queue::assertPushed(SendIngestionBatchJob::class, function (SendIngestionBatchJob $job) {
        $metadata = (array) $job->payload['metadata'];

        return $job->queue === 'langfuse'
            && count($job->payload['batch']) === 1
            && $job->payload['batch'][0]['type'] === 'score-create'
            && $metadata['batch_size'] === 1
            && $metadata['sdk_name'] === 'langfuse-php'
            && $metadata['public_key'] === 'pk-test';
    });
});

it('auto flushes when the threshold is reached', function () {
    Queue::fake();

    $batcher = queuedBatcher(new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', flushAt: 3, queue: 'langfuse'));

    $batcher->enqueue(queuedObservation('one'));
    $batcher->enqueue(queuedObservation('two'));

    expect($batcher->count())->toBe(2);
    Queue::assertNothingPushed();

    $batcher->enqueue(queuedObservation('three'));

    expect($batcher->count())->toBe(0);

    Queue::assertPushed(SendOtelBatchJob::class, function (SendOtelBatchJob $job) {
        return $job->spanCount === 3;
    });
});

it('splits above the request limit before dispatching', function () {
    Queue::fake();

    $big = str_repeat('x', (int) (OtlpRequestFactory::MAX_REQUEST_BYTES * 0.6));
    $batcher = queuedBatcher(new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', flushAt: 100, queue: 'langfuse'));

    $batcher->enqueue(queuedObservation('one', $big));
    $batcher->enqueue(queuedObservation('two', $big));
    $batcher->flush();

    Queue::assertPushed(SendOtelBatchJob::class, 2);
});

it('does not dispatch when the queue is empty', function () {
    Queue::fake();

    queuedBatcher(new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', queue: 'langfuse'))->flush();

    Queue::assertNothingPushed();
});

it('dispatches to the default queue when the queue config is null', function () {
    Queue::fake();

    $batcher = queuedBatcher(new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', queue: null));

    $batcher->enqueue(queuedObservation());
    $batcher->flush();

    Queue::assertPushed(SendOtelBatchJob::class, function (SendOtelBatchJob $job) {
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

    $batcher = queuedBatcher(new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', queue: 'langfuse'));

    $batcher->enqueue(queuedObservation());
    $batcher->flush();

    expect($batcher->count())->toBe(0);
});
