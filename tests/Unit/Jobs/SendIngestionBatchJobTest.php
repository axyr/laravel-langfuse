<?php

declare(strict_types=1);

use Axyr\Langfuse\Contracts\IngestionApiClientInterface;
use Axyr\Langfuse\Dto\IngestionResponse;
use Axyr\Langfuse\Jobs\SendIngestionBatchJob;
use Illuminate\Support\Facades\Log;

it('calls sendRaw on the api client with the score payload', function () {
    $payload = [
        'batch' => [[
            'id' => 'evt-1',
            'type' => 'score-create',
            'timestamp' => '2024-01-01T00:00:00Z',
            'body' => ['id' => 'score-1', 'name' => 'accuracy', 'value' => 1.0],
        ]],
        'metadata' => (object) ['batch_size' => 1],
    ];

    $apiClient = Mockery::mock(IngestionApiClientInterface::class);
    $apiClient->shouldReceive('sendRaw')
        ->once()
        ->with($payload)
        ->andReturn(IngestionResponse::fromArray(['successes' => [['id' => 'evt-1', 'status' => 201]], 'errors' => []]));

    (new SendIngestionBatchJob($payload))->handle($apiClient);

    expect(true)->toBeTrue();
});

it('sets the queue via onQueue', function () {
    expect((new SendIngestionBatchJob(['batch' => []]))->queue)->toBeNull()
        ->and((new SendIngestionBatchJob(['batch' => []]))->onQueue('langfuse')->queue)->toBe('langfuse');
});

it('retries with an increasing backoff', function () {
    $job = new SendIngestionBatchJob(['batch' => []]);

    expect($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([10, 60, 300]);
});

it('logs the dropped batch size when it fails permanently', function () {
    Log::shouldReceive('warning')
        ->once()
        ->with('Langfuse score batch failed permanently', Mockery::on(function (array $context) {
            return $context['scores'] === 2 && $context['message'] === 'gone';
        }));

    $job = new SendIngestionBatchJob(['batch' => [['id' => 'a'], ['id' => 'b']]]);

    $job->failed(new RuntimeException('gone'));

    expect(true)->toBeTrue();
});
