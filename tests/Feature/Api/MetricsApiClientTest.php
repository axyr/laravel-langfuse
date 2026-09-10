<?php

declare(strict_types=1);

use Axyr\Langfuse\Api\MetricsApiClient;
use Axyr\Langfuse\Config\LangfuseConfig;
use Axyr\Langfuse\Dto\MetricsResponse;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\MetricFixtures;

beforeEach(function () {
    $this->config = new LangfuseConfig(
        publicKey: 'pk-test',
        secretKey: 'sk-test',
        baseUrl: 'https://test.langfuse.com',
    );

    $this->query = MetricFixtures::rootObservationsByTraceNameQuery();
    $this->client = new MetricsApiClient($this->config);
});

it('queries metrics and parses the response', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/metrics*' => Http::response(MetricFixtures::rootObservationsByTraceName()),
    ]);

    $result = $this->client->query($this->query);

    expect($result)->toBeInstanceOf(MetricsResponse::class)
        ->and($result->data)->toHaveCount(2)
        ->and($result->data[0]['traceName'])->toBe('chat')
        ->and($result->data[0]['count_count'])->toBe(42);
});

it('sends the query as a json-encoded query parameter to v2', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/metrics*' => Http::response(MetricFixtures::rootObservationsByTraceName()),
    ]);

    $this->client->query($this->query);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/api/public/v2/metrics')) {
            return false;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($request->data()['query'], true);

        return $request->method() === 'GET'
            && $decoded['view'] === 'observations'
            && $decoded['dimensions'] === [['field' => 'traceName']]
            && $decoded['filters'][0]['column'] === 'isRootObservation'
            && $request->hasHeader('Authorization', 'Basic ' . base64_encode('pk-test:sk-test'));
    });
});

it('returns null on http error', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/metrics*' => Http::response('Bad Request', 400),
    ]);

    expect($this->client->query($this->query))->toBeNull();
});

it('returns null on network error', function () {
    Http::fake([
        'test.langfuse.com/api/public/v2/metrics*' => fn() => throw new \Exception('Connection refused'),
    ]);

    expect($this->client->query($this->query))->toBeNull();
});
