<?php

declare(strict_types=1);

use Axyr\Langfuse\Api\IngestionApiClient;
use Axyr\Langfuse\Batch\EventBatcher;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Contracts\IngestionApiClientInterface;
use Axyr\Langfuse\Dto\IngestionBatch;
use Axyr\Langfuse\Dto\IngestionEvent;
use Axyr\Langfuse\Dto\TraceBody;
use Axyr\Langfuse\Enums\EventType;
use Axyr\Langfuse\LangfuseFacade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config([
        'langfuse.public_key' => 'pk-test',
        'langfuse.secret_key' => 'sk-test',
        'langfuse.enabled' => true,
        'langfuse.flush_at' => 100,
    ]);

    $this->app->forgetInstance(\Axyr\Langfuse\Config\LangfuseConfig::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Contracts\EventBatcherInterface::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Contracts\IngestionApiClientInterface::class);
    $this->app->forgetInstance(\Axyr\Langfuse\Contracts\LangfuseClientInterface::class);
});

it('handles connection timeout without throwing', function () {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
    });

    Log::shouldReceive('warning')
        ->atLeast()
        ->once();

    $trace = LangfuseFacade::trace(new TraceBody(id: 'trace-timeout'));
    LangfuseFacade::flush();

    // If we get here, no exception was thrown
    expect(true)->toBeTrue();
});

it('handles DNS resolution failure without throwing', function () {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('DNS resolution failed');
    });

    Log::shouldReceive('warning')
        ->atLeast()
        ->once();

    $trace = LangfuseFacade::trace(new TraceBody(id: 'trace-dns'));
    LangfuseFacade::flush();

    expect(true)->toBeTrue();
});

it('handles 500 server error without throwing', function () {
    Http::fake([
        '*' => Http::response('Internal Server Error', 500),
    ]);

    Log::shouldReceive('warning')
        ->atLeast()
        ->once();

    $trace = LangfuseFacade::trace(new TraceBody(id: 'trace-500'));
    LangfuseFacade::flush();

    expect(true)->toBeTrue();
});

it('handles 401 unauthorized without throwing', function () {
    Http::fake([
        '*' => Http::response('Unauthorized', 401),
    ]);

    Log::shouldReceive('warning')
        ->atLeast()
        ->once();

    $trace = LangfuseFacade::trace(new TraceBody(id: 'trace-401'));
    LangfuseFacade::flush();

    expect(true)->toBeTrue();
});

it('handles invalid JSON response without throwing', function () {
    Http::fake([
        '*' => Http::response('not json at all', 200),
    ]);

    $trace = LangfuseFacade::trace(new TraceBody(id: 'trace-invalid-json'));
    LangfuseFacade::flush();

    expect(true)->toBeTrue();
});

it('resets batcher queue after failed flush', function () {
    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk', flushAt: 100);

    $apiClient = Mockery::mock(IngestionApiClientInterface::class);
    $apiClient->shouldReceive('send')
        ->once()
        ->andThrow(new RuntimeException('Network error'));

    $batcher = new EventBatcher($apiClient, $config);

    $batcher->enqueue(new IngestionEvent(
        id: 'evt-1',
        type: EventType::TraceCreate,
        timestamp: '2024-01-01T00:00:00Z',
        body: new TraceBody(id: 'trace-1'),
    ));

    expect($batcher->count())->toBe(1);

    $batcher->flush();

    expect($batcher->count())->toBe(0);
});

it('handles empty config gracefully', function () {
    $config = LangfuseConfig::fromArray([]);

    expect($config->publicKey)->toBe('')
        ->and($config->secretKey)->toBe('')
        ->and($config->enabled)->toBeTrue();
});

it('api client returns null on send failure without affecting subsequent calls', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push('Server Error', 500)
            ->push(['successes' => [['id' => 'evt-1', 'status' => 201]], 'errors' => []]),
    ]);

    Log::shouldReceive('warning')
        ->once();

    $config = new LangfuseConfig(publicKey: 'pk', secretKey: 'sk');
    $client = new IngestionApiClient($config);

    $batch = new IngestionBatch(batch: []);

    // First call fails
    $result1 = $client->send($batch);
    expect($result1)->toBeNull();

    // Second call succeeds
    $result2 = $client->send($batch);
    expect($result2)->not->toBeNull()
        ->and($result2->successes)->toHaveCount(1);
});
