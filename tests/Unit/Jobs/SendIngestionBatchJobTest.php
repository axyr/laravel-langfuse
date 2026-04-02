<?php

declare(strict_types=1);

use Axyr\Langfuse\Contracts\IngestionApiClientInterface;
use Axyr\Langfuse\Dto\IngestionResponse;
use Axyr\Langfuse\Jobs\SendIngestionBatchJob;

it('calls sendRaw on the api client with the payload', function () {
    $payload = [
        'batch' => [['id' => 'evt-1', 'type' => 'trace-create', 'timestamp' => '2024-01-01T00:00:00Z', 'body' => ['id' => 'trace-1']]],
        'metadata' => (object) ['batch_size' => 1],
    ];

    $apiClient = Mockery::mock(IngestionApiClientInterface::class);
    $apiClient->shouldReceive('sendRaw')
        ->once()
        ->with($payload)
        ->andReturn(IngestionResponse::fromArray(['successes' => [['id' => 'evt-1', 'status' => 201]], 'errors' => []]));

    $job = new SendIngestionBatchJob($payload);
    $job->handle($apiClient);
});

it('sets the queue via onQueue', function () {
    $job = new SendIngestionBatchJob(['batch' => []]);

    expect($job->queue)->toBeNull();

    $job = (new SendIngestionBatchJob(['batch' => []]))->onQueue('langfuse');

    expect($job->queue)->toBe('langfuse');
});
