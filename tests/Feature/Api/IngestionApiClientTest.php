<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Langfuse\Api\IngestionApiClient;
use Langfuse\Config\LangfuseConfig;
use Langfuse\Dto\IngestionBatch;
use Langfuse\Dto\IngestionEvent;
use Langfuse\Dto\IngestionResponse;
use Langfuse\Dto\TraceBody;
use Langfuse\Enums\EventType;

beforeEach(function () {
    $this->config = new LangfuseConfig(
        publicKey: 'pk-test',
        secretKey: 'sk-test',
        baseUrl: 'https://cloud.langfuse.com',
    );
    $this->client = new IngestionApiClient($this->config);
});

it('sends batch to correct url with auth headers', function () {
    Http::fake([
        'cloud.langfuse.com/api/public/ingestion' => Http::response([
            'successes' => [['id' => 'evt-1', 'status' => 201]],
            'errors' => [],
        ]),
    ]);

    $batch = new IngestionBatch(batch: [
        new IngestionEvent(
            id: 'evt-1',
            type: EventType::TraceCreate,
            timestamp: '2024-01-01T00:00:00Z',
            body: new TraceBody(id: 'trace-1', name: 'test'),
        ),
    ]);

    $response = $this->client->send($batch);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://cloud.langfuse.com/api/public/ingestion'
            && $request->hasHeader('Authorization', $this->config->authHeader())
            && $request->hasHeader('Content-Type', 'application/json')
            && isset($request->data()['batch'])
            && count($request->data()['batch']) === 1;
    });

    expect($response)->toBeInstanceOf(IngestionResponse::class)
        ->and($response->successes)->toHaveCount(1)
        ->and($response->errors)->toHaveCount(0);
});

it('returns null on http error without throwing', function () {
    Http::fake([
        'cloud.langfuse.com/api/public/ingestion' => Http::response('Server Error', 500),
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->with('Langfuse ingestion failed', \Mockery::type('array'));

    $batch = new IngestionBatch(batch: []);

    $response = $this->client->send($batch);

    expect($response)->toBeNull();
});

it('returns null on connection exception without throwing', function () {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('DNS resolution failed');
    });

    Log::shouldReceive('warning')
        ->once()
        ->with('Langfuse ingestion error', \Mockery::type('array'));

    $batch = new IngestionBatch(batch: []);

    $response = $this->client->send($batch);

    expect($response)->toBeNull();
});

it('parses response with errors', function () {
    Http::fake([
        'cloud.langfuse.com/api/public/ingestion' => Http::response([
            'successes' => [['id' => 'evt-1', 'status' => 201]],
            'errors' => [['id' => 'evt-2', 'status' => 400, 'message' => 'Invalid']],
        ]),
    ]);

    $batch = new IngestionBatch(batch: []);

    $response = $this->client->send($batch);

    expect($response)->toBeInstanceOf(IngestionResponse::class)
        ->and($response->hasErrors())->toBeTrue()
        ->and($response->errors[0]->message)->toBe('Invalid');
});

it('sends correct payload structure', function () {
    Http::fake([
        'cloud.langfuse.com/api/public/ingestion' => Http::response([
            'successes' => [],
            'errors' => [],
        ]),
    ]);

    $batch = new IngestionBatch(
        batch: [
            new IngestionEvent(
                id: 'evt-1',
                type: EventType::TraceCreate,
                timestamp: '2024-01-01T00:00:00Z',
                body: new TraceBody(id: 'trace-1'),
            ),
        ],
        metadata: ['sdk_version' => '1.0.0'],
    );

    $this->client->send($batch);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $data['batch'][0]['id'] === 'evt-1'
            && $data['batch'][0]['type'] === 'trace-create'
            && $data['batch'][0]['body']['id'] === 'trace-1';
    });
});
