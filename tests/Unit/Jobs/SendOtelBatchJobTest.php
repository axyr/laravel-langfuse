<?php

declare(strict_types=1);

use Axyr\Langfuse\Contracts\OtelTraceApiClientInterface;
use Axyr\Langfuse\Dto\OtelExportResult;
use Axyr\Langfuse\Exceptions\LangfuseExportRetryException;
use Axyr\Langfuse\Jobs\SendOtelBatchJob;
use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Support\Facades\Log;

function otelPayload(): array
{
    return ['resourceSpans' => [['scopeSpans' => [['spans' => [['name' => 'work']]]]]]];
}

it('exports the payload through the otlp client', function () {
    $apiClient = Mockery::mock(OtelTraceApiClientInterface::class);
    $apiClient->shouldReceive('exportRaw')
        ->once()
        ->with(otelPayload())
        ->andReturn(OtelExportResult::success());

    (new SendOtelBatchJob(otelPayload(), 1))->handle($apiClient);
});

it('does not retry a partial success, which the otlp spec forbids', function () {
    $apiClient = Mockery::mock(OtelTraceApiClientInterface::class);
    $apiClient->shouldReceive('exportRaw')->once()->andReturn(OtelExportResult::partial(1, 'one span rejected'));

    (new SendOtelBatchJob(otelPayload(), 1))->handle($apiClient);

    expect(true)->toBeTrue();
});

it('drops a non-retryable batch and logs it', function () {
    Log::shouldReceive('warning')
        ->once()
        ->with('Langfuse OTLP batch dropped', Mockery::on(function (array $context) {
            return $context['status'] === 400 && $context['message'] === 'bad payload';
        }));

    $apiClient = Mockery::mock(OtelTraceApiClientInterface::class);
    $apiClient->shouldReceive('exportRaw')
        ->once()
        ->andReturn(OtelExportResult::failed(status: 400, retryable: false, errorMessage: 'bad payload'));

    (new SendOtelBatchJob(otelPayload(), 1))->handle($apiClient);
});

it('throws so the worker retries a retryable status', function () {
    $apiClient = Mockery::mock(OtelTraceApiClientInterface::class);
    $apiClient->shouldReceive('exportRaw')
        ->once()
        ->andReturn(OtelExportResult::failed(status: 503, retryable: true, errorMessage: 'unavailable'));

    (new SendOtelBatchJob(otelPayload(), 1))->handle($apiClient);
})->throws(LangfuseExportRetryException::class);

it('throws so the worker retries a transport error', function () {
    $apiClient = Mockery::mock(OtelTraceApiClientInterface::class);
    $apiClient->shouldReceive('exportRaw')->once()->andReturn(OtelExportResult::transportError('connection reset'));

    (new SendOtelBatchJob(otelPayload(), 1))->handle($apiClient);
})->throws(LangfuseExportRetryException::class);

it('releases the job for the given Retry-After instead of throwing', function () {
    $apiClient = Mockery::mock(OtelTraceApiClientInterface::class);
    $apiClient->shouldReceive('exportRaw')
        ->once()
        ->andReturn(OtelExportResult::failed(status: 429, retryable: true, retryAfterSeconds: 30));

    $queueJob = Mockery::mock(QueueJobContract::class);
    $queueJob->shouldReceive('release')->once()->with(30);
    $queueJob->shouldReceive('isReleased')->andReturn(true);

    $job = new SendOtelBatchJob(otelPayload(), 1);
    $job->setJob($queueJob);

    $job->handle($apiClient);
});

it('retries with an increasing backoff', function () {
    $job = new SendOtelBatchJob(otelPayload(), 1);

    expect($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([10, 60, 300]);
});

it('logs the dropped span count when it fails permanently', function () {
    Log::shouldReceive('warning')
        ->once()
        ->with('Langfuse OTLP batch failed permanently', Mockery::on(function (array $context) {
            return $context['spans'] === 7 && $context['message'] === 'gone';
        }));

    (new SendOtelBatchJob(otelPayload(), 7))->failed(new RuntimeException('gone'));

    expect(true)->toBeTrue();
});

it('sets the queue via onQueue', function () {
    expect((new SendOtelBatchJob(otelPayload()))->queue)->toBeNull()
        ->and((new SendOtelBatchJob(otelPayload()))->onQueue('langfuse')->queue)->toBe('langfuse');
});
