<?php

declare(strict_types=1);

use Axyr\Langfuse\Batch\EventBatcher;
use Axyr\Langfuse\Batch\NullEventBatcher;
use Axyr\Langfuse\Batch\ScoreBatchFactory;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\OtelTraceApiClientInterface;
use Axyr\Langfuse\Contracts\ScoreApiClientInterface;
use Axyr\Langfuse\Dto\IngestionBatch;
use Axyr\Langfuse\Dto\OtelExportResult;
use Axyr\Langfuse\Dto\Otlp\OtlpExportRequest;
use Axyr\Langfuse\Dto\ScoreBody;
use Axyr\Langfuse\Dto\SpanBody;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Otlp\OtlpRequestFactory;
use Axyr\Langfuse\Tracing\CompletedObservation;
use Axyr\Langfuse\Tracing\TraceContext;

function batcherConfig(int $flushAt = 10): LangfuseConfig
{
    return new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', flushAt: $flushAt);
}

function makeObservation(string $name = 'work', mixed $input = null): CompletedObservation
{
    $context = new TraceContext(new TraceBody(id: 'trace-1'));

    $body = (new SpanBody(name: $name, input: $input, startTime: '2024-01-01T00:00:00Z', endTime: '2024-01-01T00:00:01Z'))
        ->withContext($context->traceId(), $context->rootObservationId());

    return CompletedObservation::span($body, $context);
}

function makeBatcher(LangfuseConfig $config, $otelClient, $scoreClient): EventBatcher
{
    return new EventBatcher(
        otelClient: $otelClient,
        scoreApiClient: $scoreClient,
        config: $config,
        requestFactory: new OtlpRequestFactory($config),
        scoreBatchFactory: new ScoreBatchFactory($config),
    );
}

it('queues observations and scores together and tracks the count', function () {
    $otel = Mockery::mock(OtelTraceApiClientInterface::class);
    $scores = Mockery::mock(ScoreApiClientInterface::class);

    $batcher = makeBatcher(batcherConfig(), $otel, $scores);

    $batcher->enqueue(makeObservation('one'));
    $batcher->enqueueScore(new ScoreBody(name: 'accuracy', value: 1.0));

    expect($batcher->count())->toBe(2);
});

it('auto flushes when the threshold is reached across both kinds', function () {
    $otel = Mockery::mock(OtelTraceApiClientInterface::class);
    $otel->shouldReceive('export')->once()->andReturn(OtelExportResult::success());

    $scores = Mockery::mock(ScoreApiClientInterface::class);
    $scores->shouldReceive('ingest')->once()->andReturnNull();

    $batcher = makeBatcher(batcherConfig(3), $otel, $scores);

    $batcher->enqueue(makeObservation('one'));
    $batcher->enqueue(makeObservation('two'));

    expect($batcher->count())->toBe(2);

    $batcher->enqueueScore(new ScoreBody(name: 'accuracy', value: 1.0));

    expect($batcher->count())->toBe(0);
});

it('sends the observations to the otlp endpoint', function () {
    $otel = Mockery::mock(OtelTraceApiClientInterface::class);
    $otel->shouldReceive('export')
        ->once()
        ->with(Mockery::on(fn(OtlpExportRequest $request): bool => $request->spanCount() === 2))
        ->andReturn(OtelExportResult::success());

    $scores = Mockery::mock(ScoreApiClientInterface::class);
    $scores->shouldNotReceive('ingest');

    $batcher = makeBatcher(batcherConfig(100), $otel, $scores);

    $batcher->enqueue(makeObservation('one'));
    $batcher->enqueue(makeObservation('two'));
    $batcher->flush();

    expect($batcher->count())->toBe(0);
});

it('sends the scores as a score-create batch', function () {
    $otel = Mockery::mock(OtelTraceApiClientInterface::class);
    $otel->shouldNotReceive('export');

    $scores = Mockery::mock(ScoreApiClientInterface::class);
    $scores->shouldReceive('ingest')
        ->once()
        ->with(Mockery::on(function (IngestionBatch $batch): bool {
            return count($batch->batch) === 1
                && $batch->batch[0]->type->value === 'score-create'
                && $batch->metadata['sdk_name'] === 'langfuse-php';
        }))
        ->andReturnNull();

    $batcher = makeBatcher(batcherConfig(100), $otel, $scores);

    $batcher->enqueueScore(new ScoreBody(name: 'accuracy', value: 1.0));
    $batcher->flush();
});

it('stamps the score envelope with the given timestamp', function () {
    $otel = Mockery::mock(OtelTraceApiClientInterface::class);

    $scores = Mockery::mock(ScoreApiClientInterface::class);
    $scores->shouldReceive('ingest')
        ->once()
        ->with(Mockery::on(fn(IngestionBatch $batch): bool => $batch->batch[0]->timestamp === '2024-01-01T00:00:00Z'))
        ->andReturnNull();

    $batcher = makeBatcher(batcherConfig(100), $otel, $scores);

    $batcher->enqueueScore(new ScoreBody(name: 'accuracy', value: 1.0), '2024-01-01T00:00:00Z');
    $batcher->flush();
});

it('splits an oversized batch into several otlp requests', function () {
    $big = str_repeat('x', (int) (OtlpRequestFactory::MAX_REQUEST_BYTES * 0.6));

    $otel = Mockery::mock(OtelTraceApiClientInterface::class);
    $otel->shouldReceive('export')->twice()->andReturn(OtelExportResult::success());

    $scores = Mockery::mock(ScoreApiClientInterface::class);

    $batcher = makeBatcher(batcherConfig(100), $otel, $scores);

    $batcher->enqueue(makeObservation('one', $big));
    $batcher->enqueue(makeObservation('two', $big));
    $batcher->flush();
});

it('does not send when the queue is empty', function () {
    $otel = Mockery::mock(OtelTraceApiClientInterface::class);
    $otel->shouldNotReceive('export');

    $scores = Mockery::mock(ScoreApiClientInterface::class);
    $scores->shouldNotReceive('ingest');

    makeBatcher(batcherConfig(), $otel, $scores)->flush();
});

it('resets the queue after flush even on error', function () {
    $otel = Mockery::mock(OtelTraceApiClientInterface::class);
    $otel->shouldReceive('export')->once()->andThrow(new RuntimeException('Network error'));

    $scores = Mockery::mock(ScoreApiClientInterface::class);

    $batcher = makeBatcher(batcherConfig(), $otel, $scores);

    $batcher->enqueue(makeObservation());
    $batcher->flush();

    expect($batcher->count())->toBe(0);
});

describe('NullEventBatcher', function () {
    it('does nothing on enqueue', function () {
        $batcher = new NullEventBatcher();
        $batcher->enqueue(makeObservation());
        $batcher->enqueueScore(new ScoreBody(name: 'accuracy'));

        expect($batcher->count())->toBe(0);
    });

    it('does nothing on flush', function () {
        $batcher = new NullEventBatcher();
        $batcher->flush();

        expect($batcher->count())->toBe(0);
    });
});
