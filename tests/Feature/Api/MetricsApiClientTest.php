<?php

declare(strict_types=1);

use Axyr\Langfuse\Api\MetricsApiClient;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Dto\MetricQuery;
use Axyr\Langfuse\Dto\MetricsResponse;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\MetricFixtures;

beforeEach(function () {
    $this->config = new LangfuseConfig(
        publicKey: 'pk-test',
        secretKey: 'sk-test',
        baseUrl: 'https://test.langfuse.com',
    );

    $this->query = new MetricQuery(
        view: 'traces',
        metrics: [['measure' => 'count', 'aggregation' => 'count']],
        fromTimestamp: '2024-05-01T00:00:00Z',
        toTimestamp: '2024-05-02T00:00:00Z',
        dimensions: [['field' => 'name']],
    );
});

it('queries metrics and parses the response', function () {
    Http::fake([
        'test.langfuse.com/api/public/metrics*' => Http::response(MetricFixtures::tracesByName()),
    ]);

    $client = new MetricsApiClient($this->config);
    $result = $client->query($this->query);

    expect($result)->toBeInstanceOf(MetricsResponse::class)
        ->and($result->data)->toHaveCount(2)
        ->and($result->data[0]['name'])->toBe('chat');
});

it('sends the query as a json-encoded query parameter', function () {
    Http::fake([
        'test.langfuse.com/api/public/metrics*' => Http::response(MetricFixtures::tracesByName()),
    ]);

    $client = new MetricsApiClient($this->config);
    $client->query($this->query);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/api/public/metrics')) {
            return false;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($request->data()['query'], true);

        return $request->method() === 'GET'
            && $decoded['view'] === 'traces'
            && $decoded['dimensions'] === [['field' => 'name']]
            && $request->hasHeader('Authorization', 'Basic ' . base64_encode('pk-test:sk-test'));
    });
});

it('returns null on http error', function () {
    Http::fake([
        'test.langfuse.com/api/public/metrics*' => Http::response('Bad Request', 400),
    ]);

    $client = new MetricsApiClient($this->config);

    expect($client->query($this->query))->toBeNull();
});

it('returns null on network error', function () {
    Http::fake([
        'test.langfuse.com/api/public/metrics*' => fn() => throw new \Exception('Connection refused'),
    ]);

    $client = new MetricsApiClient($this->config);

    expect($client->query($this->query))->toBeNull();
});
